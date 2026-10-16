import { createHmac, timingSafeEqual } from 'node:crypto';
import { z } from 'zod';

/**
 * Verifies preview tokens issued by the plugin (Preview\Tokens):
 * `base64url(json) + "." + base64url(HMAC-SHA256(base64url(json)))`.
 */
export const PREVIEW_LIFETIME_SECONDS = 300;

const PayloadSchema = z.strictObject({
  v: z.literal(1),
  id: z.number().int().positive(),
  parentId: z.number().int().positive(),
  path: z
    .string()
    .max(2048)
    .refine(
      (path) =>
        path.startsWith('/') &&
        !path.startsWith('//') &&
        !/[\\\s\u0000-\u001f?#]/.test(path),
      'Unsafe preview path',
    ),
  iat: z.number().int(),
  exp: z.number().int(),
  jti: z.string().min(1).max(100),
});

export type PreviewToken = z.infer<typeof PayloadSchema>;

function sign(payload: string, secret: string): Buffer {
  return createHmac('sha256', secret).update(payload).digest();
}

export function verifyPreviewToken(
  token: string,
  secret: string,
  now = Math.floor(Date.now() / 1000),
): PreviewToken | null {
  if (token.length > 2048) return null;
  const [payload, signature, extra] = token.split('.');
  if (!payload || !signature || extra !== undefined) return null;

  const expected = sign(payload, secret);
  const given = Buffer.from(signature, 'base64url');
  if (given.length !== expected.length || !timingSafeEqual(given, expected)) {
    return null;
  }

  let data: unknown;
  try {
    data = JSON.parse(Buffer.from(payload, 'base64url').toString('utf8'));
  } catch {
    return null;
  }
  const parsed = PayloadSchema.safeParse(data);
  if (!parsed.success) return null;
  const { iat, exp } = parsed.data;
  if (exp < now || iat > now + 30 || exp - iat > PREVIEW_LIFETIME_SECONDS) {
    return null;
  }
  return parsed.data;
}

/** Test helper and mirror of the PHP issuer. */
export function issuePreviewToken(
  payload: PreviewToken,
  secret: string,
): string {
  const body = Buffer.from(JSON.stringify(payload)).toString('base64url');
  return `${body}.${sign(body, secret).toString('base64url')}`;
}
