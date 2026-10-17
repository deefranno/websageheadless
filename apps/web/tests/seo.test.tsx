import post from '@websage/contracts/fixtures/post.json';
import routes from '@websage/contracts/fixtures/routes.json';
import site from '@websage/contracts/fixtures/site-default.json';
import { PostSchema, SiteSchema } from '@websage/contracts';
import { render } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { JsonLd } from '@/components/seo/JsonLd';
import { seoMetadata } from '@/lib/seo/metadata';
import {
  article,
  breadcrumbs,
  organization,
  pageCrumbs,
} from '@/lib/seo/structured-data';

const fetchers = vi.hoisted(() => ({ getRoutes: vi.fn() }));
vi.mock('@/lib/api/fetchers', () => fetchers);
vi.mock('next/server', () => ({ connection: async () => {} }));
const sitemap = (await import('@/app/sitemap')).default;
const robots = (await import('@/app/robots')).default;

const thePost = PostSchema.parse(post.data);
const theSite = SiteSchema.parse(site.data);

beforeEach(() => vi.stubEnv('FRONTEND_ORIGIN', 'https://www.example.com'));
afterEach(() => vi.unstubAllEnvs());

describe('metadata', () => {
  it('maps the Seo contract to title, canonical, social cards and robots', () => {
    const metadata = seoMetadata({ ...thePost.seo, noindex: true }, 'Demo');
    expect(metadata.title).toEqual({ absolute: 'Hello | Demo' });
    expect(metadata.alternates?.canonical).toBe(
      'https://www.example.com/blog/hello',
    );
    expect(metadata.robots).toEqual({ index: false, follow: false });
    expect(metadata.openGraph).toMatchObject({
      url: 'https://www.example.com/blog/hello',
      siteName: 'Demo',
    });
    expect(metadata.twitter).toMatchObject({ card: 'summary' });
  });
});

describe('structured data', () => {
  it('escapes "<" so content cannot close the script element', () => {
    const { container } = render(
      <JsonLd data={{ name: '</script><script>alert(1)</script>' }} />,
    );
    const script = container.querySelector(
      'script[type="application/ld+json"]',
    )!;
    expect(script.innerHTML).not.toContain('</script>');
    expect(JSON.parse(script.textContent!)).toMatchObject({
      '@context': 'https://schema.org',
      name: '</script><script>alert(1)</script>',
    });
  });

  it('describes the organization, articles and breadcrumbs', () => {
    expect(organization(theSite)).toMatchObject({
      '@type': 'Organization',
      name: 'WebSage Demo',
      url: 'https://www.example.com',
      email: 'hello@example.com',
    });
    expect(article(thePost, theSite)).toMatchObject({
      '@type': 'Article',
      headline: 'Hello',
      mainEntityOfPage: 'https://www.example.com/blog/hello',
      datePublished: thePost.published,
    });
    expect(
      breadcrumbs(
        pageCrumbs(
          '/about/our-team',
          'Our team',
          'https://www.example.com/about/our-team',
        ),
      ).itemListElement,
    ).toEqual([
      {
        '@type': 'ListItem',
        position: 1,
        name: 'Home',
        item: 'https://www.example.com/',
      },
      {
        '@type': 'ListItem',
        position: 2,
        name: 'About',
        item: 'https://www.example.com/about',
      },
      {
        '@type': 'ListItem',
        position: 3,
        name: 'Our team',
        item: 'https://www.example.com/about/our-team',
      },
    ]);
  });
});

describe('sitemap and robots', () => {
  it('lists indexable routes only, with absolute URLs', async () => {
    fetchers.getRoutes.mockResolvedValue([
      ...routes.data.items,
      {
        path: '/hidden',
        kind: 'page',
        modified: '2026-10-16T12:00:00Z',
        noindex: true,
      },
    ]);
    const entries = await sitemap();
    expect(entries.map((e) => e.url)).toEqual([
      'https://www.example.com/',
      'https://www.example.com/blog',
      'https://www.example.com/blog/hello',
    ]);
  });

  it('points robots at the sitemap and keeps crawlers out of the API', () => {
    expect(robots()).toEqual({
      rules: [{ userAgent: '*', allow: '/', disallow: ['/api/', '/wsh-404'] }],
      sitemap: 'https://www.example.com/sitemap.xml',
    });
  });
});
