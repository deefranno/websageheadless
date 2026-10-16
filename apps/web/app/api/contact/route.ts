import { ContactFormSchema, contactFieldErrors } from '@websage/contracts';
import { NextResponse, type NextRequest } from 'next/server';
import { getSecret } from '@/lib/config';
import { clientHash, clientIp, ProxyTrustError } from '@/lib/contact/client-ip';
import { submitToWordPress } from '@/lib/contact/submit';
import { turnstilePasses } from '@/lib/contact/turnstile';

const MAX_BYTES = 16_384;
const NO_STORE = { 'Cache-Control': 'no-store' };

function json(
  status: number,
  body: unknown,
  headers: Record<string, string> = {},
) {
  return NextResponse.json(body, {
    status,
    headers: { ...NO_STORE, ...headers },
  });
}

function error(
  status: number,
  code: string,
  message: string,
  extra: object = {},
  headers: Record<string, string> = {},
) {
  return json(status, { error: { code, message, ...extra } }, headers);
}

/**
 * Browser → Next.js → WordPress. The browser never talks to WordPress; this
 * handler validates, identifies the client by a keyed hash, optionally
 * checks Turnstile, and maps WordPress's answer to a safe response.
 */
export async function POST(request: NextRequest) {
  const origin = request.headers.get('origin');
  const allowed = [process.env.FRONTEND_ORIGIN, request.nextUrl.origin].filter(
    Boolean,
  );
  if (!origin || !allowed.includes(origin)) {
    return error(403, 'forbidden', 'Cross-site submissions are not accepted.');
  }

  const rateSecret = getSecret('WSH_RATE_LIMIT_SECRET');
  if (!rateSecret)
    return error(503, 'not_configured', 'The form is temporarily unavailable.');

  if (Number(request.headers.get('content-length') ?? '0') > MAX_BYTES) {
    return error(413, 'too_large', 'The message is too large.');
  }
  const text = await request.text();
  if (Buffer.byteLength(text) > MAX_BYTES) {
    return error(413, 'too_large', 'The message is too large.');
  }

  let body: unknown;
  try {
    body = JSON.parse(text);
  } catch {
    return error(400, 'invalid_request', 'The request could not be read.');
  }
  const parsed = ContactFormSchema.safeParse(body);
  if (!parsed.success) {
    return error(
      422,
      'validation_failed',
      'Please check the highlighted fields.',
      {
        fields: contactFieldErrors(parsed.error),
      },
    );
  }

  let ip: string;
  try {
    ip = clientIp(request.headers);
  } catch (cause) {
    if (cause instanceof ProxyTrustError)
      console.error(`websage: ${cause.message}`);
    return error(503, 'not_configured', 'The form is temporarily unavailable.');
  }

  const { turnstileToken, ...input } = parsed.data;
  if (!(await turnstilePasses(turnstileToken, ip))) {
    return error(
      422,
      'validation_failed',
      'Please complete the verification.',
      {
        fields: { turnstileToken: 'invalid' },
      },
    );
  }

  const result = await submitToWordPress(input, clientHash(ip, rateSecret));
  switch (result.status) {
    case 'accepted':
      return json(200, { data: { accepted: true } });
    case 'invalid':
      return error(
        422,
        'validation_failed',
        'Please check the highlighted fields.',
        {
          fields: result.fields,
        },
      );
    case 'rate_limited':
      return error(
        429,
        'rate_limited',
        'Too many messages. Please try again later.',
        { retryAfter: result.retryAfter },
        { 'Retry-After': String(result.retryAfter) },
      );
    case 'conflict':
      return error(
        409,
        'conflict',
        'This message was already sent with different content.',
      );
    default:
      return error(
        503,
        'unavailable',
        'The form is temporarily unavailable. Please try again soon.',
      );
  }
}
