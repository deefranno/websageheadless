import { CmsPage, cmsPageMetadata } from '@/components/layout/CmsPage';

export function generateMetadata() {
  return cmsPageMetadata('/');
}

export default function HomePage() {
  return <CmsPage path="/" />;
}
