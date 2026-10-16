import { CmsPage } from '@/components/layout/CmsPage';

export default async function NestedPage({
  params,
}: {
  params: Promise<{ slug: string[] }>;
}) {
  const { slug } = await params;
  // Segments arrive URL-encoded; getPage() rejects anything that is not a
  // valid page slug before contacting WordPress.
  const segments = slug.map((segment) => {
    try {
      return decodeURIComponent(segment);
    } catch {
      return '\u0000';
    }
  });
  return <CmsPage path={`/${segments.join('/')}`} />;
}
