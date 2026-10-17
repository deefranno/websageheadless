'use client';

import type { MenuItem } from '@websage/contracts';
import { usePathname } from 'next/navigation';
import { useEffect, useId, useRef, useState } from 'react';
import { SmartLink } from '@/components/ui/SmartLink';

function Links({ items, depth = 0 }: { items: MenuItem[]; depth?: number }) {
  return (
    <ul
      className={
        depth > 0 ? 'ml-4 border-l border-border/40 pl-3' : 'space-y-1'
      }
    >
      {items.map((item, index) => (
        <li key={`${item.href}-${index}`}>
          <SmartLink
            href={item.href}
            newTab={item.newTab}
            className="block min-h-11 rounded-control px-3 py-2.5 text-lg hover:bg-muted"
          >
            {item.label}
          </SmartLink>
          {item.children.length > 0 ? (
            <Links items={item.children} depth={depth + 1} />
          ) : null}
        </li>
      ))}
    </ul>
  );
}

/**
 * Narrow-screen menu: a toggle button with aria-expanded/aria-controls; the
 * panel closes on Escape (focus returns to the button), on navigation and
 * when the screen widens.
 */
export function MobileMenu({ items }: { items: MenuItem[] }) {
  const pathname = usePathname();
  // Remember where the menu was opened: navigating elsewhere closes it.
  const [openOn, setOpenOn] = useState<string | null>(null);
  const open = openOn === pathname;
  const setOpen = (value: boolean) => setOpenOn(value ? pathname : null);
  const id = useId();
  const buttonRef = useRef<HTMLButtonElement>(null);
  const panelRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    panelRef.current?.querySelector<HTMLElement>('a')?.focus();
    function onKey(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setOpenOn(null);
        buttonRef.current?.focus();
      }
    }
    const wide = window.matchMedia('(min-width: 768px)');
    const onWide = () => wide.matches && setOpenOn(null);
    document.addEventListener('keydown', onKey);
    wide.addEventListener('change', onWide);
    return () => {
      document.removeEventListener('keydown', onKey);
      wide.removeEventListener('change', onWide);
    };
  }, [open]);

  if (items.length === 0) return null;
  return (
    <div className="md:hidden">
      <button
        ref={buttonRef}
        type="button"
        aria-expanded={open}
        aria-controls={id}
        onClick={() => setOpen(!open)}
        className="inline-flex min-h-11 min-w-11 items-center justify-center gap-2 rounded-control border-2 border-border px-3 font-semibold"
      >
        <span aria-hidden="true">{open ? '✕' : '☰'}</span>
        Menu
      </button>
      <div
        ref={panelRef}
        id={id}
        hidden={!open}
        className="absolute inset-x-0 top-full z-40 border-b border-border/30 bg-background px-4 py-4 shadow-panel"
      >
        <nav aria-label="Main">
          <Links items={items} />
        </nav>
      </div>
    </div>
  );
}
