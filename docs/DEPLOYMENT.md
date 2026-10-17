# Deployment

A WebSage Headless site is two deployments: **WordPress** (the CMS, for example `cms.example.com`) and the **Next.js website** (for example `www.example.com`). Both must be served over HTTPS.

**Static export (`next export`) is not supported.** Previews, the contact form and instant refresh need a running Next.js server.

## Environment variables (website)

Set these in your host's environment. The template with explanations is `.env.example`.

| Variable                                                                 | Required                   | Notes                                                                                                |
| ------------------------------------------------------------------------ | -------------------------- | ---------------------------------------------------------------------------------------------------- |
| `WP_API_ORIGIN`                                                          | yes                        | `https://cms.example.com`                                                                            |
| `FRONTEND_ORIGIN`                                                        | yes, **at build time too** | `https://www.example.com`; `robots.txt` is generated during the build                                |
| `WORDPRESS_MEDIA_HOST`                                                   | yes, **at build time**     | `cms.example.com` (host of `/wp-content/uploads`); changing it needs a rebuild                       |
| `WSH_SERVER_API_SECRET`, `WSH_PREVIEW_SECRET`, `WSH_REVALIDATION_SECRET` | yes                        | ≥ 32 random characters each, identical in WordPress                                                  |
| `WSH_RATE_LIMIT_SECRET`                                                  | yes                        | ≥ 32 random characters, website only                                                                 |
| `WSH_CLIENT_IP_HEADER`                                                   | yes for the contact form   | the header your platform sets to the visitor's IP; see below                                         |
| `WSH_TRUSTED_PROXY_HOPS`                                                 | with `x-forwarded-for`     | number of proxies in front of Next.js that append to the header                                      |
| `TURNSTILE_SECRET_KEY`, `NEXT_PUBLIC_TURNSTILE_SITE_KEY`                 | optional                   | Cloudflare Turnstile on the contact form; set both, and rebuild (the site key is read at build time) |

**Client IP header.** The contact form rate-limits by visitor (a keyed hash; WordPress never sees addresses). It only trusts the header you name, because any other header can be forged. Without it, production forms answer "temporarily unavailable" rather than guess.

| Platform                                                     | Setting                                                                          |
| ------------------------------------------------------------ | -------------------------------------------------------------------------------- |
| Vercel                                                       | `WSH_CLIENT_IP_HEADER=x-forwarded-for`, `WSH_TRUSTED_PROXY_HOPS=1`               |
| Behind Cloudflare (origin reachable only through Cloudflare) | `WSH_CLIENT_IP_HEADER=cf-connecting-ip`                                          |
| nginx / Apache / LiteSpeed reverse proxy                     | `x-forwarded-for`, hops = number of your proxies that append it (one proxy: `1`) |

Check your platform's documentation for how it sets these headers; the values above are the common cases.

## WordPress (production)

1. WordPress 7.1.3+ on PHP 8.1+ (8.3 recommended), HTTPS, pretty permalinks.
2. Install and activate the plugin zip. No other plugins are required; avoid SEO or caching plugins that alter the REST API.
3. In `wp-config.php`:

   ```php
   define( 'WSH_FRONTEND_ORIGIN', 'https://www.example.com' );
   define( 'WSH_SERVER_API_SECRET', '…' );
   define( 'WSH_PREVIEW_SECRET', '…' );
   define( 'WSH_REVALIDATION_SECRET', '…' );
   // Optional: send refresh webhooks to an internal address instead of https://www.example.com/api/revalidate
   // define( 'WSH_REVALIDATE_URL', 'https://web.internal:3443/api/revalidate' );
   ```

