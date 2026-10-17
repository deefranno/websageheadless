import type { ComponentType, ReactNode } from 'react';
import {
  isKnownSection,
  type KnownSection,
  type Section,
} from '@websage/contracts';
import { Contact } from '@/components/sections/Contact';
import { CoreContent } from '@/components/sections/CoreContent';
import { Cta } from '@/components/sections/Cta';
import { Faq } from '@/components/sections/Faq';
import { FeatureGrid } from '@/components/sections/FeatureGrid';
import { Hero } from '@/components/sections/Hero';
import { Testimonials } from '@/components/sections/Testimonials';
import { TextImage } from '@/components/sections/TextImage';

interface SectionProps<T> {
  section: T;
  isFirst: boolean;
}

type Registry = {
  [T in KnownSection['type']]?: ComponentType<
    SectionProps<Extract<KnownSection, { type: T }>>
  >;
};

/**
 * Section type → component. Adding a section means: a websage/* block, its
 * Zod schema in @websage/contracts, a component, and one entry here.
 * Types without an entry yet are treated like unknown types.
 */
const registry: Registry = {
  hero: Hero,
  'text-image': TextImage,
  'feature-grid': FeatureGrid,
  cta: Cta,
  testimonials: Testimonials,
  faq: Faq,
  contact: Contact,
  'core-content': CoreContent,
};

/**
 * Renders one validated section. Unknown or not-yet-rendered types are
 * skipped in production and flagged in development. Malformed known types
 * never reach here: contract validation rejects the whole response.
 */
export function renderSection(section: Section, index: number): ReactNode {
  if (isKnownSection(section)) {
    const Component = registry[section.type] as
      ComponentType<SectionProps<KnownSection>> | undefined;
    if (Component) {
      return (
        <Component key={section.key} section={section} isFirst={index === 0} />
      );
    }
  }

  if (process.env.NODE_ENV !== 'production') {
    return (
      <div
        key={section.key}
        role="note"
        className="mx-auto my-4 max-w-6xl rounded-control border-2 border-dashed border-border p-4 text-sm"
      >
        Unsupported section type “{section.type}” (schema v
        {section.schemaVersion}). Add it to lib/sections/registry.tsx.
      </div>
    );
  }
  return null;
}
