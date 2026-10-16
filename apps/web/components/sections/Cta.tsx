import type { CtaSection } from '@websage/contracts';
import { RichText } from '@/components/ui/RichText';
import { SmartLink } from '@/components/ui/SmartLink';
import { buttonClasses, Section, SectionHeading } from './shared';

export function Cta({
  section,
  isFirst,
}: {
  section: CtaSection;
  isFirst: boolean;
}) {
  return (
    <Section theme={section.theme} name="cta" label={section.heading}>
      <div className="mx-auto max-w-3xl text-center">
        <SectionHeading isFirst={isFirst}>{section.heading}</SectionHeading>
        <RichText
          html={section.body}
          className="mt-4 text-lg [&_a]:underline"
        />
        <div className="mt-8">
          <SmartLink
            href={section.link.href}
            newTab={section.link.newTab}
            className={buttonClasses(section.theme)}
          >
            {section.link.label}
          </SmartLink>
        </div>
      </div>
    </Section>
  );
}
