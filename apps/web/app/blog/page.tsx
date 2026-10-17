import type { Metadata } from 'next';
import Link from 'next/link';
import { connection } from 'next/server';
import { PostCard } from '@/components/blog/PostCard';
import { getPosts, getSite } from '@/lib/api/fetchers';
import { frontendOrigin } from '@/lib/seo/metadata';

type Props = {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
};

const PER_PAGE = 9;
const CATEGORY = /^[\p{Ll}\p{Lo}\p{N}_-]{1,200}$/u;

function readQuery(query: Record<string, string | string[] | undefined>) {
  const rawPage = typeof query.page === 'string' ? Number(query.page) : 1;
  const page =
    Number.isInteger(rawPage) && rawPage >= 1 && rawPage <= 10_000
      ? rawPage
      : 1;
  const category =
    typeof query.category === 'string' && CATEGORY.test(query.category)
      ? query.category
      : undefined;
  return { page, category };
}

function href(page: number, category?: string) {
  const params = new URLSearchParams();
  if (category) params.set('category', category);
  if (page > 1) params.set('page', String(page));
  const search = params.toString();
  return search ? `/blog?${search}` : '/blog';
}

export async function generateMetadata({
  searchParams,
}: Props): Promise<Metadata> {
  const { page, category } = readQuery(await searchParams);
  await connection();
  const origin = frontendOrigin();
  let siteName = '';
  try {
    siteName = (await getSite()).name;
  } catch {}
  return {
    title: page > 1 ? `Blog, page ${page}` : 'Blog',
    alternates: origin
      ? { canonical: `${origin}${href(page, category)}` }
      : undefined,
    openGraph: { title: 'Blog', siteName: siteName || undefined },
  };
}

export default async function BlogIndexPage({ searchParams }: Props) {
  const { page, category } = readQuery(await searchParams);
  await connection();
  const list = await getPosts(page, PER_PAGE, category);
  const active = list.categories.find((c) => c.slug === category);

  return (
    <div className="mx-auto max-w-6xl px-4 py-section sm:px-6">
      <h1 className="text-display font-bold">
        {active ? `Blog: ${active.name}` : 'Blog'}
      </h1>

      {list.categories.length > 0 ? (
        <nav aria-label="Categories" className="mt-6">
          <ul className="flex flex-wrap gap-2">
            {[{ slug: undefined, name: 'All posts' }, ...list.categories].map(
              (c) => (
                <li key={c.slug ?? 'all'}>
                  <Link
                    href={href(1, c.slug)}
                    aria-current={c.slug === category ? 'page' : undefined}
                    className="inline-flex min-h-11 items-center rounded-control border-2 border-border px-4 aria-[current=page]:bg-primary aria-[current=page]:text-on-primary"
                  >
                    {c.name}
                  </Link>
                </li>
              ),
            )}
          </ul>
        </nav>
      ) : null}

      {list.items.length === 0 ? (
        <p className="mt-10 text-lg">
          {page > 1 ? (
            <>
              There are no more posts.{' '}
              <Link href={href(1, category)} className="underline">
                Back to the first page
              </Link>
              .
            </>
          ) : category ? (
            'There are no posts in this category yet.'
          ) : (
            'No posts have been published yet.'
          )}
        </p>
      ) : (
        <ul className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {list.items.map((post) => (
            <li key={post.slug}>
              <PostCard post={post} />
            </li>
          ))}
        </ul>
      )}

      {list.pagination.totalPages > 1 ? (
        <nav
          aria-label="Pagination"
          className="mt-10 flex items-center justify-between gap-4"
        >
          {page > 1 ? (
            <Link
              href={href(page - 1, category)}
              rel="prev"
              className="inline-flex min-h-11 items-center underline"
            >
              ← Newer posts
            </Link>
          ) : (
            <span />
          )}
          <p>
            Page {Math.min(page, list.pagination.totalPages)} of{' '}
            {list.pagination.totalPages}
          </p>
          {page < list.pagination.totalPages ? (
            <Link
              href={href(page + 1, category)}
              rel="next"
              className="inline-flex min-h-11 items-center underline"
            >
              Older posts →
            </Link>
          ) : (
            <span />
          )}
        </nav>
      ) : null}
    </div>
  );
}
