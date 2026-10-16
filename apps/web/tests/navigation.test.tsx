import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { DesktopNav } from '@/components/navigation/DesktopNav';
import { MobileMenu } from '@/components/navigation/MobileMenu';

vi.mock('next/navigation', () => ({ usePathname: () => '/about' }));

const items = [
  { label: 'Home', href: '/', newTab: false, children: [] },
  {
    label: 'About',
    href: '/about',
    newTab: false,
    children: [
      { label: 'Team', href: '/about/team', newTab: false, children: [] },
    ],
  },
  {
    label: 'Docs',
    href: 'https://docs.example.com',
    newTab: true,
    children: [],
  },
];

describe('MobileMenu', () => {
  it('toggles with aria-expanded, moves focus in and closes on Escape', async () => {
    const user = userEvent.setup();
    render(<MobileMenu items={items} />);
    const button = screen.getByRole('button', { name: /Menu/ });
    expect(button).toHaveAttribute('aria-expanded', 'false');
    expect(screen.queryByRole('link', { name: 'Team' })).toBeNull();

    await user.click(button);
    expect(button).toHaveAttribute('aria-expanded', 'true');
    expect(screen.getByRole('link', { name: 'Team' })).toHaveAttribute(
      'href',
      '/about/team',
    );
    expect(screen.getByRole('link', { name: 'Home' })).toHaveFocus();

    await user.keyboard('{Escape}');
    expect(button).toHaveAttribute('aria-expanded', 'false');
    expect(button).toHaveFocus();
  });
});

describe('DesktopNav', () => {
  it('opens submenus as disclosures and marks the current page', async () => {
    const user = userEvent.setup();
    render(<DesktopNav items={items} />);
    expect(screen.getByRole('link', { name: 'Home' })).not.toHaveAttribute(
      'aria-current',
    );

    const about = screen.getByRole('button', { name: /About/ });
    await user.click(about);
    expect(about).toHaveAttribute('aria-expanded', 'true');
    expect(screen.getByRole('link', { name: 'Team' })).toBeVisible();

    await user.keyboard('{Escape}');
    expect(about).toHaveAttribute('aria-expanded', 'false');
    expect(about).toHaveFocus();
    expect(screen.getByRole('link', { name: 'Docs' })).toHaveAttribute(
      'rel',
      'noopener noreferrer',
    );
  });
});
