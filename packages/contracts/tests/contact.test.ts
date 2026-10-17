import { describe, expect, it } from 'vitest';
import input from '../fixtures/contact-input.json';
import validationError from '../fixtures/contact-validation-error.json';
import * as c from '../src/index';

const errorsFor = (change: Record<string, unknown>) => {
  const result = c.ContactInputSchema.safeParse({ ...input, ...change });
  return result.success ? {} : c.contactFieldErrors(result.error);
};

describe('contact contracts', () => {
  it('accepts the fixture and the 422 example', () => {
    expect(c.ContactInputSchema.parse(input)).toEqual(input);
    expect(c.ContactValidationErrorSchema.parse(validationError)).toEqual(
      validationError,
    );
  });

  it.each([
    [{ name: '' }, { name: 'required' }],
    [{ name: 'x'.repeat(121) }, { name: 'too_long' }],
    [{ name: 'Eve\r\nBcc: victim@example.com' }, { name: 'invalid' }],
    [{ email: 'nope' }, { email: 'invalid' }],
    [{ email: `${'a'.repeat(250)}@x.io` }, { email: 'too_long' }],
    [{ message: '   ' }, { message: 'required' }],
    [{ message: 'x'.repeat(5001) }, { message: 'too_long' }],
    [{ consent: false }, { consent: 'required' }],
    [{ idempotencyKey: '123' }, { idempotencyKey: 'invalid' }],
  ])('%j gives %j', (change, expected) => {
    expect(errorsFor(change)).toEqual(expected);
  });

  it('allows line breaks in the message only', () => {
    expect(
      c.ContactInputSchema.safeParse({
        ...input,
        message: 'Line one\nLine two',
      }).success,
    ).toBe(true);
  });

  it('rejects unknown properties', () => {
    expect(
      c.ContactInputSchema.safeParse({ ...input, cc: 'x@example.com' }).success,
    ).toBe(false);
  });

  it('lets the browser add only a Turnstile token', () => {
    expect(
      c.ContactFormSchema.safeParse({ ...input, turnstileToken: 'abc' })
        .success,
    ).toBe(true);
    expect(c.ContactFormSchema.safeParse({ ...input, extra: 1 }).success).toBe(
      false,
    );
  });
});
