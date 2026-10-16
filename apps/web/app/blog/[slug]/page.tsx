import type { Post } from '@websage/contracts';
import { notFound } from 'next/navigation';
import { connection } from 'next/server';
import { PreviewBanner } from '@/components/preview/PreviewBanner';
import { getPost } from '@/lib/api/fetchers';
import { previewFor } from '@/lib/preview/session';
import { renderSection } from '@/lib/sections/registry';

/**
 * Minimal post view (title, date, content) so posts can be previewed now;
 * the full blog design arrives in Phase 4.
 */
export default async function BlogPostPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug: raw } = await params;
  await connection();
  let slug: string;
  try {
    slug = decodeURIComponent(raw);
  } catch {
    notFound();
  }

  const preview = await previewFor(`/blog/${slug}`);
  if (preview.status === 'active' && preview.preview.kind === 'post') {
    return (
      <>
        <PreviewBanner />
        <Article post={preview.preview.content} />
      </>
    );
  }
  const post = await getPost(slug);
  if (!post) notFound();
  return <Article post={post} />;
}

function Article({ post }: { post: Post }) {
  return (
    <article>
      <header className="mx-auto max-w-3xl px-4 pt-section sm:px-6">
        <h1 className="text-display font-bold">{post.title}</h1>
        <p className="mt-3 text-sm">
          <time dateTime={post.published}>
            {new Date(post.published).toLocaleDateString('en', {
              dateStyle: 'long',
              timeZone: 'UTC',
            })}
          </time>
        </p>
      </header>
      {post.sections.map((section, index) => renderSection(section, index + 1))}
    </article>
  );
}
