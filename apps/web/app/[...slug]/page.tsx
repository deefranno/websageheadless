import { CmsPage, cmsPageMetadata } from '@/components/layout/CmsPage';

type Params = { params: Promise<{ slug: string[] }> };

function pathFrom(slug: string[]): string {
  // Segments arrive URL-encoded; getPage() rejects anything that is not a
  // valid page slug before contacting WordPress.
  const segments = slug.map((segment) => {
    try {
      return decodeURIComponent(segment);
    } catch {
      return '\u0000';
    }
  });
  return `/${segments.join('/')}`;
}

export async function generateMetadata({ params }: Params) {
  return cmsPageMetadata(pathFrom((await params).slug));
}

export default async function NestedPage({ params }: Params) {
  return <CmsPage path={pathFrom((await params).slug)} />;
}
