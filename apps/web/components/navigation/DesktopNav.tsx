'use client';

import type { MenuItem } from '@websage/contracts';
import { usePathname } from 'next/navigation';
import { useEffect, useRef, useState, type KeyboardEvent } from 'react';
import { SmartLink } from '@/components/ui/SmartLink';

/**
 * Primary navigation for wide screens. Items with children use the
 * disclosure pattern: a button toggles the submenu (Enter/Space), Escape
 * closes it and returns focus, and it closes on navigation or outside click.
 */
export function DesktopNav({ items }: { items: MenuItem[] }) {
  const pathname = usePathname();
  // Submenu index plus the path it was opened on: navigation closes it.
  const [state, setState] = useState<{ index: number; path: string } | null>(
    null,
  );
  const open = state?.path === pathname ? state.index : null;
  const setOpen = (index: number | null) =>
    setState(index === null ? null : { index, path: pathname });
  const navRef = useRef<HTMLElement>(null);
  const buttons = useRef<(HTMLButtonElement | null)[]>([]);

  useEffect(() => {
    function onPointer(event: PointerEvent) {
      if (!navRef.current?.contains(event.target as Node)) setState(null);
    }
    document.addEventListener('pointerdown', onPointer);
    return () => document.removeEventListener('pointerdown', onPointer);
  }, []);

  function onKeyDown(event: KeyboardEvent, index: number) {
    if (event.key === 'Escape' && open === index) {
      setOpen(null);
      buttons.current[index]?.focus();
    }
  }

  return (
    <nav ref={navRef} aria-label="Main" className="hidden md:block">
      <ul className="flex items-center gap-x-6">
        {items.map((item, index) => {
          const current = item.href === pathname;
          if (item.children.length === 0) {
            return (
              <li key={`${item.href}-${index}`}>
                <SmartLink
                  href={item.href}
                  newTab={item.newTab}
                  className="inline-flex min-h-11 items-center font-medium underline-offset-4 hover:underline aria-[current=page]:underline"
                  aria-current={current ? 'page' : undefined}
                >
                  {item.label}
                </SmartLink>
              </li>
            );
          }
          const menuId = `nav-submenu-${index}`;
          return (
            <li
              key={`${item.href}-${index}`}
              className="relative"
              onKeyDown={(event) => onKeyDown(event, index)}
            >
              <button
                ref={(element) => {
                  buttons.current[index] = element;
                }}
                type="button"
                aria-expanded={open === index}
                aria-controls={menuId}
                onClick={() => setOpen(open === index ? null : index)}
                className="inline-flex min-h-11 items-center gap-1 font-medium"
              >
                {item.label}
                <span aria-hidden="true">▾</span>
              </button>
              <ul
                id={menuId}
                hidden={open !== index}
                className="absolute right-0 z-40 mt-1 min-w-56 rounded-panel border border-border/30 bg-background p-2 shadow-panel"
              >
                <li>
                  <SmartLink
                    href={item.href}
                    newTab={item.newTab}
                    className="block rounded-control px-3 py-2 font-semibold hover:bg-muted"
                  >
                    {item.label}
                  </SmartLink>
                </li>
                {item.children.map((child, childIndex) => (
                  <li key={`${child.href}-${childIndex}`}>
                    <SmartLink
                      href={child.href}
                      newTab={child.newTab}
                      className="block rounded-control px-3 py-2 hover:bg-muted"
                    >
                      {child.label}
                    </SmartLink>
                  </li>
                ))}
              </ul>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
