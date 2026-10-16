import type { FaqSection } from '@websage/contracts';
import { RichText } from '@/components/ui/RichText';
import { Section, SectionHeading } from './shared';

/** Native disclosure widgets: keyboard and screen-reader support built in. */
export function Faq({
  section,
  isFirst,
}: {
  section: FaqSection;
  isFirst: boolean;
}) {
  if (section.items.length === 0) return null;
  return (
    <Section
      theme={section.theme}
      name="faq"
      label={section.heading || 'Frequently asked questions'}
    >
      <div className="mx-auto max-w-3xl">
        <SectionHeading isFirst={isFirst}>{section.heading}</SectionHeading>
        <div className="mt-8 divide-y divide-border/40 border-y border-border/40">
          {section.items.map((item, index) => (
            <details key={`${item.question}-${index}`} className="group py-4">
              <summary className="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 text-lg font-semibold [&::-webkit-details-marker]:hidden">
                {item.question}
                <span
                  aria-hidden="true"
                  className="text-2xl transition-transform group-open:rotate-45"
                >
                  +
                </span>
              </summary>
              <RichText
                html={item.answer}
                className="mt-3 max-w-prose [&_a]:underline"
              />
            </details>
          ))}
        </div>
      </div>
    </Section>
  );
}
