import type { Site } from '@websage/contracts';
import { connection } from 'next/server';
import { getSite } from '@/lib/api/fetchers';
import { Footer } from './Footer';
import { Header, HeaderFallback } from './Header';
import { SiteTheme } from './SiteTheme';

/*
 * Site-wide pieces read settings at request time (after connection()), so a
 * production build never needs WordPress to be reachable. getSite() caches
 * the validated result, so this is not a CMS call per request.
 */

/**
 * Settings, or null in production when they cannot be loaded: the page body
 * reports its own failure, and the chrome falls back to code defaults.
 * Development rethrows so contract mismatches are impossible to miss.
 */
async function loadSite(): Promise<Site | null> {
  try {
    return await getSite();
  } catch (error) {
    if (process.env.NODE_ENV !== 'production') throw error;
    console.error('websage: site settings unavailable', error);
    return null;
  }
}

export async function SiteHeader() {
  await connection();
  const site = await loadSite();
  if (!site) return <HeaderFallback />;
  return (
    <>
      <SiteTheme branding={site.branding} />
      <Header site={site} />
    </>
  );
}

export async function SiteFooter() {
  await connection();
  const site = await loadSite();
  return site ? <Footer site={site} /> : null;
}
