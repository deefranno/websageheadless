import 'server-only';
import type { Preview } from '@websage/contracts';
import { cookies, draftMode } from 'next/headers';
import { getPreview } from '../api/fetchers';
import { getSecret } from '../config';
import { GRANT_COOKIE, openGrant } from './grant';

export type PreviewState =
  | { status: 'off' }
  | { status: 'expired' }
  | { status: 'active'; preview: Preview };

/**
 * Preview content for this path, when Draft Mode is on and the encrypted
 * grant cookie is valid and bound to the same path. Draft Mode alone never
 * authorizes anything.
 */
export async function previewFor(path: string): Promise<PreviewState> {
  if (!(await draftMode()).isEnabled) return { status: 'off' };
  const secret = getSecret('WSH_PREVIEW_SECRET');
  const grant = secret
    ? openGrant((await cookies()).get(GRANT_COOKIE)?.value, secret)
    : null;
  if (!grant) return { status: 'expired' };
  if (grant.payload.path !== path) return { status: 'off' };
  try {
    return {
      status: 'active',
      preview: await getPreview(grant.payload.id, grant.token),
    };
  } catch (error) {
    console.error('websage: preview content unavailable', error);
    return { status: 'expired' };
  }
}
