import Link from 'next/link';
import type { ReactNode } from 'react';

interface SmartLinkProps {
  href: string;
  newTab?: boolean;
  className?: string;
  children: ReactNode;
}

/**
 * Frontend paths use client navigation; absolute URLs are plain anchors.
 * Hrefs are already contract-validated (relative path or HTTPS).
 */
export function SmartLink({
  href,
  newTab,
  className,
  children,
}: SmartLinkProps) {
  const target = newTab ? '_blank' : undefined;
  const rel = newTab ? 'noopener noreferrer' : undefined;
  if (href.startsWith('/')) {
    return (
      <Link href={href} className={className} target={target} rel={rel}>
        {children}
      </Link>
    );
  }
  return (
    <a href={href} className={className} target={target} rel={rel}>
      {children}
    </a>
  );
}
