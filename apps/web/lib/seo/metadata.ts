import type { Seo, Site } from '@websage/contracts';
import type { Metadata } from 'next';

/** Public origin of this site, for absolute URLs (metadataBase, JSON-LD, sitemap). */
export function frontendOrigin(): string | undefined {
  const value = process.env.FRONTEND_ORIGIN;
  try {
    return value ? new URL(value).origin : undefined;
  } catch {
    return undefined;
  }
}

/**
 * Next.js metadata from the contract's Seo object. WordPress already applied
 * per-item fields and site-wide fallbacks; canonicals come from WordPress's
 * configured frontend origin.
 */
export function seoMetadata(seo: Seo, siteName?: string): Metadata {
  const images = seo.openGraphImage
    ? [
        {
          url: seo.openGraphImage.url,
          width: seo.openGraphImage.width,
          height: seo.openGraphImage.height,
          alt: seo.openGraphImage.alt,
        },
      ]
    : undefined;
  return {
    title: { absolute: seo.title },
    description: seo.description || undefined,
    alternates: { canonical: seo.canonical },
    robots: seo.noindex ? { index: false, follow: false } : undefined,
    openGraph: {
      title: seo.title,
      description: seo.description || undefined,
      url: seo.canonical,
      siteName,
      images,
      type: 'website',
    },
    twitter: {
      card: images ? 'summary_large_image' : 'summary',
      title: seo.title,
      description: seo.description || undefined,
      images: images?.map((image) => image.url),
    },
  };
}

/** Site-wide defaults for routes without their own SEO data. */
export function siteMetadata(site: Site): Metadata {
  const origin = frontendOrigin();
  return {
    metadataBase: origin ? new URL(origin) : undefined,
    title: { default: site.name, template: `%s | ${site.name}` },
    openGraph: { siteName: site.name, type: 'website' },
  };
}
