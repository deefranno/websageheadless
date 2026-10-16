import Image from 'next/image';
import type { HeroSection } from '@websage/contracts';
import { RichText } from '@/components/ui/RichText';
import { SmartLink } from '@/components/ui/SmartLink';

const THEMES: Record<HeroSection['theme'], string> = {
  default: 'bg-background text-foreground',
  muted: 'bg-muted text-on-muted',
  brand: 'bg-primary text-on-primary',
};

const BUTTON =
  'inline-flex min-h-11 items-center justify-center rounded-control px-5 py-2.5 font-semibold';

interface HeroProps {
  section: HeroSection;
  /** The first section on a page carries the page's only h1. */
  isFirst: boolean;
}

export function Hero({ section, isFirst }: HeroProps) {
  const Heading = isFirst ? 'h1' : 'h2';
  const inverted = section.theme === 'brand';

  return (
    <section
      className={`${THEMES[section.theme]} py-section`}
      data-section="hero"
    >
      <div className="mx-auto grid max-w-6xl items-center gap-10 px-4 sm:px-6 md:grid-cols-2">
        <div>
          {section.eyebrow ? (
            <p className="mb-3 text-sm font-semibold uppercase tracking-wide">
              {section.eyebrow}
            </p>
          ) : null}
          <Heading className="text-display font-bold">
            {section.heading}
          </Heading>
          <RichText
            html={section.body}
            className="mt-5 max-w-prose text-lg [&_a]:underline"
          />
          {section.primary || section.secondary ? (
            <div className="mt-8 flex flex-wrap gap-3">
              {section.primary ? (
                <SmartLink
                  href={section.primary.href}
                  newTab={section.primary.newTab}
                  className={`${BUTTON} ${inverted ? 'bg-on-primary text-primary' : 'bg-primary text-on-primary'}`}
                >
                  {section.primary.label}
                </SmartLink>
              ) : null}
              {section.secondary ? (
                <SmartLink
                  href={section.secondary.href}
                  newTab={section.secondary.newTab}
                  className={`${BUTTON} border-2 ${inverted ? 'border-on-primary' : 'border-primary'}`}
                >
                  {section.secondary.label}
                </SmartLink>
              ) : null}
            </div>
          ) : null}
        </div>
        {section.image ? (
          <Image
            src={section.image.url}
            alt={section.image.alt}
            width={section.image.width}
            height={section.image.height}
            priority={isFirst}
            sizes="(min-width: 768px) 50vw, 100vw"
            className="h-auto w-full rounded-panel shadow-panel"
          />
        ) : null}
      </div>
    </section>
  );
}
