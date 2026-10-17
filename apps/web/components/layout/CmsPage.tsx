import type { Section } from '@websage/contracts';
import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { connection } from 'next/server';
import { PreviewBanner } from '@/components/preview/PreviewBanner';
import { getPage, getSite } from '@/lib/api/fetchers';
import { seoMetadata } from '@/lib/seo/metadata';
import { previewFor } from '@/lib/preview/session';
import { JsonLd } from '@/components/seo/JsonLd';
import { renderSection } from '@/lib/sections/registry';
import { breadcrumbs, pageCrumbs } from '@/lib/seo/structured-data';

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
      <JsonLd
        data={breadcrumbs(
          pageCrumbs(page.path, page.title, page.seo.canonical),
        )}
      />
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

/** Metadata for a CMS page path (used by the route files). */
export async function cmsPageMetadata(path: string): Promise<Metadata> {
  await connection();
  try {
    const [preview, site] = await Promise.all([previewFor(path), getSite()]);
    // While previewing, the tab title and description follow the draft.
    const page =
      preview.status === 'active' && preview.preview.kind === 'page'
        ? preview.preview.content
        : await getPage(path);
    return page ? seoMetadata(page.seo, site.name) : {};
  } catch {
    return {};
  }
}
