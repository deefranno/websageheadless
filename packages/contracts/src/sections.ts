import { z } from 'zod';
import {
  TextSchema,
  RichTextSchema,
  ImageSchema,
  LinkSchema,
} from './primitives';
export const HeroSectionSchema = z.strictObject({
  type: z.literal('hero'),
  schemaVersion: z.literal(1),
  key: z.string().min(1).max(200),
  eyebrow: TextSchema,
  heading: TextSchema,
  body: RichTextSchema,
  image: ImageSchema.nullable(),
  primary: LinkSchema.nullable(),
  secondary: LinkSchema.nullable(),
  theme: z.enum(['default', 'muted', 'brand']),
});
export const UnknownSectionSchema = z.object({
  type: z
    .string()
    .min(1)
    .max(100)
    .refine((type) => type !== 'hero'),
  schemaVersion: z.number().int().nonnegative(),
  key: z.string().min(1).max(200),
});
export type HeroSection = z.infer<typeof HeroSectionSchema>;
export type UnknownSection = z.infer<typeof UnknownSectionSchema>;
export const SectionSchema = z.union([HeroSectionSchema, UnknownSectionSchema]);
export function parseSection(input: unknown): HeroSection | UnknownSection {
  return SectionSchema.parse(input);
}
