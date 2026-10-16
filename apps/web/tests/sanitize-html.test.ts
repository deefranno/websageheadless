import { describe, expect, it } from 'vitest';
import { sanitizeRichText } from '@/lib/security/sanitize-html';

const CMS = 'https://cms.example.com';
const clean = (html: string) => sanitizeRichText(html, CMS);

describe('sanitizeRichText', () => {
  it.each([
    ['<p>Hi<script>alert(1)</script></p>', '<p>Hi</p>'],
    ['<p onclick="steal()">Hi</p>', '<p>Hi</p>'],
    ['<p style="background:url(x)">Hi</p>', '<p>Hi</p>'],
    ['<iframe src="https://evil.example"></iframe><p>ok</p>', '<p>ok</p>'],
    ['<a href="javascript:alert(1)">x</a>', '<a>x</a>'],
    ['<a href="JaVaScRiPt:alert(1)">x</a>', '<a>x</a>'],
    ['<a href="data:text/html;base64,xx">x</a>', '<a>x</a>'],
    ['<a href="//evil.example/x">x</a>', '<a>x</a>'],
    ['<a href="https://user:pw@evil.example">x</a>', '<a>x</a>'],
    ['<img src="http://cms.example.com/a.jpg" alt="a">', '<img alt="a" />'],
    ['<svg><script>alert(1)</script></svg>', ''],
  ])('neutralizes %s', (input, expected) => {
    expect(clean(input)).toBe(expected);
  });

  it('keeps allowed text markup', () => {
    const html =
      '<h2>T</h2><p><strong>b</strong> <em>i</em></p><ul><li>a</li></ul><blockquote>q</blockquote>';
    expect(clean(html)).toBe(html);
  });

  it('rewrites same-CMS links to frontend paths and keeps external links', () => {
    expect(clean('<a href="https://cms.example.com/about/?x=1#t">a</a>')).toBe(
      '<a href="/about/?x=1#t">a</a>',
    );
    expect(clean('<a href="https://other.example/">a</a>')).toBe(
      '<a href="https://other.example/">a</a>',
    );
    expect(clean('<a href="mailto:hi@example.com">a</a>')).toBe(
      '<a href="mailto:hi@example.com">a</a>',
    );
  });

  it('keeps uploaded media on the CMS and hardens new-tab links', () => {
    expect(
      clean(
        '<a href="https://cms.example.com/wp-content/uploads/f.pdf" target="_blank" rel="opener">f</a>',
      ),
    ).toBe(
      '<a href="https://cms.example.com/wp-content/uploads/f.pdf" target="_blank" rel="noopener noreferrer">f</a>',
    );
    expect(
      clean(
        '<img src="https://cms.example.com/wp-content/uploads/a.jpg" alt="a" width="10" height="5">',
      ),
    ).toBe(
      '<img src="https://cms.example.com/wp-content/uploads/a.jpg" alt="a" width="10" height="5" />',
    );
  });
});
