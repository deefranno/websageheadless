import { notFound } from 'next/navigation';

/**
 * Placeholder until the posts endpoint ships (Phase 4): no post can be
 * verified yet, so every slug is a 404 rather than invented content.
 */
export default async function BlogPostPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  await params;
  notFound();
}
