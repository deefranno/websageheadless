/**
 * Structured data. Values come from validated API data; "<" is escaped so
 * content can never close the script element.
 */
export function JsonLd({ data }: { data: Record<string, unknown> }) {
  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{
        __html: JSON.stringify({
          '@context': 'https://schema.org',
          ...data,
        }).replace(/</g, '\\u003c'),
      }}
    />
  );
}
