import Link from 'next/link';

export default function NotFound() {
  return (
    <div className="mx-auto max-w-6xl px-4 py-section sm:px-6">
      <h1 className="text-display font-bold">Page not found</h1>
      <p className="mt-4">
        The page you are looking for does not exist or has moved.
      </p>
      <Link
        href="/"
        className="mt-6 inline-flex min-h-11 items-center rounded-control bg-primary px-5 py-2.5 font-semibold text-on-primary"
      >
        Go to the home page
      </Link>
    </div>
  );
}
