import page from '@websage/contracts/fixtures/page-hero.json';
import { HeroSectionSchema, type HeroSection } from '@websage/contracts';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { Hero } from '@/components/sections/Hero';

const base = HeroSectionSchema.parse(page.data.sections[0]);

beforeEach(() => {
  vi.stubEnv('WP_API_ORIGIN', 'https://cms.example.com');
});

describe('Hero', () => {
  it('renders the contract fixture with one h1 and a client-side link', () => {
    render(<Hero section={base} isFirst />);

    expect(
      screen.getByRole('heading', { level: 1, name: 'Build with confidence' }),
    ).toBeInTheDocument();
    expect(screen.getByText('Welcome')).toBeInTheDocument();
    expect(screen.getByText('Made for your team.')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Contact' })).toHaveAttribute(
      'href',
      '/contact',
    );
  });

  it('uses h2 when not the first section', () => {
    render(<Hero section={base} isFirst={false} />);
    expect(screen.getByRole('heading', { level: 2 })).toBeInTheDocument();
  });

  it('renders image alt text and hardened new-tab links', () => {
    const section: HeroSection = {
      ...base,
      image: {
        url: 'https://cms.example.com/wp-content/uploads/hero.jpg',
        alt: 'Team at work',
        width: 1200,
        height: 800,
      },
      secondary: {
        label: 'Docs',
        href: 'https://docs.example.com',
        newTab: true,
      },
    };
    render(<Hero section={section} isFirst />);

    expect(
      screen.getByRole('img', { name: 'Team at work' }),
    ).toBeInTheDocument();
    const docs = screen.getByRole('link', { name: 'Docs' });
    expect(docs).toHaveAttribute('target', '_blank');
    expect(docs).toHaveAttribute('rel', 'noopener noreferrer');
  });

  it('sanitizes body HTML even after contract validation', () => {
    const { container } = render(
      <Hero
        section={{
          ...base,
          body: '<p>Safe<img src=x onerror="alert(1)"><script>x()</script></p>',
        }}
        isFirst
      />,
    );

    expect(container.querySelector('script')).toBeNull();
    expect(container.innerHTML).not.toContain('onerror');
    expect(screen.getByText('Safe')).toBeInTheDocument();
  });
});
