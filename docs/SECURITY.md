# Security

## Reporting a vulnerability

Email **duanefranklyn@gmail.com** with the subject "WebSage Headless security". Include the version, what an attacker can do, and steps to reproduce. Please do not open a public issue or publish details until a fix is available.

You will get an acknowledgement within 3 working days and an assessment within 10. Fixes are released as patch versions and listed in `CHANGELOG.md`; reporters are credited unless they prefer not to be.

Supported versions: the latest release.

## Security model

**Boundaries.** Browsers talk only to the Next.js website. The website talks to WordPress server-to-server over HTTPS. WordPress's custom API is public for published content only; anything that reveals drafts or writes data needs a server secret.

| Mechanism                                                                                                                                    | Protects                                          |
| -------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------- |
| Server API secret (bearer, constant-time compare)                                                                                            | `/preview` and `/contact` on WordPress            |
| Preview tokens: HMAC-signed, 5-minute lifetime, re-verified on every render; grant kept in an encrypted HttpOnly cookie                      | Draft content                                     |
| Revalidation webhooks: HMAC over timestamp, event ID and body; ±5-minute window                                                              | Cache invalidation                                |
| Zod validation of every WordPress response; size, depth and time limits                                                                      | The website against malformed or hostile CMS data |
| Server-side HTML allowlist sanitization; JSON-LD escaping; validated CSS tokens                                                              | Visitors against stored XSS                       |
| Same-origin checks, size limits, honeypot, keyed per-visitor rate limit, explicit proxy trust                                                | The contact form against spam and abuse           |
| Capability checks and nonces on every admin action                                                                                           | wp-admin against CSRF and privilege escalation    |
| CORS closed on the custom API; CMS front end noindexed and redirected; XML-RPC off; user enumeration blocked (REST, author archives, oEmbed) | The CMS host                                      |
| CSP, HSTS, `nosniff`, Referrer-Policy, Permissions-Policy, `frame-ancestors 'none'`                                                          | Visitors (browser hardening)                      |

Secrets live only in server environment variables and `wp-config.php`; they are never stored in the database, shown in wp-admin or returned by any route. Shorter than 32 characters counts as unset.

Contact messages are stored in a private post type (administrators only, no REST route, no public URL) and deleted after the configured retention period. Visitor IP addresses never reach WordPress.

Threat list and design details: [ARCHITECTURE.md](ARCHITECTURE.md) (Security model). Findings fixed in the pre-release review are listed in `CHANGELOG.md` under 1.0.0-rc.1.

## Configuration checklist

- [ ] HTTPS on both the website and WordPress.
- [ ] Four different random secrets, ≥ 32 characters (`openssl rand -hex 32`); the first three identical on both sides.
- [ ] `WSH_CLIENT_IP_HEADER` matches your platform ([DEPLOYMENT.md](DEPLOYMENT.md)).
- [ ] `WORDPRESS_MEDIA_HOST` set to the CMS host only (images from other hosts are refused).
- [ ] Hardening options left on in **WebSage Settings → Integration**.
- [ ] Strong passwords and two-factor login for WordPress administrators (use your host's or a security plugin's 2FA).
- [ ] WordPress, PHP and Node.js kept up to date; rebuild the website when dependencies are updated.
- [ ] `WP_DEBUG_DISPLAY` off in production (WordPress errors never reach the website, but keep them off the CMS too).

## Known limits

- The Content Security Policy allows inline scripts (`'unsafe-inline'`), because cached pages cannot carry per-request nonces (ADR-005).
- Rate limiting uses fixed 15-minute windows: up to 10 messages are possible across a window boundary.
- The data cache is per process; see "One instance per site" in [DEPLOYMENT.md](DEPLOYMENT.md).
