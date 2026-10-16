import { describe, expect, it } from 'vitest';
import pageSections from '../fixtures/page-sections.json';
import postsList from '../fixtures/posts-list.json';
import post from '../fixtures/post.json';
import routes from '../fixtures/routes.json';
import previewPage from '../fixtures/preview-page.json';
import event from '../fixtures/revalidation-event.json';
import * as c from '../src/index';

const sections = pageSections.data.sections;
const byType = (type: string) => sections.find((s) => s.type === type)!;

describe('endpoint fixtures', () => {
  it.each([
    ['page with every section', c.PageResponseSchema, pageSections],
    ['posts list', c.PostListResponseSchema, postsList],
    ['single post', c.PostResponseSchema, post],
    ['routes', c.RouteListResponseSchema, routes],
    ['preview', c.PreviewResponseSchema, previewPage],
    ['revalidation event', c.RevalidationEventSchema, event],
  ] as const)('%s parses unchanged', (_name, schema, fixture) => {
    expect(schema.parse(fixture)).toEqual(fixture);
  });

  it('covers every known section type', () => {
    expect(new Set(sections.map((s) => s.type))).toEqual(
      new Set(c.KNOWN_SECTION_TYPES),
    );
  });
});

describe('known sections fail loudly instead of becoming unknown', () => {
  it.each([
    ['text-image', { position: 'center' }],
    ['feature-grid', { columns: 5 }],
    [
      'feature-grid',
      { items: Array(13).fill(byType('feature-grid').items![0]) },
    ],
    ['cta', { link: null }],
    ['testimonials', { items: [{ quote: 'x' }] }],
    ['faq', { items: Array(31).fill({ question: 'q', answer: 'a' }) }],
    ['contact', { recipient: 'owner@example.com' }],
    ['core-content', { html: 'x'.repeat(100_001) }],
  ])('%s with %j', (type, change) => {
    expect(() => c.parseSection({ ...byType(type), ...change })).toThrow();
  });

  it('keeps only the envelope of unknown future types', () => {
    expect(
      c.parseSection({
        type: 'pricing',
        schemaVersion: 1,
        key: '9',
        plans: [],
      }),
    ).toEqual({ type: 'pricing', schemaVersion: 1, key: '9' });
  });
});

describe('posts and routes', () => {
  it('rejects post paths outside /blog and invalid slugs', () => {
    const item = postsList.data.items[0]!;
    expect(() =>
      c.PostSummarySchema.parse({ ...item, path: '/hello' }),
    ).toThrow();
    expect(() =>
      c.PostSummarySchema.parse({ ...item, slug: 'Bad Slug' }),
    ).toThrow();
  });

  it('rejects unknown route kinds', () => {
    expect(() =>
      c.RouteItemSchema.parse({
        path: '/',
        kind: 'attachment',
        modified: '2026-10-16T12:00:00Z',
      }),
    ).toThrow();
  });
});

describe('cache tags and events', () => {
  it.each([
    'site',
    'posts',
    'routes',
    'page:/',
    'page:/about/team',
    'post:hello',
    'page:/café',
  ])('accepts %s', (tag) => expect(c.CacheTagSchema.parse(tag)).toBe(tag));

  it.each([
    'page:about',
    'page:/../x',
    'page://evil',
    'post:',
    'post:a/b',
    'everything',
    'page:/About',
  ])('rejects %s', (tag) =>
    expect(() => c.CacheTagSchema.parse(tag)).toThrow(),
  );

  it('rejects events without tags or with unknown fields', () => {
    expect(() =>
      c.RevalidationEventSchema.parse({ ...event, tags: [] }),
    ).toThrow();
    expect(() =>
      c.RevalidationEventSchema.parse({ ...event, extra: 1 }),
    ).toThrow();
    expect(() =>
      c.RevalidationEventSchema.parse({ ...event, event: 'content.exploded' }),
    ).toThrow();
  });
});

describe('preview', () => {
  it('requires the content to match its kind', () => {
    expect(() =>
      c.PreviewResponseSchema.parse({
        data: { kind: 'post', content: previewPage.data.content },
      }),
    ).toThrow();
  });
});
