import 'server-only';
import { getServerConfig } from '@/lib/config';
import { sanitizeRichText } from '@/lib/security/sanitize-html';

interface RichTextProps {
  html: string;
  className?: string;
}

/**
 * The only component allowed to inject HTML. Every value passes through the
 * server-side allowlist sanitizer first.
 */
export function RichText({ html, className }: RichTextProps) {
  if (!html) return null;
  const safe = sanitizeRichText(html, getServerConfig().cmsOrigin);
  return (
    <div className={className} dangerouslySetInnerHTML={{ __html: safe }} />
  );
}
