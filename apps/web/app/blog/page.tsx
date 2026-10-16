import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Blog',
  robots: { index: false },
};

/**
 * Placeholder until the posts endpoint ships (Phase 4). It states plainly
 * that no posts are loaded rather than showing sample articles.
 */
export default function BlogIndexPage() {
  return (
    <div className="mx-auto max-w-6xl px-4 py-section sm:px-6">
      <h1 className="text-display font-bold">Blog</h1>
      <p className="mt-4">
        The blog is not connected yet. Posts published in WordPress will appear
        here once the blog integration is installed.
      </p>
    </div>
  );
}
