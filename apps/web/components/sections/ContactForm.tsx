'use client';

import {
  ContactFormSchema,
  contactFieldErrors,
  type ContactField,
  type ContactFieldErrors,
} from '@websage/contracts/contact';
import Script from 'next/script';
import { useId, useRef, useState, type FormEvent } from 'react';

interface ContactFormProps {
  submitLabel: string;
  successMessage: string;
  turnstileSiteKey?: string;
}

type Status =
  | { kind: 'idle' }
  | { kind: 'submitting' }
  | { kind: 'success' }
  | { kind: 'error'; message: string };

const MESSAGES: Record<ContactField, Partial<Record<string, string>>> = {
  name: {
    required: 'Please enter your name.',
    too_long: 'Please use at most 120 characters.',
    invalid: 'Please enter your name on one line.',
  },
  email: {
    required: 'Please enter your email address.',
    too_long: 'This email address is too long.',
    invalid: 'Please enter a valid email address, like name@example.com.',
  },
  message: {
    required: 'Please enter a message.',
    too_long: 'Please keep your message under 5,000 characters.',
    invalid: 'Your message contains characters we cannot accept.',
  },
  website: {},
  consent: { required: 'Please confirm we may use your details to reply.' },
  idempotencyKey: { invalid: 'Please reload the page and try again.' },
  turnstileToken: {
    invalid: 'Please complete the verification.',
    required: 'Please complete the verification.',
  },
};

const FIELD_ORDER: ContactField[] = [
  'name',
  'email',
  'message',
  'consent',
  'turnstileToken',
];

function messageFor(field: ContactField, code: string | undefined) {
  return code
    ? (MESSAGES[field][code] ?? 'Please check this field.')
    : undefined;
}

/**
 * Accessible contact form. Posts to the same-origin /api/contact handler;
 * input is kept on failure, and the same idempotency key is reused when the
 * visitor retries the same message after a network error.
 */
