import page from '@websage/contracts/fixtures/preview-page.json';
import { NextRequest } from 'next/server';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { openGrant, sealGrant } from '@/lib/preview/grant';
import {
  issuePreviewToken,
  verifyPreviewToken,
  type PreviewToken,
} from '@/lib/preview/token';

const draft = vi.hoisted(() => ({ enable: vi.fn(), disable: vi.fn() }));
const fetchers = vi.hoisted(() => ({ getPreview: vi.fn() }));
vi.mock('next/headers', () => ({ draftMode: async () => draft }));
vi.mock('@/lib/api/fetchers', () => fetchers);
const { GET } = await import('@/app/api/preview/route');
const exit = await import('@/app/api/preview/exit/route');
const { ApiError } = await import('@/lib/api/errors');

const SECRET = 'p'.repeat(40);
const now = () => Math.floor(Date.now() / 1000);
const payload = (overrides: Partial<PreviewToken> = {}): PreviewToken => ({
  v: 1,
  id: 42,
  parentId: 41,
  path: '/draft',
  iat: now(),
  exp: now() + 300,
  jti: 'a1b2',
  ...overrides,
});

describe('preview tokens', () => {
  it('round-trips and rejects tampering, expiry and unsafe paths', () => {
    const token = issuePreviewToken(payload(), SECRET);
    expect(verifyPreviewToken(token, SECRET)?.id).toBe(42);

    const [body, sig] = token.split('.');
    const forged = Buffer.from(JSON.stringify(payload({ id: 1 }))).toString(
      'base64url',
    );
    expect(verifyPreviewToken(`${forged}.${sig}`, SECRET)).toBeNull();
    expect(verifyPreviewToken(`${body}.${sig}x`, SECRET)).toBeNull();
    expect(verifyPreviewToken(token, 'q'.repeat(40))).toBeNull();
    expect(
      verifyPreviewToken(
        issuePreviewToken(
          payload({ iat: now() - 400, exp: now() - 100 }),
          SECRET,
        ),
        SECRET,
      ),
    ).toBeNull();
    expect(
      verifyPreviewToken(
        issuePreviewToken(payload({ exp: now() + 3600 }), SECRET),
        SECRET,
      ),
    ).toBeNull();
    for (const path of [
      '//evil.example',
      'https://evil.example',
      '/a?next=//evil',
      '/a\\b',
    ]) {
      expect(
        verifyPreviewToken(
          issuePreviewToken(payload({ path }), SECRET),
          SECRET,
        ),
      ).toBeNull();
    }
  });

  it('seals grants so they cannot be read or altered', () => {
    const token = issuePreviewToken(payload(), SECRET);
    const sealed = sealGrant(token, SECRET);
    expect(sealed).not.toContain(token.split('.')[0]);
    expect(openGrant(sealed, SECRET)?.payload.path).toBe('/draft');
    expect(openGrant(sealed.slice(0, -2) + 'AA', SECRET)).toBeNull();
    expect(openGrant(sealed, 'q'.repeat(40))).toBeNull();
    expect(openGrant(undefined, SECRET)).toBeNull();
  });
});

describe('GET /api/preview', () => {
  beforeEach(() => vi.stubEnv('WSH_PREVIEW_SECRET', SECRET));
  afterEach(() => {
    vi.unstubAllEnvs();
    vi.clearAllMocks();
  });

  const call = (token: string) =>
    GET(
      new NextRequest(
        `https://www.example.com/api/preview?token=${encodeURIComponent(token)}`,
      ),
    );

  it('enables Draft Mode, stores the sealed grant and redirects to the token path', async () => {
    fetchers.getPreview.mockResolvedValue(page.data);
    const token = issuePreviewToken(payload(), SECRET);

    const response = await call(token);

    expect(response.status).toBe(303);
    expect(response.headers.get('location')).toBe(
      'https://www.example.com/draft',
    );
    expect(response.headers.get('cache-control')).toBe('private, no-store');
    expect(response.headers.get('referrer-policy')).toBe('no-referrer');
    expect(draft.enable).toHaveBeenCalled();
    expect(fetchers.getPreview).toHaveBeenCalledWith(42, token);
    const cookie = response.headers.get('set-cookie') ?? '';
    expect(cookie).toMatch(/^wsh_preview=/);
    expect(cookie).toMatch(/HttpOnly/i);
    expect(cookie).toMatch(/SameSite=lax/i);
    expect(cookie).not.toContain(token);
  });

  it('refuses invalid tokens without enabling Draft Mode', async () => {
    const response = await call('nope');
    expect(response.status).toBe(401);
    expect(draft.enable).not.toHaveBeenCalled();
  });

  it('reports drafts that no longer exist', async () => {
    fetchers.getPreview.mockRejectedValue(
      new ApiError('not_found', 'gone', 404),
    );
    expect((await call(issuePreviewToken(payload(), SECRET))).status).toBe(404);
    expect(draft.enable).not.toHaveBeenCalled();
  });
});

describe('POST /api/preview/exit', () => {
  it('requires a same-origin POST', async () => {
    const cross = await exit.POST(
      new NextRequest('https://www.example.com/api/preview/exit', {
        method: 'POST',
        headers: { origin: 'https://evil.example' },
      }),
    );
    expect(cross.status).toBe(403);
    expect(draft.disable).not.toHaveBeenCalled();

    const same = await exit.POST(
      new NextRequest('https://www.example.com/api/preview/exit', {
        method: 'POST',
        headers: { origin: 'https://www.example.com' },
      }),
    );
    expect(same.status).toBe(303);
    expect(draft.disable).toHaveBeenCalled();
    expect(same.headers.get('set-cookie')).toMatch(/wsh_preview=;/);
  });
});
