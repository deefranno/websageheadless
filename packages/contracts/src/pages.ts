import { z } from 'zod';
import { TextSchema, SeoSchema } from './primitives';
import { SectionSchema } from './sections';
export const PageSchema = z.strictObject({
  path: z.string().startsWith('/').max(2048),
  title: TextSchema,
  modified: z.iso.datetime(),
  sections: z.array(SectionSchema).max(100),
  seo: SeoSchema,
});
export const PageResponseSchema = z.strictObject({ data: PageSchema });
export type Page = z.infer<typeof PageSchema>;
