import page from '@websage/contracts/fixtures/page-hero.json';
import site from '@websage/contracts/fixtures/site-default.json';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const cache = vi.hoisted(() => ({ cacheTag: vi.fn(), cacheLife: vi.fn() }));
vi.mock('next/cache', () => cache);

const { getPage, getSite, pageSlugFor, PUBLIC_REVALIDATE_SECONDS } =
  await import('@/lib/api/fetchers');
const { ApiError } = await import('@/lib/api/errors');

const fetchMock = vi.fn<typeof fetch>();

beforeEach(() => {
  vi.stubEnv('WP_API_ORIGIN', 'https://cms.example.com');
  vi.stubEnv('WSH_FIXTURE_MODE', 'false');
  vi.stubGlobal('fetch', fetchMock);
});

afterEach(() => {
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
  vi.clearAllMocks();
});

describe('cache policy', () => {
  it('tags site data and uses the 300 second fallback', async () => {
    fetchMock.mockResolvedValue(Response.json(site));

    await getSite();

    expect(cache.cacheTag).toHaveBeenCalledWith('site');
    expect(cache.cacheLife).toHaveBeenCalledWith(
      expect.objectContaining({ revalidate: PUBLIC_REVALIDATE_SECONDS }),
    );
    expect(PUBLIC_REVALIDATE_SECONDS).toBe(300);
  });

  it('tags pages by frontend path', async () => {
    fetchMock.mockResolvedValue(
      Response.json({ data: { ...page.data, path: '/about/team' } }),
    );

    await getPage('/about/team');

    expect(cache.cacheTag).toHaveBeenCalledWith('page:/about/team', 'site');
    expect(String(fetchMock.mock.calls[0]![0])).toBe(
      'https://cms.example.com/wp-json/websage/v1/pages/about/team',
    );
  });
});

describe('failure handling', () => {
  it('never substitutes fixture content when a configured CMS fails', async () => {
    fetchMock.mockRejectedValue(new TypeError('connect ECONNREFUSED'));

    await expect(getPage('/')).rejects.toMatchObject({ kind: 'unavailable' });
    await expect(getSite()).rejects.toBeInstanceOf(ApiError);
  });

  it('serves shared fixtures only in explicit development fixture mode', async () => {
    vi.stubEnv('WSH_FIXTURE_MODE', 'true');
    vi.stubEnv('NODE_ENV', 'development');

    await expect(getPage('/')).resolves.toEqual(page.data);
    await expect(getSite()).resolves.toEqual(site.data);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('returns null for impossible paths without a request', async () => {
    await expect(getPage('/blog/../x')).resolves.toBeNull();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('returns null only for a confirmed 404', async () => {
    fetchMock.mockResolvedValueOnce(
      Response.json(
        { error: { code: 'not_found', message: 'x', requestId: 'r' } },
        { status: 404 },
      ),
    );
    await expect(getPage('/missing')).resolves.toBeNull();

    fetchMock.mockResolvedValueOnce(Response.json({}, { status: 503 }));
    await expect(getPage('/missing')).rejects.toMatchObject({
      kind: 'unavailable',
    });
  });
});

describe('pageSlugFor', () => {
  it.each([
    ['/', 'home'],
    ['/about', 'about'],
    ['/about/team/', 'about/team'],
    ['/café', 'caf%C3%A9'],
  ])('%s → %s', (path, slug) => {
    expect(pageSlugFor(path)).toBe(slug);
  });

  it.each(['/About', '/a b', '/..', '/a//b', 'about', '/a\u0000'])(
    'rejects %j',
    (path) => {
      expect(pageSlugFor(path)).toBeNull();
    },
  );
});
