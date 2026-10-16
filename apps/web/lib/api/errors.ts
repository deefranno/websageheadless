export type ApiErrorKind =
  /** WordPress confirmed the resource does not exist or is not public. */
  | 'not_found'
  /** Network failure, timeout, 5xx, or misconfiguration. */
  | 'unavailable'
  /** WordPress answered with data that violates the contract. */
  | 'invalid_response';

/**
 * A failed CMS read. Messages are descriptive for server logs; error
 * boundaries show users a generic message instead.
 */
export class ApiError extends Error {
  override name = 'ApiError';

  constructor(
    readonly kind: ApiErrorKind,
    message: string,
    readonly status?: number,
    readonly requestId?: string,
  ) {
    super(message);
  }
}

export function isNotFound(error: unknown): boolean {
  return error instanceof ApiError && error.kind === 'not_found';
}
