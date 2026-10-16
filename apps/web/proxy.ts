import { RouteListResponseSchema } from '@websage/contracts';
import { NextResponse, type NextRequest } from 'next/server';
import { fetchApi } from '@/lib/api/http';

/*
 * Real 404 status for paths WordPress does not know (ADR-011).
 *
 * Page responses stream, so a page cannot change its status once rendering
 * starts. This check runs first: paths absent from /routes are rewritten to
 * /wsh-404, which calls notFound() before streaming. The route list is held
 * per proxy instance (proxy shares no state with the app); a miss refetches
 * once (at most every 5 s) so newly published pages work immediately. Any
 * failure fails open: an outage never turns pages into 404s.
 */

const TTL_MS = 60_000;
const MISS_REFRESH_MS = 5_000;

let cached: { paths: Set<string>; at: number } | null = null;

async function load(force: boolean): Promise<Set<string> | null> {
  const now = Date.now();
  if (!force && cached && now - cached.at < TTL_MS) return cached.paths;
  try {
    const paths = new Set<string>();
    for (let page = 1; page <= 100; page++) {
      const { data } = await fetchApi('/routes', RouteListResponseSchema, {
        query: { page, perPage: 500 },
      });
      data.items.forEach((item) => paths.add(item.path));
      if (page >= data.pagination.totalPages) break;
    }
    cached = { paths, at: now };
    return paths;
  } catch {
    return null;
  }
}

function normalize(pathname: string): string | null {
  try {
    const path = decodeURIComponent(pathname);
    return path.length > 1 ? path.replace(/\/+$/, '') : path;
  } catch {
    return null;
  }
}

export async function proxy(request: NextRequest) {
  // Previews may show unpublished paths; fixture mode has no CMS.
  if (
    request.cookies.has('__prerender_bypass') ||
    request.cookies.has('wsh_preview') ||
    process.env.WSH_FIXTURE_MODE === 'true'
  ) {
    return NextResponse.next();
  }
  const path = normalize(request.nextUrl.pathname);
  if (path === null)
    return NextResponse.rewrite(new URL('/wsh-404', request.url));

  let routes = await load(false);
  if (!routes || routes.has(path)) return NextResponse.next();
  if (cached && Date.now() - cached.at >= MISS_REFRESH_MS) {
    routes = await load(true);
    if (!routes || routes.has(path)) return NextResponse.next();
  }
  return NextResponse.rewrite(new URL('/wsh-404', request.url));
}

export const config = {
  matcher: [
    '/((?!api/|_next/|wsh-404|favicon\\.ico|robots\\.txt|sitemap\\.xml|.*\\.[A-Za-z0-9]+$).*)',
  ],
};
