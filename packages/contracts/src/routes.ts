import { z } from 'zod';
import { PaginationSchema, TimestampSchema } from './primitives';
import { PagePathSchema, PageSchema } from './pages';
import { PostSchema } from './posts';

export const RouteItemSchema = z.strictObject({
  path: PagePathSchema,
  kind: z.enum(['page', 'post', 'index']),
  modified: TimestampSchema,
  /** Exists but is hidden from search: kept out of the sitemap, not a 404. */
  noindex: z.boolean(),
});

export const RouteListSchema = z.strictObject({
  items: z.array(RouteItemSchema).max(500),
  pagination: PaginationSchema,
});

export const RouteListResponseSchema = z.strictObject({
  data: RouteListSchema,
});

export const PreviewSchema = z.discriminatedUnion('kind', [
  z.strictObject({ kind: z.literal('page'), content: PageSchema }),
  z.strictObject({ kind: z.literal('post'), content: PostSchema }),
]);

export const PreviewResponseSchema = z.strictObject({ data: PreviewSchema });

export type RouteItem = z.infer<typeof RouteItemSchema>;
export type RouteList = z.infer<typeof RouteListSchema>;
export type Preview = z.infer<typeof PreviewSchema>;
