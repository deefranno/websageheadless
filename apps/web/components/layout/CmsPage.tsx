import type { Section } from '@websage/contracts';
import { notFound } from 'next/navigation';
import { connection } from 'next/server';
import { PreviewBanner } from '@/components/preview/PreviewBanner';
import { getPage } from '@/lib/api/fetchers';
import { previewFor } from '@/lib/preview/session';
import { renderSection } from '@/lib/sections/registry';

/**
 * Renders a CMS page by frontend path, or its draft while previewing. Only
 * a confirmed 404 from WordPress becomes notFound(); outages propagate to
 * the error boundary. Unknown paths are 404'd earlier by proxy.ts.
 */
export async function CmsPage({ path }: { path: string }) {
  await connection();
  const preview = await previewFor(path);
  if (preview.status === 'active' && preview.preview.kind === 'page') {
    const draft = preview.preview.content;
    return (
      <>
        <PreviewBanner />
        <Sections title={draft.title} sections={draft.sections} />
      </>
    );
  }

  const page = await getPage(path);
  if (!page) notFound();
  return (
    <>
      {preview.status === 'expired' ? <PreviewBanner expired /> : null}
      <Sections title={page.title} sections={page.sections} />
    </>
  );
}

function Sections({ title, sections }: { title: string; sections: Section[] }) {
  if (sections.length === 0) {
    return (
      <div className="mx-auto max-w-6xl px-4 py-section sm:px-6">
        <h1 className="text-display font-bold">{title}</h1>
        <p className="mt-4">This page has no content yet.</p>
      </div>
    );
  }
  return <>{sections.map(renderSection)}</>;
}
