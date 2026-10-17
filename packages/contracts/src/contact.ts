import { z } from 'zod';

/** Plain single-line text: no control characters (blocks header injection). */
const plain = (max: number) =>
  z
    .string()
    .trim()
    .min(1, 'required')
    .max(max, 'too_long')
    .refine((value) => !/[\u0000-\u001f\u007f]/.test(value), 'invalid');

/**
 * What WordPress accepts at POST /websage/v1/contact (sent only by Next.js).
 * Field error codes are stable so the form can show translated messages.
 */
export const ContactInputSchema = z.strictObject({
  name: plain(120),
  email: z.string().trim().max(254, 'too_long').pipe(z.email('invalid')),
  message: z
    .string()
    .trim()
    .min(1, 'required')
    .max(5_000, 'too_long')
    .refine(
      (value) => !/[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/.test(value),
      'invalid',
    ),
  /** Honeypot: humans leave it empty; filled submissions are accepted silently. */
  website: z.string().max(200, 'too_long'),
  consent: z.literal(true, { error: 'required' }),
  idempotencyKey: z.uuid('invalid'),
});

/** What the browser sends to POST /api/contact. */
export const ContactFormSchema = ContactInputSchema.extend({
  turnstileToken: z.string().max(4_096).optional(),
});

export const ContactFieldSchema = z.enum([
  'name',
  'email',
  'message',
  'website',
  'consent',
  'idempotencyKey',
  'turnstileToken',
]);

export const ContactFieldErrorCodeSchema = z.enum([
  'required',
  'too_long',
  'invalid',
]);

export const ContactAcceptedSchema = z.strictObject({
  data: z.strictObject({ accepted: z.literal(true) }),
});

/** 422 body: the shared error envelope plus field codes. */
export const ContactValidationErrorSchema = z.strictObject({
  error: z.strictObject({
    code: z.literal('validation_failed'),
    message: z.string(),
    requestId: z.string(),
    fields: z.partialRecord(ContactFieldSchema, ContactFieldErrorCodeSchema),
  }),
});

export type ContactInput = z.infer<typeof ContactInputSchema>;
export type ContactForm = z.infer<typeof ContactFormSchema>;
export type ContactField = z.infer<typeof ContactFieldSchema>;
export type ContactFieldErrors = Partial<
  Record<ContactField, z.infer<typeof ContactFieldErrorCodeSchema>>
>;

/** Field codes from a failed parse, first issue per field. */
export function contactFieldErrors(error: z.ZodError): ContactFieldErrors {
  const fields: ContactFieldErrors = {};
  for (const issue of error.issues) {
    const field = ContactFieldSchema.safeParse(issue.path[0]);
    if (!field.success || fields[field.data]) continue;
    const code = ContactFieldErrorCodeSchema.safeParse(issue.message);
    fields[field.data] = code.success
      ? code.data
      : issue.code === 'too_big'
        ? 'too_long'
        : issue.code === 'too_small' || issue.code === 'invalid_type'
          ? 'required'
          : 'invalid';
  }
  return fields;
}
