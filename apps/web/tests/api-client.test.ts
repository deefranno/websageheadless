import page from '@websage/contracts/fixtures/page-hero.json';
import { PageResponseSchema } from '@websage/contracts';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fetchApi, MAX_BYTES, TIMEOUT_MS } from '@/lib/api/client';
import { ApiError } from '@/lib/api/errors';
import { ConfigError, getServerConfig } from '@/lib/config';

const fetchMock = vi.fn<typeof fetch>();

function json(body: unknown, status = 200, headers: HeadersInit = {}) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json', ...headers },
  });
}

async function failure(promise: Promise<unknown>): Promise<ApiError> {
  const error = await promise.then(
    () => {
      throw new Error('expected rejection');
    },
    (reason: unknown) => reason,
  );
  expect(error).toBeInstanceOf(ApiError);
  return error as ApiError;
}

beforeEach(() => {
  vi.stubEnv('WP_API_ORIGIN', 'https://cms.example.com');
  vi.stubEnv('WSH_FIXTURE_MODE', 'false');
  vi.stubGlobal('fetch', fetchMock);
});

afterEach(() => {
  vi.useRealTimers();
  vi.unstubAllEnvs();
  vi.unstubAllGlobals();
  fetchMock.mockReset();
});

describe('fetchApi', () => {
  it('returns validated data from the configured origin without HTTP caching or redirects', async () => {
    fetchMock.mockResolvedValue(json(page));

    await expect(fetchApi('/pages/home', PageResponseSchema)).resolves.toEqual(
      page,
    );

    const [url, init] = fetchMock.mock.calls[0]!;
    expect(String(url)).toBe(
      'https://cms.example.com/wp-json/websage/v1/pages/home',
    );
    expect(init).toMatchObject({ redirect: 'error', cache: 'no-store' });
  });

  it('distinguishes a confirmed 404 from a server failure', async () => {
    const envelope = {
      error: { code: 'not_found', message: 'x', requestId: 'req-1' },
    };
    fetchMock.mockResolvedValueOnce(json(envelope, 404));
    const notFound = await failure(fetchApi('/pages/x', PageResponseSchema));
    expect(notFound).toMatchObject({
      kind: 'not_found',
      status: 404,
      requestId: 'req-1',
    });

    fetchMock.mockResolvedValueOnce(json(envelope, 500));
    const broken = await failure(fetchApi('/pages/x', PageResponseSchema));
    expect(broken).toMatchObject({ kind: 'unavailable', status: 500 });
  });

  it('rejects malformed JSON and malformed known sections', async () => {
    fetchMock.mockResolvedValueOnce(new Response('<html>oops</html>'));
    expect(
      (await failure(fetchApi('/pages/home', PageResponseSchema))).kind,
    ).toBe('invalid_response');

    const headless = { ...page.data.sections[0]!, heading: undefined };
    fetchMock.mockResolvedValueOnce(
      json({ data: { ...page.data, sections: [headless] } }),
    );
    expect(
      (await failure(fetchApi('/pages/home', PageResponseSchema))).kind,
    ).toBe('invalid_response');
  });

  it(`aborts after ${TIMEOUT_MS}ms`, async () => {
    vi.useFakeTimers();
    fetchMock.mockImplementation(
      (_url, init) =>
        new Promise((_resolve, reject) => {
          init?.signal?.addEventListener('abort', () =>
            reject(new DOMException('Aborted', 'AbortError')),
          );
        }),
    );

    const pending = failure(fetchApi('/site', PageResponseSchema));
    await vi.advanceTimersByTimeAsync(TIMEOUT_MS);
    const error = await pending;

    expect(error.kind).toBe('unavailable');
    expect(error.message).toContain('timed out');
  });

  it('refuses bodies over 1 MiB, declared or streamed', async () => {
    fetchMock.mockResolvedValueOnce(
      json(page, 200, { 'content-length': String(MAX_BYTES + 1) }),
    );
    expect((await failure(fetchApi('/site', PageResponseSchema))).kind).toBe(
      'invalid_response',
    );

    fetchMock.mockResolvedValueOnce(new Response('x'.repeat(MAX_BYTES + 1)));
    expect(
      (await failure(fetchApi('/site', PageResponseSchema))).message,
    ).toContain('exceeds');
  });

  it('treats a refused redirect as unavailable', async () => {
    fetchMock.mockRejectedValue(
      new TypeError('fetch failed: redirect mode is set to error'),
    );

    expect((await failure(fetchApi('/site', PageResponseSchema))).kind).toBe(
      'unavailable',
    );
  });

  it.each([
    '/pages/../../wp-admin',
    '//evil.example/x',
    '/pages/a?x=1',
    'pages/home',
    '/pages/%2e%2e',
  ])('refuses unsafe path %s without a request', async (path) => {
    await failure(fetchApi(path, PageResponseSchema));
    expect(fetchMock).not.toHaveBeenCalled();
  });
});

describe('getServerConfig', () => {
  it('requires an HTTPS origin', () => {
    expect(() => getServerConfig({ NODE_ENV: 'development' })).toThrow(
      ConfigError,
    );
    expect(() =>
      getServerConfig({
        NODE_ENV: 'development',
        WP_API_ORIGIN: 'http://cms.example.com',
      }),
    ).toThrow(ConfigError);
    expect(() =>
      getServerConfig({
        NODE_ENV: 'development',
        WP_API_ORIGIN: 'https://cms.example.com/wp',
      }),
    ).toThrow(ConfigError);
  });

  it('refuses fixture mode in production', () => {
    expect(() =>
      getServerConfig({ NODE_ENV: 'production', WSH_FIXTURE_MODE: 'true' }),
    ).toThrow(ConfigError);
    expect(
      getServerConfig({ NODE_ENV: 'development', WSH_FIXTURE_MODE: 'true' })
        .fixtureMode,
    ).toBe(true);
  });
});
