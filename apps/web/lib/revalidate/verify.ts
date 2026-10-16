import { createHmac, timingSafeEqual } from 'node:crypto';
import {
  RevalidationEventSchema,
  type RevalidationEvent,
} from '@websage/contracts';

export const MAX_BODY_BYTES = 65_536;
export const MAX_SKEW_SECONDS = 300;

export type VerifyResult =
  | { ok: true; event: RevalidationEvent }
  | { ok: false; status: 400 | 401 | 413; reason: string };

/**
 * Verifies a webhook from the plugin (Revalidation\Signer):
 * hex HMAC-SHA256 of `timestamp + "." + eventId + "." + rawBody`, within
 * ±300 s. Repeated valid deliveries are harmless: invalidation is idempotent.
 */
export function verifyWebhook(
  input: {
    body: string;
    timestamp: string | null;
    eventId: string | null;
    signature: string | null;
  },
  secret: string,
  now = Math.floor(Date.now() / 1000),
): VerifyResult {
  if (Buffer.byteLength(input.body) > MAX_BODY_BYTES) {
    return { ok: false, status: 413, reason: 'body too large' };
  }
  const { timestamp, eventId, signature } = input;
  if (
    !timestamp ||
    !/^\d{1,12}$/.test(timestamp) ||
    !eventId ||
    !/^[0-9a-f-]{36}$/i.test(eventId) ||
    !signature ||
    !/^[0-9a-f]{64}$/i.test(signature)
  ) {
    return {
      ok: false,
      status: 401,
      reason: 'missing or malformed signature headers',
    };
  }
  if (Math.abs(now - Number(timestamp)) > MAX_SKEW_SECONDS) {
    return { ok: false, status: 401, reason: 'timestamp outside window' };
  }
  const expected = createHmac('sha256', secret)
    .update(`${timestamp}.${eventId}.${input.body}`)
    .digest();
  const given = Buffer.from(signature, 'hex');
  if (!timingSafeEqual(given, expected)) {
    return { ok: false, status: 401, reason: 'bad signature' };
  }

  let json: unknown;
  try {
    json = JSON.parse(input.body);
  } catch {
    return { ok: false, status: 400, reason: 'invalid JSON' };
  }
  const parsed = RevalidationEventSchema.safeParse(json);
  if (!parsed.success) {
    return { ok: false, status: 400, reason: 'invalid event' };
  }
  return { ok: true, event: parsed.data };
}

/** Test helper mirroring the PHP signer. */
export function signWebhook(
  secret: string,
  timestamp: number,
  eventId: string,
  body: string,
): string {
  return createHmac('sha256', secret)
    .update(`${timestamp}.${eventId}.${body}`)
    .digest('hex');
}
