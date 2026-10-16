import { z } from 'zod';
import {
  BodyHtmlSchema,
  ImageSchema,
  LinkSchema,
  RichTextSchema,
  TextSchema,
  ThemeSchema,
} from './primitives';

/** Fields every section carries; `key` is deterministic per traversal position. */
const envelope = {
  schemaVersion: z.literal(1),
  key: z.string().min(1).max(200),
};

export const HeroSectionSchema = z.strictObject({
  type: z.literal('hero'),
  ...envelope,
  eyebrow: TextSchema,
  heading: TextSchema,
  body: RichTextSchema,
  image: ImageSchema.nullable(),
  primary: LinkSchema.nullable(),
  secondary: LinkSchema.nullable(),
  theme: ThemeSchema,
});

export const TextImageSectionSchema = z.strictObject({
  type: z.literal('text-image'),
  ...envelope,
  heading: TextSchema,
  body: BodyHtmlSchema,
  image: ImageSchema.nullable(),
  position: z.enum(['left', 'right']),
  theme: ThemeSchema,
});

export const FeatureItemSchema = z.strictObject({
  title: TextSchema,
  body: RichTextSchema,
  image: ImageSchema.nullable(),
  link: LinkSchema.nullable(),
});

export const FeatureGridSectionSchema = z.strictObject({
  type: z.literal('feature-grid'),
  ...envelope,
  heading: TextSchema,
  introduction: RichTextSchema,
  columns: z.union([z.literal(2), z.literal(3), z.literal(4)]),
  theme: ThemeSchema,
  items: z.array(FeatureItemSchema).max(12),
});

export const CtaSectionSchema = z.strictObject({
  type: z.literal('cta'),
  ...envelope,
  heading: TextSchema,
  body: RichTextSchema,
  link: LinkSchema,
  theme: ThemeSchema,
});

export const TestimonialSchema = z.strictObject({
  quote: TextSchema,
  author: TextSchema,
  role: TextSchema,
  company: TextSchema,
  image: ImageSchema.nullable(),
});

export const TestimonialsSectionSchema = z.strictObject({
  type: z.literal('testimonials'),
  ...envelope,
  heading: TextSchema,
  theme: ThemeSchema,
  items: z.array(TestimonialSchema).max(12),
});

export const FaqItemSchema = z.strictObject({
  question: TextSchema,
  answer: RichTextSchema,
});

export const FaqSectionSchema = z.strictObject({
  type: z.literal('faq'),
  ...envelope,
  heading: TextSchema,
  theme: ThemeSchema,
  items: z.array(FaqItemSchema).max(30),
});

export const ContactSectionSchema = z.strictObject({
  type: z.literal('contact'),
  ...envelope,
  heading: TextSchema,
  body: RichTextSchema,
  submitLabel: TextSchema,
  successMessage: TextSchema,
  theme: ThemeSchema,
});

export const CoreContentSectionSchema = z.strictObject({
  type: z.literal('core-content'),
  ...envelope,
  html: BodyHtmlSchema,
});

export const KnownSectionSchema = z.discriminatedUnion('type', [
  HeroSectionSchema,
  TextImageSectionSchema,
  FeatureGridSectionSchema,
  CtaSectionSchema,
  TestimonialsSectionSchema,
  FaqSectionSchema,
  ContactSectionSchema,
  CoreContentSectionSchema,
]);

export const KNOWN_SECTION_TYPES = KnownSectionSchema.options.map(
  (option) => option.shape.type.value,
);

/**
 * A section type this frontend does not know (a newer plugin). Only the
 * envelope survives parsing; its attributes never render. Known type names
 * are refused here so a malformed known section cannot hide as "unknown".
 */
export const UnknownSectionSchema = z.object({
  type: z
    .string()
    .min(1)
    .max(100)
    .refine(
      (type) => !(KNOWN_SECTION_TYPES as string[]).includes(type),
      'Known section type failed validation',
    ),
  schemaVersion: z.number().int().nonnegative(),
  key: z.string().min(1).max(200),
});

export const SectionSchema = z.union([
  KnownSectionSchema,
  UnknownSectionSchema,
]);
export const SectionsSchema = z.array(SectionSchema).max(100);

export type HeroSection = z.infer<typeof HeroSectionSchema>;
export type TextImageSection = z.infer<typeof TextImageSectionSchema>;
export type FeatureGridSection = z.infer<typeof FeatureGridSectionSchema>;
export type CtaSection = z.infer<typeof CtaSectionSchema>;
export type TestimonialsSection = z.infer<typeof TestimonialsSectionSchema>;
export type FaqSection = z.infer<typeof FaqSectionSchema>;
export type ContactSection = z.infer<typeof ContactSectionSchema>;
export type CoreContentSection = z.infer<typeof CoreContentSectionSchema>;
export type KnownSection = z.infer<typeof KnownSectionSchema>;
export type UnknownSection = z.infer<typeof UnknownSectionSchema>;
export type Section = z.infer<typeof SectionSchema>;

export function parseSection(input: unknown): Section {
  return SectionSchema.parse(input);
}

export function isKnownSection(section: Section): section is KnownSection {
  return (KNOWN_SECTION_TYPES as string[]).includes(section.type);
}
