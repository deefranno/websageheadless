import Image from 'next/image';
import type { FeatureGridSection } from '@websage/contracts';
import { RichText } from '@/components/ui/RichText';
import { SmartLink } from '@/components/ui/SmartLink';
import { Section, SectionHeading } from './shared';

// Literal class names so Tailwind can see them.
const COLUMNS: Record<FeatureGridSection['columns'], string> = {
  2: 'sm:grid-cols-2',
  3: 'sm:grid-cols-2 lg:grid-cols-3',
  4: 'sm:grid-cols-2 lg:grid-cols-4',
};

export function FeatureGrid({
  section,
  isFirst,
}: {
  section: FeatureGridSection;
  isFirst: boolean;
}) {
  const ItemHeading = isFirst || section.heading ? 'h3' : 'h2';
  return (
    <Section theme={section.theme} name="feature-grid" label={section.heading}>
      <SectionHeading isFirst={isFirst}>{section.heading}</SectionHeading>
      <RichText
        html={section.introduction}
        className="mt-4 max-w-prose text-lg [&_a]:underline"
      />
      {section.items.length > 0 ? (
        <ul className={`mt-10 grid gap-6 ${COLUMNS[section.columns]}`}>
          {section.items.map((item, index) => (
            <li
              key={`${item.title}-${index}`}
              className="flex flex-col rounded-panel border border-border/30 bg-background p-6 text-foreground shadow-panel"
            >
              {item.image ? (
                <Image
                  src={item.image.url}
                  alt={item.image.alt}
                  width={item.image.width}
                  height={item.image.height}
                  sizes="(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw"
                  className="mb-4 h-auto w-full rounded-control"
                />
              ) : null}
              <ItemHeading className="text-xl font-semibold">
                {item.title}
              </ItemHeading>
              <RichText
                html={item.body}
                className="mt-2 flex-1 [&_a]:underline"
              />
              {item.link ? (
                <SmartLink
                  href={item.link.href}
                  newTab={item.link.newTab}
                  className="mt-4 font-semibold underline underline-offset-4"
                >
                  {item.link.label}
                </SmartLink>
              ) : null}
            </li>
          ))}
        </ul>
      ) : null}
    </Section>
  );
}
