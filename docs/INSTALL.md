# Install WebSage Headless

From zero to the demo site running on your machine. Allow about 30 minutes, most of it downloads.

You receive two archives:

| Archive                                  | What it is                                             |
| ---------------------------------------- | ------------------------------------------------------ |
| `websage-headless-core-<version>.zip`    | The WordPress plugin. Upload it to any WordPress site. |
| `websage-headless-starter-<version>.zip` | The Next.js website and the kit's source code.         |

There are two ways to get WordPress running. **A** uses DDEV and is the supported local setup. **B** uses a WordPress site you already have (local or hosted). Pick one; the website steps are the same for both.

## 1. Prerequisites

| Tool                                                                                                 | Version                 | Check                                   |
| ---------------------------------------------------------------------------------------------------- | ----------------------- | --------------------------------------- |
| Node.js                                                                                              | 24.22 or newer 24.x     | `node --version`                        |
| pnpm                                                                                                 | 12.14 (installed below) | `pnpm --version`                        |
| `openssl`                                                                                            | any                     | `openssl version` (to generate secrets) |
| **Path A:** Docker Engine (or another DDEV provider) and [DDEV](https://ddev.com/get-started/)       | current                 | `ddev --version`                        |
| **Path B:** WordPress 7.1.3+ on PHP 8.1+, reachable over **HTTPS**, with WP-CLI for the demo content |                         | `wp --version`                          |

Docker Desktop is not required on Linux.

## 2. Unpack the starter and install dependencies

```bash
npm install --global pnpm@12.14.0   # skip if `pnpm --version` already prints 12.14.x
unzip websage-headless-starter-<version>.zip
cd websage-headless-starter-<version>
pnpm install
```

## 3. Create the shared secrets

WordPress and the website prove who they are to each other with secrets. Generate four (each at least 32 characters; shorter values count as "not set"):

```bash
for name in SERVER_API PREVIEW REVALIDATION RATE_LIMIT; do echo "WSH_${name}_SECRET=$(openssl rand -hex 32)"; done
```

Keep the output; you need the first three in WordPress and all four in the website.

## 4. WordPress

### Path A: DDEV (supported local setup)

```bash
pnpm --filter @websage/wp-plugin build   # compile the editor blocks
ddev start
ddev websage-install                     # WordPress 7.1.3 with only WebSage Headless Core; prints the admin password once
```

Store the printed password. Then give WordPress the secrets (replace each `…` with your value) and load the demo:

```bash
ddev wp config set WSH_SERVER_API_SECRET …   --path=.wordpress/site
ddev wp config set WSH_PREVIEW_SECRET …      --path=.wordpress/site
ddev wp config set WSH_REVALIDATION_SECRET … --path=.wordpress/site
ddev wp websage seed --yes --path=.wordpress/site
```

WordPress is at https://websage-headless.ddev.site/wp-admin/ (user `admin`).

### Path B: an existing WordPress site

1. In wp-admin, go to **Plugins → Add Plugin → Upload Plugin**, choose `websage-headless-core-<version>.zip`, then **Install Now** and **Activate**. No other plugin is needed.
2. Add the secrets and the website address to `wp-config.php`, above the line `/* That's all, stop editing! */`:

   ```php
   define( 'WSH_SERVER_API_SECRET', '…' );
   define( 'WSH_PREVIEW_SECRET', '…' );
   define( 'WSH_REVALIDATION_SECRET', '…' );
   define( 'WSH_FRONTEND_ORIGIN', 'http://localhost:3000' ); // the website's address: https:// in production; http:// is accepted for localhost only
   ```

   With WP-CLI you can instead run `wp config set WSH_SERVER_API_SECRET …` for each one (it prints the value back, so run it where nobody is watching your screen).

3. Load the demo content (pages, posts, menus, settings; safe to run again; it never deletes your own content):

   ```bash
   wp websage seed --yes
   ```

4. Set **Settings → Permalinks** to anything except "Plain" if it is not already.

## 5. The website

Create `apps/web/.env.local` from the template:

```bash
cp .env.example apps/web/.env.local
```

Edit it:

| Variable               | Path A (DDEV)                        | Path B                                                 |
| ---------------------- | ------------------------------------ | ------------------------------------------------------ |
| `WP_API_ORIGIN`        | `https://websage-headless.ddev.site` | your WordPress address, e.g. `https://cms.example.com` |
| `WORDPRESS_MEDIA_HOST` | `websage-headless.ddev.site`         | the same host, without `https://`                      |
| `FRONTEND_ORIGIN`      | `http://localhost:3000`              | `http://localhost:3000`                                |
| `WSH_*_SECRET` (4)     | the values from step 3               | the values from step 3                                 |

Start it:

```bash
pnpm --filter @websage/web dev
```

**Local HTTPS certificates.** If WordPress uses a certificate your computer does not trust by default (DDEV's mkcert certificate, or a self-signed one), Node.js must be told about it **in the shell that starts the server**. Putting it in `.env.local` does not work, because Node reads it before the app loads its settings:

```bash
NODE_EXTRA_CA_CERTS="$(mkcert -CAROOT)/rootCA.pem" pnpm --filter @websage/web dev   # Path A (DDEV)
NODE_EXTRA_CA_CERTS=/path/to/your-ca.pem pnpm --filter @websage/web dev               # Path B with a self-signed certificate
```

Open http://localhost:3000. You should see the demo home page "Websites that grow with your business", with Home, About (Services is in its drop-down), Blog and Contact in the menu.

## 6. Check the connection

In wp-admin open **WebSage Settings → Diagnostics**. Every row should be ✅. **Last webhook delivery** may still show a failure from before the website was running (for example from loading the demo); click **Send a test refresh** and reload the screen.

- **Edit something:** change a page and click **Update**, then reload the website. Path B on the same computer: within seconds. Path A (DDEV): within 5 minutes (see below). Deployed sites: within seconds.
- **Preview:** open a draft and click **Preview**; the website opens it in preview mode with a banner.
- **Contact form:** works in development without extra settings. Production needs `WSH_CLIENT_IP_HEADER` (see [DEPLOYMENT.md](DEPLOYMENT.md)).

**Why DDEV changes take up to 5 minutes:** WordPress tells the website to refresh by calling `<website address>/api/revalidate`. Inside DDEV's container, `localhost:3000` is the container itself, not your computer, so the call fails and the website falls back to refreshing its cache every 5 minutes. Restarting `pnpm dev` also clears it. For instant refresh with DDEV, see "HTTPS front for local testing" in [DEVELOPER.md](DEVELOPER.md). **Send a test refresh** on the Diagnostics screen shows whether WordPress can reach the website.

## 7. Production build (optional check)

```bash
pnpm build
pnpm --filter @websage/web start     # serves the production build on http://localhost:3000, reading apps/web/.env.local
```

Put `NODE_EXTRA_CA_CERTS=…` in front of the `start` command too if you needed it in step 5. `robots.txt` is generated at build time, so set `FRONTEND_ORIGIN` before building. In production mode the contact form also needs `WSH_CLIENT_IP_HEADER` (it answers "temporarily unavailable" without it); see [DEPLOYMENT.md](DEPLOYMENT.md).

## Troubleshooting

| Symptom                                                         | Cause and fix                                                                                                                                                         |
| --------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Page shows "Something went wrong", terminal says `fetch failed` | The website cannot reach WordPress: check `WP_API_ORIGIN`; for local certificates start the server with `NODE_EXTRA_CA_CERTS=…` in front of the command (see step 5). |
| `503 not_configured` from WordPress                             | `WSH_FRONTEND_ORIGIN` is missing, or not `https://` (only `localhost` may use `http://`); or set the address in **WebSage Settings → Integration**.                   |
| Every page shows an error or a 404                              | Permalinks are still "Plain" (the API at `/wp-json/` needs pretty permalinks), or the demo was not seeded (no front page).                                            |
| Preview says "not configured"                                   | `WSH_PREVIEW_SECRET` differs between WordPress and the website, or is shorter than 32 characters.                                                                     |
| Images do not load                                              | `WORDPRESS_MEDIA_HOST` is wrong; it is read at build time, so restart `pnpm dev` or rebuild after changing it.                                                        |

Next: rebrand the site with [REBRANDING.md](REBRANDING.md), and give your client [CLIENT-GUIDE.md](CLIENT-GUIDE.md).
