import type { MetadataRoute } from 'next';
import { connection } from 'next/server';
import { getRoutes } from '@/lib/api/fetchers';
import { frontendOrigin } from '@/lib/seo/metadata';

/** Indexable routes from WordPress; read at request time, cached by tag. */
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  await connection();
  const origin = frontendOrigin();
  if (!origin) return [];
  const routes = await getRoutes();
  return routes
    .filter((route) => !route.noindex)
    .map((route) => ({
      url: `${origin}${route.path === '/' ? '/' : route.path}`,
      lastModified: route.modified,
    }));
}
