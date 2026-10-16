import { describe, it, expect } from 'vitest';
import page from '../fixtures/page-hero.json';
import site from '../fixtures/site-default.json';
import * as contracts from '../src/index';

describe('public data boundary', () => {
  it('accepts the independently specified page and site examples', () => {
    expect(contracts.PageResponseSchema.parse(page)).toEqual(page);
    expect(contracts.SiteResponseSchema.parse(site)).toEqual(site);
  });
  it.each([
    { heading: undefined },
    { theme: 'custom' },
    { hiddenSecret: 'private' },
    { body: 'a'.repeat(20001) },
    { heading: 'a'.repeat(501) },
    {
      image: {
        url: 'https://cms.example.com/a.jpg',
        alt: '',
        width: 0,
        height: 5,
      },
    },
  ])(
    'rejects malformed known heroes rather than treating them as unknown: %j',
    (change) => {
      expect(() =>
        contracts.parseSection({ ...page.data.sections[0], ...change }),
      ).toThrow();
    },
  );
  it.each([
    'javascript:alert(1)',
    '//evil.example',
    'https://user:pass@evil.example',
    '/\\evil.example',
    '/%2f/evil.example',
  ])('rejects unsafe link %s', (href) => {
    expect(() =>
      contracts.parseSection({
        ...page.data.sections[0],
        primary: { label: 'Go', href, newTab: false },
      }),
    ).toThrow();
  });
  it('keeps only the future section envelope', () => {
    expect(
      contracts.parseSection({
        type: 'future',
        schemaVersion: 2,
        key: '1',
        private: 'never render',
      }),
    ).toEqual({ type: 'future', schemaVersion: 2, key: '1' });
  });
  it('rejects more than 100 page sections', () => {
    expect(() =>
      contracts.PageResponseSchema.parse({
        data: {
          ...page.data,
          sections: Array(101).fill(page.data.sections[0]),
        },
      }),
    ).toThrow();
  });
});
