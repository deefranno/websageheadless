import type { Post, Site } from '@websage/contracts';
import { frontendOrigin } from './metadata';

export function organization(site: Site) {
  const origin = frontendOrigin();
  return {
    '@type': 'Organization',
    name: site.name,
    ...(origin ? { url: origin } : {}),
    ...(site.branding.logo ? { logo: site.branding.logo.url } : {}),
    ...(site.contact.email ? { email: site.contact.email } : {}),
    ...(site.footer.social.length > 0
      ? {
          sameAs: site.footer.social
            .filter((link) => link.url.startsWith('https://'))
            .map((link) => link.url),
        }
      : {}),
  };
}

export function website(site: Site) {
  const origin = frontendOrigin();
  return {
    '@type': 'WebSite',
    name: site.name,
    ...(origin ? { url: origin } : {}),
  };
}

/** Breadcrumbs from crumbs of [name, absolute URL]. */
export function breadcrumbs(crumbs: [string, string][]) {
  return {
    '@type': 'BreadcrumbList',
    itemListElement: crumbs.map(([name, item], index) => ({
      '@type': 'ListItem',
      position: index + 1,
      name,
      item,
    })),
  };
}

export function article(post: Post, site: Site) {
  const publisher = organization(site);
  return {
    '@type': 'Article',
    headline: post.title,
    description: post.excerpt || undefined,
    datePublished: post.published,
    dateModified: post.modified,
    mainEntityOfPage: post.seo.canonical,
    ...(post.image ? { image: [post.image.url] } : {}),
    author: publisher,
    publisher,
  };
}

/** Crumbs for a CMS page path: Home, intermediate segments, the page. */
export function pageCrumbs(
  path: string,
  title: string,
  canonical: string,
): [string, string][] {
  const origin = new URL(canonical).origin;
  const crumbs: [string, string][] = [['Home', `${origin}/`]];
  if (path === '/') return crumbs;
  const segments = path.slice(1).split('/');
  segments.slice(0, -1).forEach((segment, index) => {
    const name = segment
      .replace(/-/g, ' ')
      .replace(/^\p{Ll}/u, (c) => c.toUpperCase());
    crumbs.push([name, `${origin}/${segments.slice(0, index + 1).join('/')}`]);
  });
  crumbs.push([title, canonical]);
  return crumbs;
}
