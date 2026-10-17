import type { Site } from '@websage/contracts';
import { SmartLink } from '@/components/ui/SmartLink';

export function Footer({ site }: { site: Site }) {
  const { description, copyright, social } = site.footer;
  return (
    <footer className="mt-auto bg-muted text-on-muted">
      <div className="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-3">
        <div>
          <p className="font-heading text-lg font-bold">{site.name}</p>
          {description ? <p className="mt-2">{description}</p> : null}
          {site.contact.email || site.contact.phone || site.contact.address ? (
            <address className="mt-4 space-y-1 not-italic">
              {site.contact.email ? (
                <p>
                  <a
                    href={`mailto:${site.contact.email}`}
                    className="underline"
                  >
                    {site.contact.email}
                  </a>
                </p>
              ) : null}
              {site.contact.phone ? <p>{site.contact.phone}</p> : null}
              {site.contact.address ? (
                <p className="whitespace-pre-line">{site.contact.address}</p>
              ) : null}
            </address>
          ) : null}
        </div>
        {site.menus.footer.length > 0 ? (
          <nav aria-label="Footer">
            <ul className="space-y-2">
              {site.menus.footer
                .flatMap((item) => [item, ...item.children])
                .map((item) => (
                  <li key={`${item.href}-${item.label}`}>
                    <SmartLink
                      href={item.href}
                      newTab={item.newTab}
                      className="hover:underline"
                    >
                      {item.label}
                    </SmartLink>
                  </li>
                ))}
            </ul>
          </nav>
        ) : null}
        {social.length > 0 ? (
          <ul className="flex flex-wrap gap-4" aria-label="Social media">
            {social.map((link) => (
              <li key={link.url}>
                <SmartLink href={link.url} newTab className="hover:underline">
                  {link.label}
                </SmartLink>
              </li>
            ))}
          </ul>
        ) : null}
      </div>
      <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border/30 px-4 py-4 text-sm sm:px-6">
        <p>
          © {new Date().getFullYear()} {copyright || site.name}
        </p>
        <a href="#top" className="inline-flex min-h-11 items-center underline">
          Back to top
        </a>
      </div>
    </footer>
  );
}
