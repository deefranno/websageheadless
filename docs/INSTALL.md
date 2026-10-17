# Install WebSage Headless

From zero to the demo site running on your machine. Allow about 30 minutes, most of it downloads.

You receive two archives:

| Archive                                  | What it is                                             |
| ---------------------------------------- | ------------------------------------------------------ |
| `websage-headless-core-<version>.zip`    | The WordPress plugin. Upload it to any WordPress site. |
| `websage-headless-starter-<version>.zip` | The Next.js website and the kit's source code.         |

There are two ways to get WordPress running. **A** uses DDEV and is the supported local setup. **B** uses a WordPress site you already have (local or hosted). Pick one; the website steps are the same for both.

## 1. Prerequisites

| Tool                                                                                                 | Version                     | Check                                   |
| ---------------------------------------------------------------------------------------------------- | --------------------------- | --------------------------------------- |
| Node.js                                                                                              | 24.22 or newer 24.x         | `node --version`                        |
| pnpm                                                                                                 | 12.14 (via Corepack, below) | `pnpm --version`                        |
| `openssl`                                                                                            | any                         | `openssl version` (to generate secrets) |
| **Path A:** Docker Engine (or another DDEV provider) and [DDEV](https://ddev.com/get-started/)       | current                     | `ddev --version`                        |
| **Path B:** WordPress 7.1.3+ on PHP 8.1+, reachable over **HTTPS**, with WP-CLI for the demo content |                             | `wp --version`                          |

Docker Desktop is not required on Linux.

## 2. Unpack the starter and install dependencies

```bash
unzip websage-headless-starter-<version>.zip
cd websage-headless-starter-<version>
corepack enable          # makes the pinned pnpm available
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

1. In wp-admin, go to **Plugins → Add New → Upload Plugin**, choose `websage-headless-core-<version>.zip`, then **Install Now** and **Activate**. No other plugin is needed.
2. Add the secrets and the website address to `wp-config.php`, above the line `/* That's all, stop editing! */`:

   ```php
   define( 'WSH_SERVER_API_SECRET', '…' );
   define( 'WSH_PREVIEW_SECRET', '…' );
   define( 'WSH_REVALIDATION_SECRET', '…' );
   define( 'WSH_FRONTEND_ORIGIN', 'https://localhost:3000' ); // your website's address; must start with https://
   ```

   With WP-CLI you can instead run `wp config set WSH_SERVER_API_SECRET …` for each one.

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

| Variable               | Path A (DDEV)                                   | Path B                                                                                |
| ---------------------- | ----------------------------------------------- | ------------------------------------------------------------------------------------- |
| `WP_API_ORIGIN`        | `https://websage-headless.ddev.site`            | your WordPress address, e.g. `https://cms.example.com`                                |
| `WORDPRESS_MEDIA_HOST` | `websage-headless.ddev.site`                    | the same host, without `https://`                                                     |
| `NODE_EXTRA_CA_CERTS`  | output of `echo "$(mkcert -CAROOT)/rootCA.pem"` | only if WordPress uses a self-signed or local certificate: that certificate's CA file |
| `FRONTEND_ORIGIN`      | `http://localhost:3000`                         | `http://localhost:3000`                                                               |
| `WSH_*_SECRET` (4)     | the values from step 3                          | the values from step 3                                                                |

Start it:

```bash
pnpm --filter @websage/web dev
```

Open http://localhost:3000. You should see the demo home page "Websites that grow with your business", with Services, About, Blog and Contact in the menu.

## 6. Check the connection

In wp-admin open **WebSage Settings → Diagnostics**. Every row should be ✅. Locally, the **Send a test refresh** button reports a failure; that is expected (see below).

- **Edit something:** change a page and click **Update**. Locally the site shows it within 5 minutes (see below); in production it is instant.
- **Preview:** open a draft and click **Preview**; the website opens it in preview mode with a banner.
- **Contact form:** works in development without extra settings. Production needs `WSH_CLIENT_IP_HEADER` (see [DEPLOYMENT.md](DEPLOYMENT.md)).

**Why changes take up to 5 minutes locally:** WordPress tells the website to refresh by calling `https://<website>/api/revalidate`. The local development server is plain HTTP on your machine, so WordPress cannot reach it, and the site falls back to refreshing its cache every 5 minutes. Restarting `pnpm dev` also clears it. Deployed sites (HTTPS) refresh within seconds. To test instant refresh locally, see "HTTPS front for local testing" in [DEVELOPER.md](DEVELOPER.md).

## 7. Production build (optional check)

```bash
pnpm build
pnpm --filter @websage/web start     # serves the production build on http://localhost:3000
```

`robots.txt` is generated at build time, so set `FRONTEND_ORIGIN` before building. Deploying for real: [DEPLOYMENT.md](DEPLOYMENT.md).

## Troubleshooting

| Symptom                                                         | Cause and fix                                                                                                                       |
| --------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| Page shows "Something went wrong", terminal says `fetch failed` | The website cannot reach WordPress: check `WP_API_ORIGIN`; for local certificates set `NODE_EXTRA_CA_CERTS` and restart `pnpm dev`. |
| `503 not_configured` from WordPress                             | `WSH_FRONTEND_ORIGIN` is missing or not `https://` (or set the address in **WebSage Settings → Integration**).                      |
| Every page is 404                                               | Permalinks are set to "Plain", or the demo was not seeded (no front page).                                                          |
| Preview says "not configured"                                   | `WSH_PREVIEW_SECRET` differs between WordPress and the website, or is shorter than 32 characters.                                   |
| Images do not load                                              | `WORDPRESS_MEDIA_HOST` is wrong; it is read at build time, so restart `pnpm dev` or rebuild after changing it.                      |

Next: rebrand the site with [REBRANDING.md](REBRANDING.md), and give your client [CLIENT-GUIDE.md](CLIENT-GUIDE.md).
