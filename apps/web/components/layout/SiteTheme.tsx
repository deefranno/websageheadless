import type { Site } from '@websage/contracts';
import { fontsToCss } from '@/config/fonts';
import { tokensToCss } from '@/config/tokens';

/**
 * Applies WordPress branding as CSS variables. Only contract-validated hex
 * colours and allowlisted font IDs are serialized.
 */
export function SiteTheme({ branding }: { branding: Site['branding'] }) {
  return (
    <style
      // Built from validated tokens only; see tokensToCss and fontsToCss.
      dangerouslySetInnerHTML={{
        __html: tokensToCss(branding.tokens) + fontsToCss(branding.fonts),
      }}
    />
  );
}
