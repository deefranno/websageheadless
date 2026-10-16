import { notFound } from 'next/navigation';
import { connection } from 'next/server';
import { getPage } from '@/lib/api/fetchers';
import { isNotFound } from '@/lib/api/errors';
import { renderSection } from '@/lib/sections/registry';

/**
 * Renders a CMS page by frontend path. Only a confirmed 404 from WordPress
 * becomes notFound(); outages propagate to the error boundary.
 */
export async function CmsPage({ path }: { path: string }) {
  await connection();
  let page;
  try {
    page = await getPage(path);
  } catch (error) {
    if (isNotFound(error)) notFound();
    throw error;
  }

  if (page.sections.length === 0) {
    return (
      <div className="mx-auto max-w-6xl px-4 py-section sm:px-6">
        <h1 className="text-display font-bold">{page.title}</h1>
        <p className="mt-4">This page has no content yet.</p>
      </div>
    );
  }
  return <>{page.sections.map(renderSection)}</>;
}
