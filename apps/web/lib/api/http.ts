/*
 * Transport for websage/v1 without the `server-only` guard, so proxy.ts can
 * use it. Application code imports lib/api/client.ts instead.
 */
import { ApiErrorSchema } from '@websage/contracts';
import type { z } from 'zod';
import { getServerConfig } from '../config';
import { ApiError } from './errors';

export const API_BASE = '/wp-json/websage/v1';
export const TIMEOUT_MS = 5_000;
export const MAX_BYTES = 1_048_576;

/** Endpoint paths: fixed segments, no traversal, no query injection. */
const SAFE_PATH = /^\/[a-z]+(?:\/(?:[A-Za-z0-9_-]|%[0-9A-F]{2})+)*$/;

/**
 * Fetches one websage/v1 endpoint from the configured CMS and validates it.
 *
 * Uncached on purpose: callers cache only the validated result, so invalid or
 * failed responses never enter the persistent cache.
 */
export interface FetchOptions {
  /** Query parameters, appended with URLSearchParams encoding. */
  query?: Record<string, string | number | undefined>;
  /** Extra request headers (server-to-server credentials). */
  headers?: Record<string, string>;
}

export async function fetchApi<S extends z.ZodType>(
  path: string,
  schema: S,
  options: FetchOptions = {},
): Promise<z.output<S>> {
  if (!SAFE_PATH.test(path)) {
    throw new ApiError('invalid_response', `Refusing unsafe API path ${path}`);
  }
  const { cmsOrigin } = getServerConfig();
  const url = new URL(`${API_BASE}${path}`, cmsOrigin);
  for (const [key, value] of Object.entries(options.query ?? {})) {
    if (value !== undefined) url.searchParams.set(key, String(value));
  }
  if (url.origin !== cmsOrigin) {
    throw new ApiError('invalid_response', `API URL left the CMS origin`);
  }

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);
  let response: Response;
  let body: string;
  try {
    response = await fetch(url, {
      headers: { Accept: 'application/json', ...options.headers },
      redirect: 'error',
      cache: 'no-store',
      signal: controller.signal,
    });
    body = await readBounded(response);
  } catch (error) {
    if (error instanceof ApiError) throw error;
    const reason = controller.signal.aborted
      ? `timed out after ${TIMEOUT_MS}ms`
      : String(error);
    throw new ApiError('unavailable', `GET ${url.pathname} failed: ${reason}`);
  } finally {
    clearTimeout(timer);
  }

  const json = parseJson(body, url.pathname);

  if (!response.ok) {
    const envelope = ApiErrorSchema.safeParse(json);
    const requestId = envelope.success
      ? envelope.data.error.requestId
      : undefined;
    const kind = response.status === 404 ? 'not_found' : 'unavailable';
    throw new ApiError(
      kind,
      `GET ${url.pathname} returned ${response.status}`,
      response.status,
      requestId,
    );
  }

  const result = schema.safeParse(json);
  if (!result.success) {
    throw new ApiError(
      'invalid_response',
      `GET ${url.pathname} violated the contract: ${result.error.message}`,
      response.status,
    );
  }
  return result.data;
}

async function readBounded(response: Response): Promise<string> {
  const declared = Number(response.headers.get('content-length') ?? '0');
  if (declared > MAX_BYTES) {
    throw new ApiError(
      'invalid_response',
      `Response of ${declared} bytes exceeds ${MAX_BYTES}`,
    );
  }
  if (!response.body) return '';

  const reader = response.body.getReader();
  const chunks: Uint8Array[] = [];
  let total = 0;
  for (;;) {
    const { done, value } = await reader.read();
    if (done) break;
    total += value.byteLength;
    if (total > MAX_BYTES) {
      await reader.cancel();
      throw new ApiError(
        'invalid_response',
        `Response exceeds ${MAX_BYTES} bytes`,
      );
    }
    chunks.push(value);
  }
  return new TextDecoder().decode(Buffer.concat(chunks));
}

function parseJson(body: string, path: string): unknown {
  try {
    return JSON.parse(body);
  } catch {
    throw new ApiError('invalid_response', `GET ${path} returned invalid JSON`);
  }
}
