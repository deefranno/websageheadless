import 'server-only';
import sanitizeHtml from 'sanitize-html';

const UPLOADS_PATH = '/wp-content/uploads/';

/**
 * Allowlist sanitizer for every HTML string that came from WordPress.
 * Same-CMS links become frontend paths; uploaded media keeps its CMS URL.
 */
export function sanitizeRichText(html: string, cmsOrigin: string): string {
  return sanitizeHtml(html, {
    allowedTags: [
      'p',
      'br',
      'strong',
      'em',
      'b',
      'i',
      'a',
      'ul',
      'ol',
      'li',
      'h2',
      'h3',
      'h4',
      'blockquote',
      'img',
    ],
    allowedAttributes: {
      a: ['href', 'target', 'rel'],
      img: ['src', 'alt', 'width', 'height'],
    },
    allowedSchemes: ['https', 'mailto', 'tel'],
    allowedSchemesByTag: { img: ['https'] },
    allowProtocolRelative: false,
    disallowedTagsMode: 'discard',
    transformTags: {
      a: (tagName, attribs) => {
        const href = rewriteHref(attribs.href, cmsOrigin);
        const next: sanitizeHtml.Attributes = {};
        if (href !== undefined) next.href = href;
        if (attribs.target === '_blank') {
          next.target = '_blank';
          next.rel = 'noopener noreferrer';
        }
        return { tagName, attribs: next };
      },
    },
  });
}

/**
 * Rewrites a link to the CMS into a frontend path. Returns undefined for
 * links that cannot be made safe, which drops the attribute.
 */
function rewriteHref(
  href: string | undefined,
  cmsOrigin: string,
): string | undefined {
  if (!href) return undefined;
  if (href.startsWith('/')) {
    return href.startsWith('//') || href.includes('\\') ? undefined : href;
  }
  let url: URL;
  try {
    url = new URL(href);
  } catch {
    return undefined;
  }
  if (url.username || url.password) return undefined;
  if (url.origin === cmsOrigin && !url.pathname.startsWith(UPLOADS_PATH)) {
    return `${url.pathname}${url.search}${url.hash}`;
  }
  // sanitize-html enforces allowedSchemes after this transform.
  return href;
}
