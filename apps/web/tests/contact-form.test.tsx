import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ContactForm } from '@/components/sections/ContactForm';

const fetchMock = vi.fn<typeof fetch>();

afterEach(() => {
  vi.unstubAllGlobals();
  fetchMock.mockReset();
});

function setup() {
  vi.stubGlobal('fetch', fetchMock);
  render(
    <ContactForm
      submitLabel="Send message"
      successMessage="Thanks, we will reply soon."
    />,
  );
  return userEvent.setup();
}

async function fillValid(user: ReturnType<typeof userEvent.setup>) {
  await user.type(screen.getByLabelText('Name'), 'A Visitor');
  await user.type(screen.getByLabelText('Email'), 'visitor@example.com');
  await user.type(screen.getByLabelText('Message'), 'Please contact me.');
  await user.click(screen.getByLabelText(/I agree/));
}

describe('ContactForm', () => {
  it('shows field errors, links them and moves focus to the summary without sending', async () => {
    const user = setup();
    await user.type(screen.getByLabelText('Email'), 'nope');
    await user.click(screen.getByRole('button', { name: 'Send message' }));

    expect(fetchMock).not.toHaveBeenCalled();
    const email = screen.getByLabelText('Email');
    expect(email).toHaveAttribute('aria-invalid', 'true');
    expect(email).toHaveAccessibleDescription(/valid email address/);
    expect(screen.getByLabelText('Name')).toHaveAccessibleDescription(
      'Please enter your name.',
    );
    await waitFor(() =>
      expect(
        screen.getByText(/There are problems/).parentElement,
      ).toHaveFocus(),
    );
    expect(screen.getByRole('status')).toHaveTextContent(
      'Please correct the highlighted fields.',
    );
  });

  it('sends a valid message, announces success and clears the form', async () => {
    const user = setup();
    fetchMock.mockResolvedValue(Response.json({ data: { accepted: true } }));
    await fillValid(user);
    await user.click(screen.getByRole('button', { name: 'Send message' }));

    await waitFor(() =>
      expect(screen.getByRole('status')).toHaveTextContent(
        'Thanks, we will reply soon.',
      ),
    );
    const [url, init] = fetchMock.mock.calls[0]!;
    expect(url).toBe('/api/contact');
    const body = JSON.parse(String(init?.body));
    expect(body).toMatchObject({
      name: 'A Visitor',
      email: 'visitor@example.com',
      consent: true,
      website: '',
    });
    expect(body.idempotencyKey).toMatch(/^[0-9a-f-]{36}$/);
    expect(screen.getByLabelText('Name')).toHaveValue('');
  });

  it('reuses the idempotency key when retrying the same message', async () => {
    const user = setup();
    fetchMock.mockRejectedValueOnce(new TypeError('offline'));
    fetchMock.mockResolvedValueOnce(
      Response.json({ data: { accepted: true } }),
    );
    await fillValid(user);
    await user.click(screen.getByRole('button', { name: 'Send message' }));
    await waitFor(() =>
      expect(screen.getByRole('status')).toHaveTextContent(/could not be sent/),
    );
    expect(screen.getByLabelText('Message')).toHaveValue('Please contact me.');
    await user.click(screen.getByRole('button', { name: 'Send message' }));

    const keys = fetchMock.mock.calls.map(
      ([, init]) => JSON.parse(String(init?.body)).idempotencyKey,
    );
    expect(keys[0]).toBe(keys[1]);
  });

  it('explains rate limiting and server-side field errors', async () => {
    const user = setup();
    fetchMock.mockResolvedValueOnce(
      Response.json(
        { error: { code: 'rate_limited', retryAfter: 600 } },
        { status: 429 },
      ),
    );
    await fillValid(user);
    await user.click(screen.getByRole('button', { name: 'Send message' }));
    await waitFor(() =>
      expect(screen.getByRole('status')).toHaveTextContent('about 10 minutes'),
    );

    fetchMock.mockResolvedValueOnce(
      Response.json(
        { error: { code: 'validation_failed', fields: { email: 'invalid' } } },
        { status: 422 },
      ),
    );
    fireEvent.submit(
      screen.getByRole('button', { name: 'Send message' }).closest('form')!,
    );
    await waitFor(() =>
      expect(screen.getByLabelText('Email')).toHaveAttribute(
        'aria-invalid',
        'true',
      ),
    );
  });

  it('hides the honeypot from people and assistive technology', () => {
    setup();
    const honeypot = document.querySelector<HTMLInputElement>(
      'input[name="website"]',
    )!;
    expect(honeypot.tabIndex).toBe(-1);
    expect(honeypot.closest('[aria-hidden="true"]')).not.toBeNull();
  });
});