export function ContactForm({
  submitLabel,
  successMessage,
  turnstileSiteKey,
}: ContactFormProps) {
  const id = useId();
  const formRef = useRef<HTMLFormElement>(null);
  const summaryRef = useRef<HTMLDivElement>(null);
  const keyRef = useRef<{ key: string; fingerprint: string } | null>(null);
  const [errors, setErrors] = useState<ContactFieldErrors>({});
  const [status, setStatus] = useState<Status>({ kind: 'idle' });

  const fieldId = (name: string) => `${id}-${name}`;
  const errorId = (name: string) => `${id}-${name}-error`;

  function idempotencyKey(fingerprint: string) {
    if (!keyRef.current || keyRef.current.fingerprint !== fingerprint) {
      keyRef.current = { key: crypto.randomUUID(), fingerprint };
    }
    return keyRef.current.key;
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    const fields = {
      name: String(data.get('name') ?? ''),
      email: String(data.get('email') ?? ''),
      message: String(data.get('message') ?? ''),
      website: String(data.get('website') ?? ''),
    };
    const token = data.get('cf-turnstile-response');
    const payload = {
      ...fields,
      consent: data.get('consent') === 'on',
      idempotencyKey: idempotencyKey(JSON.stringify(fields)),
      ...(typeof token === 'string' && token ? { turnstileToken: token } : {}),
    };

    const local = ContactFormSchema.safeParse(payload);
    if (!local.success) {
      setErrors(contactFieldErrors(local.error));
      setStatus({
        kind: 'error',
        message: 'Please correct the highlighted fields.',
      });
      requestAnimationFrame(() => summaryRef.current?.focus());
      return;
    }

    setErrors({});
    setStatus({ kind: 'submitting' });
    try {
      const response = await fetch('/api/contact', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(local.data),
      });
      const body = (await response.json().catch(() => ({}))) as {
        error?: { fields?: ContactFieldErrors; retryAfter?: number };
      };
      if (response.ok) {
        keyRef.current = null;
        formRef.current?.reset();
        setStatus({ kind: 'success' });
        return;
      }
      if (response.status === 422 && body.error?.fields) {
        setErrors(body.error.fields);
        setStatus({
          kind: 'error',
          message: 'Please correct the highlighted fields.',
        });
        requestAnimationFrame(() => summaryRef.current?.focus());
        return;
      }
      if (response.status === 429) {
        const minutes = Math.max(
          1,
          Math.ceil((body.error?.retryAfter ?? 900) / 60),
        );
        setStatus({
          kind: 'error',
          message: `You have sent several messages in a short time. Please try again in about ${minutes} minute${minutes === 1 ? '' : 's'}.`,
        });
        return;
      }
      setStatus({
        kind: 'error',
        message:
          'Sorry, your message could not be sent. Please try again in a moment.',
      });
    } catch {
      setStatus({
        kind: 'error',
        message:
          'Sorry, your message could not be sent. Check your connection and try again.',
      });
    }
  }

  const invalid = FIELD_ORDER.filter((field) => errors[field]);
  const inputClass =
    'mt-1 block w-full rounded-control border-2 border-border bg-background px-3 py-2 text-foreground aria-[invalid=true]:border-[color:var(--color-focus)]';

  return (
    <form
      ref={formRef}
      noValidate
      onSubmit={onSubmit}
      className="mt-8 space-y-5"
      aria-describedby={`${id}-status`}
    >
      {invalid.length > 0 ? (
        <div
          ref={summaryRef}
          tabIndex={-1}
          className="rounded-control border-2 border-border bg-background p-4 text-foreground"
        >
          <p className="font-semibold">
            There {invalid.length === 1 ? 'is a problem' : 'are problems'} with
            your message:
          </p>
          <ul className="mt-2 list-disc pl-6">
            {invalid.map((field) => (
              <li key={field}>
                <a href={`#${fieldId(field)}`} className="underline">
                  {messageFor(field, errors[field])}
                </a>
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      <div>
        <label htmlFor={fieldId('name')} className="font-semibold">
          Name
        </label>
        <input
          id={fieldId('name')}
          name="name"
          type="text"
          autoComplete="name"
          required
          maxLength={120}
          aria-invalid={Boolean(errors.name)}
          aria-describedby={errors.name ? errorId('name') : undefined}
          className={inputClass}
        />
        {errors.name ? (
          <p id={errorId('name')} className="mt-1 text-sm font-semibold">
            {messageFor('name', errors.name)}
          </p>
        ) : null}
      </div>

      <div>
        <label htmlFor={fieldId('email')} className="font-semibold">
          Email
        </label>
        <input
          id={fieldId('email')}
          name="email"
          type="email"
          autoComplete="email"
          required
          maxLength={254}
          aria-invalid={Boolean(errors.email)}
          aria-describedby={errors.email ? errorId('email') : undefined}
          className={inputClass}
        />
        {errors.email ? (
          <p id={errorId('email')} className="mt-1 text-sm font-semibold">
            {messageFor('email', errors.email)}
          </p>
        ) : null}
      </div>

      <div>
        <label htmlFor={fieldId('message')} className="font-semibold">
          Message
        </label>
        <textarea
          id={fieldId('message')}
          name="message"
          required
          rows={6}
          maxLength={5000}
          aria-invalid={Boolean(errors.message)}
          aria-describedby={errors.message ? errorId('message') : undefined}
          className={inputClass}
        />
        {errors.message ? (
          <p id={errorId('message')} className="mt-1 text-sm font-semibold">
            {messageFor('message', errors.message)}
          </p>
        ) : null}
      </div>

      {/* Honeypot: hidden from people and assistive technology. */}
      <div
        aria-hidden="true"
        className="absolute -left-[10000px] h-px w-px overflow-hidden"
      >
        <label htmlFor={fieldId('website')}>Website</label>
        <input
          id={fieldId('website')}
          name="website"
          type="text"
          tabIndex={-1}
          autoComplete="off"
          defaultValue=""
        />
      </div>

      <div>
        <div className="flex items-start gap-3">
          <input
            id={fieldId('consent')}
            name="consent"
            type="checkbox"
            required
            aria-invalid={Boolean(errors.consent)}
            aria-describedby={errors.consent ? errorId('consent') : undefined}
            className="mt-1 size-5 shrink-0"
          />
          <label htmlFor={fieldId('consent')}>
            I agree that my details may be used to reply to my message.
          </label>
        </div>
        {errors.consent ? (
          <p id={errorId('consent')} className="mt-1 text-sm font-semibold">
            {messageFor('consent', errors.consent)}
          </p>
        ) : null}
      </div>

      {turnstileSiteKey ? (
        <div id={fieldId('turnstileToken')}>
          <Script
            src="https://challenges.cloudflare.com/turnstile/v0/api.js"
            strategy="lazyOnload"
          />
          <div className="cf-turnstile" data-sitekey={turnstileSiteKey} />
          {errors.turnstileToken ? (
            <p className="mt-1 text-sm font-semibold">
              {messageFor('turnstileToken', errors.turnstileToken)}
            </p>
          ) : null}
        </div>
      ) : null}

      <button
        type="submit"
        disabled={status.kind === 'submitting'}
        className="inline-flex min-h-11 items-center justify-center rounded-control bg-primary px-6 py-2.5 font-semibold text-on-primary disabled:opacity-70"
      >
        {status.kind === 'submitting' ? 'Sending…' : submitLabel}
      </button>

      <div
        id={`${id}-status`}
        role="status"
        aria-live="polite"
        className="min-h-6 font-semibold"
      >
        {status.kind === 'success'
          ? successMessage
          : status.kind === 'error'
            ? status.message
            : ''}
      </div>
    </form>
  );
}
