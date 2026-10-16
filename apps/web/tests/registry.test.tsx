import page from '@websage/contracts/fixtures/page-hero.json';
import { PageSchema, parseSection } from '@websage/contracts';
import { render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { renderSection } from '@/lib/sections/registry';

const future = parseSection({ type: 'pricing', schemaVersion: 1, key: '1' });

beforeEach(() => {
  vi.stubEnv('WP_API_ORIGIN', 'https://cms.example.com');
});

afterEach(() => vi.unstubAllEnvs());

describe('renderSection', () => {
  it('renders known sections through the registry', () => {
    const hero = parseSection(page.data.sections[0]);
    render(<>{renderSection(hero, 0)}</>);
    expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
  });

  it('skips unknown future sections in production', () => {
    vi.stubEnv('NODE_ENV', 'production');
    expect(renderSection(future, 1)).toBeNull();
  });

  it('flags unknown sections in development', () => {
    vi.stubEnv('NODE_ENV', 'development');
    render(<>{renderSection(future, 1)}</>);
    expect(screen.getByRole('note')).toHaveTextContent('pricing');
  });

  it('never receives invalid known sections: validation rejects the page first', () => {
    const broken = { ...page.data.sections[0]!, heading: undefined };
    expect(
      PageSchema.safeParse({ ...page.data, sections: [broken] }).success,
    ).toBe(false);
  });
});
