import Image from 'next/image';
import Link from 'next/link';
import type { Site } from '@websage/contracts';
import { SmartLink } from '@/components/ui/SmartLink';

export function Header({ site }: { site: Site }) {
  const { announcement, announcementLink } = site.header;
  return (
    <header className="border-b border-border/30 bg-background">
      {announcement ? (
        <p className="bg-primary px-4 py-2 text-center text-sm text-on-primary">
          {announcementLink ? (
            <SmartLink
              href={announcementLink.href}
              newTab={announcementLink.newTab}
              className="underline"
            >
              {announcement}
            </SmartLink>
          ) : (
            announcement
          )}
        </p>
      ) : null}
      <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
        <Link href="/" className="font-heading text-xl font-bold">
          {site.branding.logo ? (
            <Image
              src={site.branding.logo.url}
              alt={site.branding.logo.alt || site.name}
              width={site.branding.logo.width}
              height={site.branding.logo.height}
              className="h-10 w-auto"
              priority
            />
          ) : (
            site.name
          )}
        </Link>
        {site.menus.primary.length > 0 ? (
          <nav aria-label="Primary">
            <ul className="flex flex-wrap gap-x-6 gap-y-2">
              {site.menus.primary.map((item) => (
                <li key={`${item.href}-${item.label}`}>
                  <SmartLink
                    href={item.href}
                    newTab={item.newTab}
                    className="font-medium hover:underline"
                  >
                    {item.label}
                  </SmartLink>
                </li>
              ))}
            </ul>
          </nav>
        ) : null}
      </div>
    </header>
  );
}

/** Rendered while site settings stream in; keeps layout stable. */
export function HeaderFallback() {
  return (
    <header
      className="h-[4.5rem] border-b border-border/30 bg-background"
      aria-hidden="true"
    />
  );
}
