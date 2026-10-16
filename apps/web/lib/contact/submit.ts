import 'server-only';
import {
  ContactValidationErrorSchema,
  type ContactFieldErrors,
  type ContactInput,
} from '@websage/contracts';
import { getSecret, getServerConfig } from '../config';

export type SubmitResult =
  | { status: 'accepted' }
  | { status: 'invalid'; fields: ContactFieldErrors }
  | { status: 'rate_limited'; retryAfter: number }
  | { status: 'conflict' }
  | { status: 'unavailable' };

/** Forwards a validated submission to WordPress, server to server. */
export async function submitToWordPress(
  input: ContactInput,
  clientHash: string,
): Promise<SubmitResult> {
  const secret = getSecret('WSH_SERVER_API_SECRET');
  if (!secret) return { status: 'unavailable' };
  const { cmsOrigin, fixtureMode } = getServerConfig();
  if (fixtureMode) return { status: 'accepted' };

  let response: Response;
  try {
    response = await fetch(new URL('/wp-json/websage/v1/contact', cmsOrigin), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${secret}`,
        'X-WSH-Client-Hash': clientHash,
      },
      body: JSON.stringify(input),
      redirect: 'error',
      cache: 'no-store',
      signal: AbortSignal.timeout(8_000),
    });
  } catch (error) {
    console.error(
      'websage: contact submission failed to reach WordPress',
      error,
    );
    return { status: 'unavailable' };
  }

  if (response.status === 201) return { status: 'accepted' };
  if (response.status === 409) return { status: 'conflict' };
  if (response.status === 429) {
    const retryAfter = Number(response.headers.get('retry-after') ?? '900');
    return {
      status: 'rate_limited',
      retryAfter: Number.isFinite(retryAfter) ? retryAfter : 900,
    };
  }
  if (response.status === 422) {
    const body = ContactValidationErrorSchema.safeParse(
      await response.json().catch(() => null),
    );
    if (body.success)
      return { status: 'invalid', fields: body.data.error.fields };
  }
  console.error(
    `websage: WordPress rejected a contact submission (${response.status})`,
  );
  return { status: 'unavailable' };
}
