import { z } from 'zod';
import { SeoSchema, TextSchema, TimestampSchema } from './primitives';
import { SectionsSchema } from './sections';

export const PagePathSchema = z.string().startsWith('/').max(2048);

export const PageSchema = z.strictObject({
  path: PagePathSchema,
  title: TextSchema,
  modified: TimestampSchema,
  sections: SectionsSchema,
  seo: SeoSchema,
});

export const PageResponseSchema = z.strictObject({ data: PageSchema });

export type Page = z.infer<typeof PageSchema>;
