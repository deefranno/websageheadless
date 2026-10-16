import type { Metadata } from 'next';
import { connection } from 'next/server';
import { getSite } from '@/lib/api/fetchers';
import { siteMetadata } from '@/lib/seo/metadata';
import { Suspense, type ReactNode } from 'react';
import { HeaderFallback } from '@/components/layout/Header';
import { SiteFooter, SiteHeader } from '@/components/layout/SiteChrome';
import { fontVariables } from '@/config/fonts';
import './globals.css';

export async function generateMetadata(): Promise<Metadata> {
  await connection();
  try {
    return siteMetadata(await getSite());
  } catch {
    return { title: { default: 'Website', template: '%s' } };
  }
}

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="en" className={fontVariables} id="top">
      <body className="flex min-h-dvh flex-col">
        <a
          href="#main"
          className="sr-only z-50 bg-background px-4 py-2 focus:not-sr-only focus:fixed focus:left-4 focus:top-4"
        >
          Skip to content
        </a>
        <Suspense fallback={<HeaderFallback />}>
          <SiteHeader />
        </Suspense>
        <main id="main" tabIndex={-1} className="flex-1">
          {children}
        </main>
        <Suspense fallback={null}>
          <SiteFooter />
        </Suspense>
      </body>
    </html>
  );
}
