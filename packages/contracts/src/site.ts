import { z } from 'zod';
import {
  TextSchema,
  LinkSchema,
  ImageSchema,
  LinkUrlSchema,
} from './primitives';
const Color = z.string().regex(/^#[0-9a-fA-F]{6}$/);
const MenuItemSchema: z.ZodType<MenuItem> = z.lazy(() =>
  z.strictObject({
    label: TextSchema,
    href: LinkUrlSchema,
    newTab: z.boolean(),
    children: z.array(MenuItemSchema).max(100),
  }),
);
export interface MenuItem {
  label: string;
  href: string;
  newTab: boolean;
  children: MenuItem[];
}
const Menu = z
  .array(MenuItemSchema)
  .max(100)
  .superRefine((items, ctx) => {
    let count = 0;
    function visit(nodes: MenuItem[], depth: number) {
      for (const node of nodes) {
        count++;
        if (depth > 3 || count > 100) {
          ctx.addIssue({ code: 'custom', message: 'Menu exceeds limits' });
          return;
        }
        visit(node.children, depth + 1);
      }
    }
    visit(items, 1);
  });
export const SiteSchema = z.strictObject({
  name: TextSchema,
  branding: z.strictObject({
    logo: ImageSchema.nullable(),
    fonts: z.strictObject({
      heading: z.enum(['sora', 'system']),
      body: z.enum(['montserrat', 'system']),
    }),
    tokens: z.strictObject({
      primary: Color,
      on_primary: Color,
      background: Color,
      foreground: Color,
      muted: Color,
      on_muted: Color,
      border: Color,
      focus: Color,
    }),
  }),
  header: z.strictObject({
    announcement: TextSchema,
    announcementLink: LinkSchema.nullable(),
  }),
  footer: z.strictObject({
    description: TextSchema,
    copyright: TextSchema,
    social: z
      .array(z.strictObject({ label: TextSchema, url: LinkUrlSchema }))
      .max(20),
  }),
  menus: z.strictObject({ primary: Menu, footer: Menu }),
  contact: z.strictObject({
    email: z.union([z.literal(''), z.email()]),
    phone: TextSchema,
    address: TextSchema,
  }),
});
export const SiteResponseSchema = z.strictObject({ data: SiteSchema });
export type Site = z.infer<typeof SiteSchema>;
