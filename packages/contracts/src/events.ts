import { z } from 'zod';
import { PagePathSchema } from './pages';

/**
 * Cache tags shared by the plugin (which emits them) and the frontend
 * (which tags fetches with them): site | posts | routes | page:/path | post:slug
 */
export const CacheTagSchema = z
  .string()
  .max(2100)
  .regex(
    /^(?:site|posts|routes|page:\/(?:[\p{Ll}\p{Lo}\p{N}_-]+(?:\/[\p{Ll}\p{Lo}\p{N}_-]+)*)?|post:[\p{Ll}\p{Lo}\p{N}_-]+)$/u,
    'Unknown cache tag',
  );

export const RevalidationEventSchema = z.strictObject({
  version: z.literal(1),
  event: z.enum([
    'content.updated',
    'content.deleted',
    'menu.updated',
    'settings.updated',
  ]),
  resource: z.enum(['page', 'post', 'testimonial', 'cta', 'menu', 'settings']),
  paths: z.array(PagePathSchema).max(50),
  tags: z.array(CacheTagSchema).min(1).max(100),
});

export type CacheTag = z.infer<typeof CacheTagSchema>;
export type RevalidationEvent = z.infer<typeof RevalidationEventSchema>;
