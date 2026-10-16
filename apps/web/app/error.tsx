'use client';

/**
 * Generic failure UI. Details stay in server logs; production builds only
 * expose an opaque digest to the browser.
 */
export default function RouteError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  return (
    <div className="mx-auto max-w-6xl px-4 py-section sm:px-6" role="alert">
      <h1 className="text-3xl font-bold">
        This page is temporarily unavailable
      </h1>
      <p className="mt-4">Please try again in a moment.</p>
      {error.digest ? (
        <p className="mt-2 text-sm text-on-muted">Reference: {error.digest}</p>
      ) : null}
      <button
        type="button"
        onClick={reset}
        className="mt-6 min-h-11 rounded-control bg-primary px-5 py-2.5 font-semibold text-on-primary"
      >
        Try again
      </button>
    </div>
  );
}
