import type { Post } from '@websage/contracts';
import type { Metadata } from 'next';
import Image from 'next/image';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { connection } from 'next/server';
import { formatDate } from '@/components/blog/format';
import { PreviewBanner } from '@/components/preview/PreviewBanner';
import { JsonLd } from '@/components/seo/JsonLd';
import { getPost, getSite } from '@/lib/api/fetchers';
import { previewFor } from '@/lib/preview/session';
import { renderSection } from '@/lib/sections/registry';
import { seoMetadata } from '@/lib/seo/metadata';
import { article, breadcrumbs } from '@/lib/seo/structured-data';

type Props = { params: Promise<{ slug: string }> };

function slugFrom(raw: string): string | null {
  try {
    return decodeURIComponent(raw);
  } catch {
    return null;
  }
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const slug = slugFrom((await params).slug);
  await connection();
  if (!slug) return {};
  try {
    const [preview, site] = await Promise.all([
      previewFor(`/blog/${slug}`),
      getSite(),
    ]);
    // While previewing, the tab title and description follow the draft.
    const post =
      preview.status === 'active' && preview.preview.kind === 'post'
        ? preview.preview.content
        : await getPost(slug);
    if (!post) return {};
    const metadata = seoMetadata(post.seo, site.name);
    return {
      ...metadata,
      openGraph: {
        ...metadata.openGraph,
        type: 'article',
        publishedTime: post.published,
        modifiedTime: post.modified,
      },
    };
  } catch {
    return {};
  }
}

export default async function BlogPostPage({ params }: Props) {
  const slug = slugFrom((await params).slug);
  await connection();
  if (!slug) notFound();

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
  const site = await getSite();
  const origin = new URL(post.seo.canonical).origin;
  return (
    <>
      <JsonLd
        data={{
          '@graph': [
            article(post, site),
            breadcrumbs([
              ['Home', `${origin}/`],
              ['Blog', `${origin}/blog`],
              [post.title, post.seo.canonical],
            ]),
          ],
        }}
      />
      <Article post={post} />
    </>
  );
}

function Article({ post }: { post: Post }) {
  return (
    <article>
      <header className="mx-auto max-w-3xl px-4 pt-section sm:px-6">
        <nav aria-label="Breadcrumb" className="text-sm">
          <ol className="flex flex-wrap gap-2">
            <li>
              <Link href="/" className="underline">
                Home
              </Link>{' '}
              /
            </li>
            <li>
              <Link href="/blog" className="underline">
                Blog
              </Link>
            </li>
          </ol>
        </nav>
        <h1 className="mt-4 text-display font-bold">{post.title}</h1>
        <p className="mt-3 text-sm">
          <time dateTime={post.published}>{formatDate(post.published)}</time>
          {post.categories.length > 0 ? (
            <>
              {' · '}
              {post.categories.map((category, index) => (
                <span key={category.slug}>
                  {index > 0 ? ', ' : null}
                  <Link
                    href={`/blog?category=${encodeURIComponent(category.slug)}`}
                    className="underline"
                  >
                    {category.name}
                  </Link>
                </span>
              ))}
            </>
          ) : null}
        </p>
        {post.image ? (
          <Image
            src={post.image.url}
            alt={post.image.alt}
            width={post.image.width}
            height={post.image.height}
            priority
            sizes="(min-width: 768px) 768px, 100vw"
            className="mt-8 h-auto w-full rounded-panel"
          />
        ) : null}
      </header>
      {post.sections.map((section, index) => renderSection(section, index + 1))}
      <p className="mx-auto max-w-3xl px-4 pb-section sm:px-6">
        <Link href="/blog" className="underline">
          ← All posts
        </Link>
      </p>
    </article>
  );
}
