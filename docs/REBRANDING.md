# Rebranding checklist

WebSage Headless is rebranded from WordPress. Logo, brand colours and fonts are settings; no React component needs editing. Budget: under an hour for a competent developer, most of it choosing colours.

Automated proof: `apps/web/tests/e2e/rebranding.spec.ts` changes the logo, all eight colours and both fonts through the real settings screen, then checks every demo page (and the form error state and mobile menu) for any element still using an old colour, wrong fonts or the old logo, and runs axe on the result.

## 1. In WordPress (no code, no rebuild)

**WebSage Settings → Branding**

| Setting                         | Used for                                                                                    |
| ------------------------------- | ------------------------------------------------------------------------------------------- |
| Site name                       | Header text when there is no logo, titles, structured data                                  |
| Logo                            | Header (replaces the site name); give the image alt text in the media library               |
| Heading font / Body font        | Built-in choices: Sora or system font for headings, Montserrat or system font for body text |
| Primary, Text on primary        | Hero, call to action, buttons, active category                                              |
| Background, Text                | Page background and body text                                                               |
| Muted background, Text on muted | Alternate sections, footer                                                                  |
| Borders                         | Form fields, cards, dividers                                                                |
| Focus outline                   | Keyboard focus ring                                                                         |

Contrast guard: if Text/Background, Text on primary/Primary or Text on muted/Muted background fall below 4.5:1 (WCAG AA), the pair is rolled back and a notice explains why. Components only place text in these three pairs (never primary-coloured text on the page background), so passing the guard keeps text contrast at AA whichever colours are chosen.

Also check: **SEO** tab (organization name, title template, default sharing image), **Footer** (copyright, social links), **Contact** (email, phone, address), and **Appearance → Menus**.

Changes reach the website within seconds (signed revalidation webhook). If they do not, see **WebSage Settings → Diagnostics**.

## 2. Optional: code defaults (token file)

Only needed when the kit should ship with a different default brand (for example, an agency starter):

1. `apps/web/app/globals.css`, the `:root` block: default colours and font stacks, used before settings load.
2. `apps/web/config/tokens.ts`, `DEFAULT_TOKENS`: the same colours.
3. `packages/wp-plugin/src/Settings/Settings.php`, `DEFAULT_TOKENS`: the same colours, used as WordPress defaults.

Keep the three in sync. Spacing, radius, shadow and type scale live in the `@theme` block of `globals.css` and apply everywhere.

## 3. Optional: a different font family

Fonts are compiled in with `next/font` (self-hosted, no runtime requests to Google). To offer another family:

1. `apps/web/config/fonts.ts`: load it and add it to `FAMILIES`.
2. `packages/contracts/src/site.ts`: add its ID to the heading or body `z.enum`.
3. `packages/wp-plugin/src/Settings/Settings.php`: add the ID to `HEADING_FONTS` or `BODY_FONTS`, and the option to the select in `src/Fields/SettingsPage.php`.
4. Rebuild the frontend and the plugin, then choose it in WebSage Settings.

## 4. Verify

- [ ] Header shows the new logo (with alt text) on every page width; mobile menu opens.
- [ ] Home, a content page, contact (including an error state), blog list, a post and the 404 page use only the new colours and fonts.
- [ ] Keyboard focus ring is visible on links, buttons and form fields.
- [ ] Sharing preview (Open Graph image, site name) is correct.
- [ ] `pnpm --filter @websage/web test:integration` passes against your site, including `rebranding.spec.ts` and `accessibility.spec.ts`.
