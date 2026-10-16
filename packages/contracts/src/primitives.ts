import { z } from 'zod';
export const TextSchema = z.string().max(500);
export const RichTextSchema = z.string().max(20_000);
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
