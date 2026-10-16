import Image from 'next/image';
import Link from 'next/link';
import type { Site } from '@websage/contracts';
import { DesktopNav } from '@/components/navigation/DesktopNav';
import { MobileMenu } from '@/components/navigation/MobileMenu';
import { SmartLink } from '@/components/ui/SmartLink';

export function Header({ site }: { site: Site }) {
  const { announcement, announcementLink } = site.header;
  return (
    <header className="relative border-b border-border/30 bg-background">
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
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <Link
          href="/"
          className="inline-flex min-h-11 items-center font-heading text-xl font-bold"
        >
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
        <DesktopNav items={site.menus.primary} />
        <MobileMenu items={site.menus.primary} />
      </div>
    </header>
  );
}

/** Rendered while site settings stream in; keeps layout stable. */
export function HeaderFallback() {
  return (
    <header
      className="h-[4.25rem] border-b border-border/30 bg-background"
      aria-hidden="true"
    />
  );
}