4. **Real cron** (recommended): webhook and email retries use WP-Cron, which only runs on page views, and the CMS has few. Add `define( 'DISABLE_WP_CRON', true );` and a system cron job every minute: `wp cron event run --due-now --path=/path/to/wordpress`.
5. **Email delivery:** notifications use `wp_mail`. Configure SMTP or your host's mail service so they are not marked as spam.
6. **WebSage Settings → Integration:** hardening is on by default: the CMS front end is noindexed and redirects to the website (wp-admin, login, REST API and media are unaffected), the core sitemap and XML-RPC are off, and author listing is blocked.
7. **WebSage Settings → Diagnostics:** all rows ✅, then click **Send a test refresh**; it should report that the website accepted the signed webhook.

## Option 1: Vercel (or a similar managed Next.js host)

1. Import the repository. Set **Root Directory** to `apps/web`; Vercel detects pnpm from the workspace lockfile.
2. Framework preset: Next.js. Build command: default (`next build`). Install command: default.
3. Add the environment variables above for Production (and Preview if you use preview deployments, with their own `FRONTEND_ORIGIN`).
4. Deploy, add your domain, then set `WSH_FRONTEND_ORIGIN` in WordPress to that domain.

The kit uses only standard Next.js features (Cache Components, `revalidateTag`, Draft Mode, `proxy.ts`), which managed Next.js hosts support. `output: 'standalone'` is harmless there.

## Option 2: Self-hosted Node (standalone)

Build (on a CI runner or the server), with the build-time variables set:

```bash
corepack enable
pnpm install --frozen-lockfile
FRONTEND_ORIGIN=https://www.example.com WORDPRESS_MEDIA_HOST=cms.example.com pnpm build
cp -r apps/web/.next/static apps/web/.next/standalone/apps/web/.next/
```

Deploy the `apps/web/.next/standalone` directory (it contains a minimal `node_modules`) and run:

```bash
PORT=3000 node apps/web/server.js        # from inside the standalone directory
```

Requirements:

- **Node.js 24.22+** on the server.
- **Keep the default `HOSTNAME`** (all interfaces). Binding to `127.0.0.1` behind a TLS proxy breaks internal rewrites (real 404 pages) in Next.js 16.4 (ADR-014). Restrict access with the firewall instead.
- **One instance per site**, or a shared cache. The data cache is in memory per process, so a refresh webhook reaches only the instance that receives it; with several instances, others serve old content until the 5-minute fallback. Use a single instance (normal for brochure sites) or configure a shared Next.js cache handler.

Example systemd unit:

```ini
[Service]
WorkingDirectory=/srv/website/standalone
EnvironmentFile=/srv/website/env
ExecStart=/usr/bin/node apps/web/server.js
Environment=PORT=3000 NODE_ENV=production
Restart=always
User=website
```

Example nginx server block (TLS configuration omitted):

```nginx
server {
  server_name www.example.com;
  location / {
    proxy_pass http://127.0.0.1:3000;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
  }
}
```

With this block, use `WSH_CLIENT_IP_HEADER=x-forwarded-for` and `WSH_TRUSTED_PROXY_HOPS=1`.

### cPanel / LiteSpeed "Setup Node.js App"

Where the host offers Node.js 24 apps: build locally or in CI as above, upload the standalone directory as the application root, set the startup file to `apps/web/server.js`, add the environment variables in the panel, and start the app. LiteSpeed's proxy appends to `X-Forwarded-For` (hops `1`). Hosts that only offer older Node versions cannot run this kit. This path follows the hosts' documented Node.js support but has not been tested on a specific host.

## After deploying

- Visit the site; check a page, the blog, a post and a missing URL (should be a 404 page).
- Edit a page in WordPress and reload the website within a few seconds.
- Preview a draft from wp-admin.
- Send a contact message; confirm it arrives by email and under **Form messages**.
- Run PageSpeed Insights (https://pagespeed.web.dev/) on the home page and a blog post.
- Optional: run the browser suite against a **staging** copy (it creates content): `WSH_E2E_WEB_URL=… WSH_E2E_WP_URL=… WSH_E2E_WP_USER=… WSH_E2E_WP_PASSWORD=… pnpm test:integration`.
