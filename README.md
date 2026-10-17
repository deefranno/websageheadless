# WebSage Headless

A starter kit by WebSage Creative for websites built with **Next.js**, managed in **WordPress**.

Clients edit pages, posts, menus, branding and SEO in wp-admin, using native block-editor sections. The Next.js website renders everything on the server, updates within seconds of publishing, and previews drafts. One WordPress plugin, WebSage Headless Core, is all WordPress needs: no ACF, no GraphQL, no paid plugins.

## Documentation

| Guide                                                                   | For                                                                       |
| ----------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| [Install](docs/INSTALL.md)                                              | From zero to the demo site running locally                                |
| [Developer guide](docs/DEVELOPER.md)                                    | Architecture, adding sections, settings and API endpoints, releasing      |
| [Rebranding](docs/REBRANDING.md)                                        | New logo, colours and fonts without touching components                   |
| [Deployment](docs/DEPLOYMENT.md)                                        | Vercel, self-hosted Node, cPanel/LiteSpeed, WordPress production settings |
| [Client guide](docs/CLIENT-GUIDE.md)                                    | Plain-language wp-admin guide to hand to clients                          |
| [Security](docs/SECURITY.md)                                            | Security model, configuration checklist, reporting vulnerabilities        |
| [Architecture](docs/ARCHITECTURE.md) and [decisions](docs/DECISIONS.md) | Detailed design and the reasons behind it                                 |
| [Changelog](CHANGELOG.md)                                               | Releases                                                                  |

## At a glance

```text
apps/web/            Next.js website (React Server Components, Tailwind, Zod)
packages/wp-plugin/  WebSage Headless Core (WordPress plugin, PHP 8.1+)
packages/contracts/  shared schemas and fixtures for the API between them
```

Requirements: Node.js 24, pnpm 12 (via Corepack), WordPress 7.1.3+ on PHP 8.1+ over HTTPS (DDEV for local development).

## Licence

The WordPress plugin is GPL-2.0-or-later, the contracts package is MIT, and the Next.js website is under a commercial licence. See [LICENSE](LICENSE).
