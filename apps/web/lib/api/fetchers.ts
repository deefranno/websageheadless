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
  cacheTag(`page:${path}`);
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
