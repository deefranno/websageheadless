export default function Loading() {
  return (
    <div
      className="mx-auto max-w-6xl px-4 py-section sm:px-6"
      role="status"
      aria-live="polite"
    >
      <span className="sr-only">Loading…</span>
      <div className="h-12 w-2/3 rounded-control bg-muted" />
      <div className="mt-6 h-5 w-1/2 rounded-control bg-muted" />
    </div>
  );
}
