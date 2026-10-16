import { z } from 'zod';

export const TextSchema = z.string().max(500);
/** Allowlisted inline HTML (sanitized again by the frontend). */
export const RichTextSchema = z.string().max(20_000);
/** Allowlisted block HTML for long-form content. */
export const BodyHtmlSchema = z.string().max(100_000);
export const SlugSchema = z
  .string()
  .min(1)
  .max(200)
  .regex(/^[\p{Ll}\p{Lo}\p{N}_-]+$/u);
export const TimestampSchema = z.iso.datetime();
export const ThemeSchema = z.enum(['default', 'muted', 'brand']);

export const HttpsSchema = z
  .string()
  .max(2048)
  .refine((value) => {
    try {
      const url = new URL(value);
      return (
        url.protocol === 'https:' &&
        !url.username &&
        !url.password &&
        !/[\\\s]/.test(value)
      );
    } catch {
      return false;
    }
  }, 'Expected an HTTPS URL without credentials');

export const LinkUrlSchema = z
  .string()
  .max(2048)
  .refine((value) => {
    if (HttpsSchema.safeParse(value).success) return true;
    try {
      const decoded = decodeURIComponent(value);
      return (
        decoded.startsWith('/') &&
        !decoded.startsWith('//') &&
        !/[\\\s\u0000-\u001f]/.test(decoded) &&
        !decoded.split('/').some((part) => part === '.' || part === '..')
      );
    } catch {
      return false;
    }
  }, 'Expected a safe frontend path or HTTPS URL');

export const LinkSchema = z.strictObject({
  label: TextSchema,
  href: LinkUrlSchema,
  newTab: z.boolean(),
});

export const ImageSchema = z.strictObject({
  url: HttpsSchema,
  alt: TextSchema,
  width: z.number().int().positive(),
  height: z.number().int().positive(),
});

export const SeoSchema = z.strictObject({
  title: TextSchema,
  description: TextSchema,
  canonical: HttpsSchema,
  openGraphImage: ImageSchema.nullable(),
  noindex: z.boolean(),
});

export const PaginationSchema = z.strictObject({
  page: z.number().int().positive(),
  perPage: z.number().int().positive(),
  total: z.number().int().nonnegative(),
  totalPages: z.number().int().nonnegative(),
});

export type Link = z.infer<typeof LinkSchema>;
export type Image = z.infer<typeof ImageSchema>;
export type Seo = z.infer<typeof SeoSchema>;
export type Pagination = z.infer<typeof PaginationSchema>;
