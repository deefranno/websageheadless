import { Montserrat, Sora } from 'next/font/google';
import type { Site } from '@websage/contracts';

/**
 * Fonts are compiled in at build time; WordPress selects among these IDs.
 * Adding a family means adding it here and to the contract enum, then rebuilding.
 */
export const sora = Sora({
  subsets: ['latin'],
  display: 'swap',
  variable: '--font-sora',
});

export const montserrat = Montserrat({
  subsets: ['latin'],
  display: 'swap',
  variable: '--font-montserrat',
});

export const fontVariables = `${sora.variable} ${montserrat.variable}`;

type Fonts = Site['branding']['fonts'];

const SYSTEM_STACK =
  'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';

const FAMILIES: Record<Fonts['heading'] | Fonts['body'], string> = {
  sora: 'var(--font-sora)',
  montserrat: 'var(--font-montserrat)',
  system: SYSTEM_STACK,
};

export function fontsToCss(fonts: Fonts): string {
  return `:root{--wsh-font-heading:${FAMILIES[fonts.heading]};--wsh-font-body:${FAMILIES[fonts.body]};}`;
}
