import { draftMode } from 'next/headers';
import { NextResponse, type NextRequest } from 'next/server';
import { getPreview } from '@/lib/api/fetchers';
import { isNotFound } from '@/lib/api/errors';
import { getSecret } from '@/lib/config';
import { GRANT_COOKIE, sealGrant } from '@/lib/preview/grant';
import { verifyPreviewToken } from '@/lib/preview/token';

const HEADERS = {
  'Cache-Control': 'private, no-store',
  'Referrer-Policy': 'no-referrer',
  'X-Robots-Tag': 'noindex',
};

function refuse(status: number, message: string) {
  return new NextResponse(message, { status, headers: HEADERS });
}

/**
 * Entry point from wp-admin's Preview button. Verifies the short-lived
 * token, confirms WordPress serves the draft for it, enables Draft Mode and
 * stores the grant in an encrypted HttpOnly cookie, then redirects to the
 * token's own path (never a caller-supplied URL).
 */
export async function GET(request: NextRequest) {
  const secret = getSecret('WSH_PREVIEW_SECRET');
  if (!secret) return refuse(503, 'Preview is not configured.');

  const token = request.nextUrl.searchParams.get('token') ?? '';
  const payload = verifyPreviewToken(token, secret);
  if (!payload) {
    return refuse(
      401,
      'This preview link is invalid or has expired. Use Preview in WordPress again.',
    );
  }

  try {
    await getPreview(payload.id, token);
  } catch (error) {
    if (isNotFound(error)) return refuse(404, 'This draft no longer exists.');
    console.error('websage: preview fetch failed', error);
    return refuse(503, 'Preview is temporarily unavailable.');
  }

  (await draftMode()).enable();
  // Behind a TLS proxy request.url is the internal URL; prefer the public origin.
  const base = process.env.FRONTEND_ORIGIN ?? request.url;
  const response = NextResponse.redirect(new URL(payload.path, base), 303);
  for (const [name, value] of Object.entries(HEADERS)) {
    response.headers.set(name, value);
  }
  response.cookies.set(GRANT_COOKIE, sealGrant(token, secret), {
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
    sameSite: 'lax',
    path: '/',
    maxAge: Math.max(1, payload.exp - Math.floor(Date.now() / 1000)),
  });
  return response;
}
