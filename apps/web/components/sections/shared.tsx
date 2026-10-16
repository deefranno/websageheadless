import type { ReactNode } from 'react';

export type Theme = 'default' | 'muted' | 'brand';

/** Colour treatment per theme; components never hard-code colours. */
export const THEME_CLASSES: Record<Theme, string> = {
  default: 'bg-background text-foreground',
  muted: 'bg-muted text-on-muted',
  brand: 'bg-primary text-on-primary',
};

/** Button styles that stay readable on every theme. */
export function buttonClasses(
  theme: Theme,
  variant: 'solid' | 'outline' = 'solid',
) {
  const base =
    'inline-flex min-h-11 items-center justify-center rounded-control px-5 py-2.5 font-semibold underline-offset-4 hover:underline';
  if (variant === 'outline') {
    return `${base} border-2 ${theme === 'brand' ? 'border-on-primary' : 'border-primary'}`;
  }
  return `${base} ${theme === 'brand' ? 'bg-on-primary text-primary' : 'bg-primary text-on-primary'}`;
}

interface SectionProps {
  theme: Theme;
  name: string;
  /** Accessible name for the region, usually the visible heading. */
  label?: string;
  children: ReactNode;
}

export function Section({ theme, name, label, children }: SectionProps) {
  return (
    <section
      data-section={name}
      aria-label={label || undefined}
      className={`${THEME_CLASSES[theme]} py-section`}
    >
      <div className="mx-auto max-w-6xl px-4 sm:px-6">{children}</div>
    </section>
  );
}

/** The first section on a page carries the page's only h1. */
export function SectionHeading({
  isFirst,
  children,
  className = '',
}: {
  isFirst: boolean;
  children: ReactNode;
  className?: string;
}) {
  if (!children) return null;
  const Tag = isFirst ? 'h1' : 'h2';
  const size = isFirst ? 'text-display' : 'text-3xl sm:text-4xl';
  return <Tag className={`${size} font-bold ${className}`}>{children}</Tag>;
}
