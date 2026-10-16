import { draftMode } from 'next/headers';
import { NextResponse, type NextRequest } from 'next/server';
import { GRANT_COOKIE } from '@/lib/preview/grant';

/** Leaves preview. POST only, same-origin only. */
export async function POST(request: NextRequest) {
  const origin = request.headers.get('origin');
  const allowed = process.env.FRONTEND_ORIGIN ?? request.nextUrl.origin;
  if (origin !== allowed && origin !== request.nextUrl.origin) {
    return new NextResponse('Forbidden', { status: 403 });
  }
  (await draftMode()).disable();
  const response = NextResponse.redirect(
    new URL('/', process.env.FRONTEND_ORIGIN ?? request.url),
    303,
  );
  response.headers.set('Cache-Control', 'no-store');
  response.cookies.delete(GRANT_COOKIE);
  return response;
}
