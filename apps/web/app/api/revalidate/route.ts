import { revalidateTag } from 'next/cache';
import { getSecret } from '@/lib/config';
import { MAX_BODY_BYTES, verifyWebhook } from '@/lib/revalidate/verify';

const NO_STORE = { 'Cache-Control': 'no-store' };

function error(status: number, code: string) {
  return Response.json(
    { error: { code, message: 'Webhook rejected.' } },
    { status, headers: NO_STORE },
  );
}

/**
 * Signed content-change webhook from WebSage Headless Core. Expires only the
 * tags named in the verified event, immediately.
 */
export async function POST(request: Request) {
  const secret = getSecret('WSH_REVALIDATION_SECRET');
  if (!secret) return error(503, 'not_configured');

  const declared = Number(request.headers.get('content-length') ?? '0');
  if (declared > MAX_BODY_BYTES) return error(413, 'too_large');

  const result = verifyWebhook(
    {
      body: await request.text(),
      timestamp: request.headers.get('x-wsh-timestamp'),
      eventId: request.headers.get('x-wsh-event-id'),
      signature: request.headers.get('x-wsh-signature'),
    },
    secret,
  );
  if (!result.ok) {
    console.warn(`websage: revalidation webhook rejected (${result.reason})`);
    return error(
      result.status,
      result.status === 401 ? 'unauthorized' : 'invalid',
    );
  }

  for (const tag of result.event.tags) {
    revalidateTag(tag, { expire: 0 });
  }
  return Response.json({ data: { accepted: true } }, { headers: NO_STORE });
}
