import Image from 'next/image';
import type { TestimonialsSection } from '@websage/contracts';
import { Section, SectionHeading } from './shared';

export function Testimonials({
  section,
  isFirst,
}: {
  section: TestimonialsSection;
  isFirst: boolean;
}) {
  if (section.items.length === 0) return null;
  return (
    <Section
      theme={section.theme}
      name="testimonials"
      label={section.heading || 'Testimonials'}
    >
      <SectionHeading isFirst={isFirst}>{section.heading}</SectionHeading>
      <ul className="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        {section.items.map((item, index) => (
          <li key={`${item.author}-${index}`}>
            <figure className="flex h-full flex-col rounded-panel border border-border/30 bg-background p-6 text-foreground shadow-panel">
              <blockquote className="flex-1 text-lg">
                <p>“{item.quote}”</p>
              </blockquote>
              <figcaption className="mt-6 flex items-center gap-3">
                {item.image ? (
                  <Image
                    src={item.image.url}
                    alt=""
                    width={48}
                    height={48}
                    className="size-12 rounded-full object-cover"
                  />
                ) : null}
                <span>
                  <span className="block font-semibold">{item.author}</span>
                  {item.role || item.company ? (
                    <span className="block text-sm">
                      {[item.role, item.company].filter(Boolean).join(', ')}
                    </span>
                  ) : null}
                </span>
              </figcaption>
            </figure>
          </li>
        ))}
      </ul>
    </Section>
  );
}
