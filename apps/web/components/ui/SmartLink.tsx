import Link from 'next/link';
import type { AnchorHTMLAttributes, ReactNode } from 'react';

interface SmartLinkProps extends Omit<
  AnchorHTMLAttributes<HTMLAnchorElement>,
  'href' | 'target' | 'rel'
> {
  href: string;
  newTab?: boolean;
  children: ReactNode;
}

/**
 * Frontend paths use client navigation; absolute URLs are plain anchors.
 * Hrefs are already contract-validated (relative path or HTTPS).
 */
export function SmartLink({ href, newTab, children, ...rest }: SmartLinkProps) {
  const target = newTab ? '_blank' : undefined;
  const rel = newTab ? 'noopener noreferrer' : undefined;
  if (href.startsWith('/')) {
    return (
      <Link href={href} target={target} rel={rel} {...rest}>
        {children}
      </Link>
    );
  }
  return (
    <a href={href} target={target} rel={rel} {...rest}>
      {children}
    </a>
  );
}
