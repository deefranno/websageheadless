import 'server-only';
import {
  createCipheriv,
  createDecipheriv,
  hkdfSync,
  randomBytes,
} from 'node:crypto';
import { verifyPreviewToken, type PreviewToken } from './token';

export const GRANT_COOKIE = 'wsh_preview';

function key(secret: string): Buffer {
  return Buffer.from(
    hkdfSync('sha256', secret, 'websage-headless', 'preview-grant-cookie', 32),
  );
}

/** AES-256-GCM-encrypted grant (the signed token) for the HttpOnly cookie. */
export function sealGrant(token: string, secret: string): string {
  const iv = randomBytes(12);
  const cipher = createCipheriv('aes-256-gcm', key(secret), iv);
  const body = Buffer.concat([cipher.update(token, 'utf8'), cipher.final()]);
  return Buffer.concat([iv, cipher.getAuthTag(), body]).toString('base64url');
}

/**
 * Decrypts and re-verifies a grant cookie. Returns the token and its payload
 * only while the token is still valid.
 */
export function openGrant(
  sealed: string | undefined,
  secret: string,
): { token: string; payload: PreviewToken } | null {
  if (!sealed || sealed.length > 4096) return null;
  try {
    const raw = Buffer.from(sealed, 'base64url');
    const decipher = createDecipheriv(
      'aes-256-gcm',
      key(secret),
      raw.subarray(0, 12),
    );
    decipher.setAuthTag(raw.subarray(12, 28));
    const token = Buffer.concat([
      decipher.update(raw.subarray(28)),
      decipher.final(),
    ]).toString('utf8');
    const payload = verifyPreviewToken(token, secret);
    return payload ? { token, payload } : null;
  } catch {
    return null;
  }
}
