import type { ComponentType, ReactNode } from 'react';
import type { HeroSection, Page } from '@websage/contracts';
import { Hero } from '@/components/sections/Hero';

type Section = Page['sections'][number];

interface SectionProps<T> {
  section: T;
  isFirst: boolean;
}

/**
 * Section type → component. Adding a section means: a websage/* block, its
 * Zod schema in @websage/contracts, a component, and one entry here.
 */
const registry: { hero: ComponentType<SectionProps<HeroSection>> } = {
  hero: Hero,
};

function isKnown(section: Section): section is HeroSection {
  return section.type in registry;
}

/**
 * Renders one validated section. Unknown types (newer plugin than frontend)
 * are skipped in production and flagged in development. Malformed known
 * types never reach here: contract validation rejects the whole response.
 */
export function renderSection(section: Section, index: number): ReactNode {
  if (isKnown(section)) {
    const Component = registry[section.type];
    return (
      <Component key={section.key} section={section} isFirst={index === 0} />
    );
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
