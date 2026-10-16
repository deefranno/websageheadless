import 'server-only';
import { cacheLife, cacheTag } from 'next/cache';
import {
  PageResponseSchema,
  PostListResponseSchema,
  PostResponseSchema,
  PreviewResponseSchema,
  RouteListResponseSchema,
  SiteResponseSchema,
  type Page,
  type Post,
  type PostList,
  type Preview,
  type RouteItem,
  type Site,
} from '@websage/contracts';
import pageFixture from '@websage/contracts/fixtures/page-hero.json';
import siteFixture from '@websage/contracts/fixtures/site-default.json';
import { ConfigError, getSecret, getServerConfig } from '../config';
import { fetchApi } from './client';
import { isNotFound } from './errors';

/** Fallback lifetime; signed webhooks expire tags immediately in Phase 3. */
export const PUBLIC_REVALIDATE_SECONDS = 300;

const PUBLIC_CACHE_LIFE = {
  stale: 60,
  revalidate: PUBLIC_REVALIDATE_SECONDS,
  expire: 86_400,
};

const SEGMENT = /^[\p{Ll}\p{Lo}\p{N}_-]+$/u;

/**
 * Maps a frontend path to the pages endpoint slug, or null when the path can
 * never name a CMS page. Mirrors PathResolver.php.
 */
export function pageSlugFor(path: string): string | null {
  if (path === '/') return 'home';
  if (!path.startsWith('/') || path.length > 2048) return null;
  const segments = path.slice(1).replace(/\/$/, '').split('/');
  if (segments.length > 10) return null;
  return segments.every((segment) => SEGMENT.test(segment))
    ? segments.map(encodeURIComponent).join('/')
    : null;
}

export async function getSite(): Promise<Site> {
  'use cache';
  cacheTag('site');
  cacheLife(PUBLIC_CACHE_LIFE);

  if (getServerConfig().fixtureMode) {
    return SiteResponseSchema.parse(siteFixture).data;
  }
  return (await fetchApi('/site', SiteResponseSchema)).data;
}

/**
 * Validated page for a frontend path, or null when WordPress confirms it does
 * not exist (or is not public). Absence is cached under the same tag, so
 * publishing the page invalidates it. Outages throw ApiError('unavailable')
 * and are never cached.
 */
export async function getPage(path: string): Promise<Page | null> {
  'use cache';
  // `site` too: settings (titles, canonicals, brand) shape every page.
  cacheTag(`page:${path}`, 'site');
  cacheLife(PUBLIC_CACHE_LIFE);

  const slug = pageSlugFor(path);
  if (slug === null) return null;
  if (getServerConfig().fixtureMode) {
    return path === '/' ? PageResponseSchema.parse(pageFixture).data : null;
  }
  try {
    return (await fetchApi(`/pages/${slug}`, PageResponseSchema)).data;
  } catch (error) {
    if (isNotFound(error)) return null;
    throw error;
  }
}

const POST_SLUG = /^[\p{Ll}\p{Lo}\p{N}_-]{1,200}$/u;

/** Single blog post, or null when WordPress confirms it is not public. */
export async function getPost(slug: string): Promise<Post | null> {
  'use cache';
  cacheTag(`post:${slug}`, 'site');
  cacheLife(PUBLIC_CACHE_LIFE);

  if (!POST_SLUG.test(slug) || getServerConfig().fixtureMode) return null;
  try {
    return (
      await fetchApi(`/posts/${encodeURIComponent(slug)}`, PostResponseSchema)
    ).data;
  } catch (error) {
    if (isNotFound(error)) return null;
    throw error;
  }
}

/** Paginated post list, optionally filtered by category slug. */
export async function getPosts(
  page = 1,
  perPage = 10,
  category?: string,
): Promise<PostList> {
  'use cache';
  cacheTag('posts', 'site');
  cacheLife(PUBLIC_CACHE_LIFE);

  if (getServerConfig().fixtureMode) {
    return {
      items: [],
      pagination: { page, perPage, total: 0, totalPages: 0 },
      categories: [],
    };
  }
  return (
    await fetchApi('/posts', PostListResponseSchema, {
      query: { page, perPage, category },
    })
  ).data;
}

/** Every public route (all pages of the routes endpoint). */
export async function getRoutes(): Promise<RouteItem[]> {
  'use cache';
  cacheTag('routes');
  cacheLife(PUBLIC_CACHE_LIFE);

  if (getServerConfig().fixtureMode) return [];
  const items: RouteItem[] = [];
  for (let page = 1; page <= 100; page++) {
    const { data } = await fetchApi('/routes', RouteListResponseSchema, {
      query: { page, perPage: 500 },
    });
    items.push(...data.items);
    if (page >= data.pagination.totalPages) break;
  }
  return items;
}

/**
 * Draft content for a verified preview grant. Never cached: it runs only in
 * Draft Mode and authenticates with the server API secret plus the grant.
 */
export async function getPreview(id: number, grant: string): Promise<Preview> {
  const secret = getSecret('WSH_SERVER_API_SECRET');
  if (!secret) throw new ConfigError('WSH_SERVER_API_SECRET is not set.');
  return (
    await fetchApi(`/preview/${id}`, PreviewResponseSchema, {
      headers: {
        Authorization: `Bearer ${secret}`,
        'X-WSH-Preview-Grant': grant,
      },
    })
  ).data;
}
