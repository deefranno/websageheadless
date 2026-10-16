import type { ContactSection } from '@websage/contracts';
import { RichText } from '@/components/ui/RichText';
import { ContactForm } from './ContactForm';
import { Section, SectionHeading } from './shared';

export function Contact({
  section,
  isFirst,
}: {
  section: ContactSection;
  isFirst: boolean;
}) {
  return (
    <Section
      theme={section.theme}
      name="contact"
      label={section.heading || 'Contact'}
    >
      <div className="mx-auto max-w-2xl">
        <SectionHeading isFirst={isFirst}>{section.heading}</SectionHeading>
        <RichText
          html={section.body}
          className="mt-4 text-lg [&_a]:underline"
        />
        <ContactForm
          submitLabel={section.submitLabel}
          successMessage={section.successMessage}
          turnstileSiteKey={process.env.NEXT_PUBLIC_TURNSTILE_SITE_KEY}
        />
      </div>
    </Section>
  );
}
