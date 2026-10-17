import Image from 'next/image';
import type { TextImageSection } from '@websage/contracts';
import { RichText } from '@/components/ui/RichText';
import { Section, SectionHeading } from './shared';

export function TextImage({
  section,
  isFirst,
}: {
  section: TextImageSection;
  isFirst: boolean;
}) {
  return (
    <Section theme={section.theme} name="text-image" label={section.heading}>
      <div className="grid items-center gap-10 md:grid-cols-2">
        <div className={section.position === 'left' ? 'md:order-2' : ''}>
          <SectionHeading isFirst={isFirst}>{section.heading}</SectionHeading>
          <RichText
            html={section.body}
            className="mt-5 max-w-prose space-y-4 text-lg [&_a]:underline [&_li]:ml-6 [&_ol]:list-decimal [&_ul]:list-disc"
          />
        </div>
        {section.image ? (
          <Image
            src={section.image.url}
            alt={section.image.alt}
            width={section.image.width}
            height={section.image.height}
            sizes="(min-width: 768px) 50vw, 100vw"
            className={`h-auto w-full rounded-panel shadow-panel ${section.position === 'left' ? 'md:order-1' : ''}`}
          />
        ) : null}
      </div>
    </Section>
  );
}
