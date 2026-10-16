import { z } from 'zod';
import {
  ImageSchema,
  PaginationSchema,
  SeoSchema,
  SlugSchema,
  TextSchema,
  TimestampSchema,
} from './primitives';
import { SectionsSchema } from './sections';

export const CategorySchema = z.strictObject({
  slug: SlugSchema,
  name: TextSchema,
});

export const PostSummarySchema = z.strictObject({
  slug: SlugSchema,
  path: z.string().startsWith('/blog/').max(2048),
  title: TextSchema,
  excerpt: TextSchema,
  published: TimestampSchema,
  modified: TimestampSchema,
  image: ImageSchema.nullable(),
  categories: z.array(CategorySchema).max(50),
});

export const PostSchema = PostSummarySchema.extend({
  sections: SectionsSchema,
  seo: SeoSchema,
});

export const PostListSchema = z.strictObject({
  items: z.array(PostSummarySchema).max(100),
  pagination: PaginationSchema,
  categories: z.array(CategorySchema).max(500),
});

export const PostListResponseSchema = z.strictObject({ data: PostListSchema });
export const PostResponseSchema = z.strictObject({ data: PostSchema });

export type Category = z.infer<typeof CategorySchema>;
export type PostSummary = z.infer<typeof PostSummarySchema>;
export type Post = z.infer<typeof PostSchema>;
export type PostList = z.infer<typeof PostListSchema>;
