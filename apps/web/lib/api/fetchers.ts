import 'server-only';
import { cacheLife, cacheTag } from 'next/cache';
import {
  PageResponseSchema,
  SiteResponseSchema,
  type Page,
  type Site,
} from '@websage/contracts';
import pageFixture from '@websage/contracts/fixtures/page-hero.json';
import siteFixture from '@websage/contracts/fixtures/site-default.json';
import { getServerConfig } from '../config';
import { fetchApi } from './client';
import { ApiError } from './errors';

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
 * Validated page for a frontend path. Throws ApiError('not_found') only when
 * WordPress confirms absence; outages throw 'unavailable' and are not cached.
 */
export async function getPage(path: string): Promise<Page> {
  'use cache';
  cacheTag(`page:${path}`);
  cacheLife(PUBLIC_CACHE_LIFE);

  const slug = pageSlugFor(path);
  if (slug === null) {
    throw new ApiError('not_found', `No CMS page can match ${path}`);
  }
  if (getServerConfig().fixtureMode) {
    if (path !== '/') throw new ApiError('not_found', `No fixture for ${path}`);
    return PageResponseSchema.parse(pageFixture).data;
  }
  return (await fetchApi(`/pages/${slug}`, PageResponseSchema)).data;
}
