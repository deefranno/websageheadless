import Image from 'next/image';
import Link from 'next/link';
import type { PostSummary } from '@websage/contracts';
import { formatDate } from './format';

export function PostCard({ post }: { post: PostSummary }) {
  return (
    <article className="flex h-full flex-col overflow-hidden rounded-panel border border-border/30 bg-background shadow-panel">
      {post.image ? (
        <Image
          src={post.image.url}
          alt={post.image.alt}
          width={post.image.width}
          height={post.image.height}
          sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw"
          className="aspect-[16/9] h-auto w-full object-cover"
        />
      ) : null}
      <div className="flex flex-1 flex-col p-6">
        <h2 className="text-xl font-semibold">
          <Link href={post.path} className="underline-offset-4 hover:underline">
            {post.title}
          </Link>
        </h2>
        <p className="mt-1 text-sm">
          <time dateTime={post.published}>{formatDate(post.published)}</time>
          {post.categories.length > 0
            ? ` · ${post.categories.map((c) => c.name).join(', ')}`
            : null}
        </p>
        {post.excerpt ? <p className="mt-3 flex-1">{post.excerpt}</p> : null}
      </div>
    </article>
  );
}
