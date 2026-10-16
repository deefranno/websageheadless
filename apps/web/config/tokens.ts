import type { Site } from '@websage/contracts';

export type BrandTokens = Site['branding']['tokens'];

/**
 * Code defaults, used before WordPress settings load and as the fallback
 * documented in the rebranding checklist. Keep in sync with
 * SiteController::DEFAULT_TOKENS in the plugin.
 */
export const DEFAULT_TOKENS: BrandTokens = {
  primary: '#174F46',
  on_primary: '#FFFFFF',
  background: '#FFFFFF',
  foreground: '#17201E',
  muted: '#F2F5F4',
  on_muted: '#35443F',
  border: '#62736D',
  focus: '#126BDA',
};

const HEX = /^#[0-9a-fA-F]{6}$/;

/**
 * CSS custom properties for brand colours. Values are re-checked here so a
 * declaration can never break out of the style block, even if a caller
 * skipped contract validation.
 */
export function tokensToCss(tokens: BrandTokens): string {
  const declarations = Object.entries(tokens)
    .filter(([, value]) => HEX.test(value))
    .map(([name, value]) => `--wsh-${name.replace('_', '-')}:${value};`)
    .join('');
  return `:root{${declarations}}`;
}
