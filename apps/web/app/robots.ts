import type { MetadataRoute } from 'next';
import { frontendOrigin } from '@/lib/seo/metadata';

export default function robots(): MetadataRoute.Robots {
  const origin = frontendOrigin();
  return {
    rules: [{ userAgent: '*', allow: '/', disallow: ['/api/', '/wsh-404'] }],
    ...(origin ? { sitemap: `${origin}/sitemap.xml` } : {}),
  };
}
