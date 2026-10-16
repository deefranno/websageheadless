import type { CoreContentSection } from '@websage/contracts';
import { RichText } from '@/components/ui/RichText';

export function CoreContent({ section }: { section: CoreContentSection }) {
  return (
    <section
      data-section="core-content"
      className="mx-auto max-w-3xl px-4 py-10 sm:px-6 [&_a]:underline [&_blockquote]:border-l-4 [&_blockquote]:border-border [&_blockquote]:pl-4 [&_h2]:mt-8 [&_h2]:text-3xl [&_h2]:font-bold [&_h3]:mt-6 [&_h3]:text-2xl [&_h3]:font-semibold [&_img]:h-auto [&_img]:max-w-full [&_li]:ml-6 [&_ol]:list-decimal [&_p]:mt-4 [&_ul]:list-disc"
    >
      <RichText html={section.html} />
    </section>
  );
}
