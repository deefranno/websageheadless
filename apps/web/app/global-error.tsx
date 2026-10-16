'use client';

import './globals.css';

/** Last-resort boundary for failures in the root layout itself. */
export default function GlobalError({ reset }: { reset: () => void }) {
  return (
    <html lang="en">
      <body className="mx-auto max-w-2xl px-4 py-16">
        <h1 className="text-3xl font-bold">Something went wrong</h1>
        <p className="mt-4">Please try again in a moment.</p>
        <button
          type="button"
          onClick={reset}
          className="mt-6 min-h-11 rounded-control bg-primary px-5 py-2.5 font-semibold text-on-primary"
        >
          Try again
        </button>
      </body>
    </html>
  );
}
