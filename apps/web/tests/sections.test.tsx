import pageSections from '@websage/contracts/fixtures/page-sections.json';
import { PageSchema } from '@websage/contracts';
import { render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { renderSection } from '@/lib/sections/registry';

vi.mock('next/navigation', () => ({ usePathname: () => '/' }));

const page = PageSchema.parse(pageSections.data);

beforeEach(() => {
  vi.stubEnv('WP_API_ORIGIN', 'https://cms.example.com');
});

describe('every section type renders', () => {
  it('renders the whole fixture page with one h1 and section headings', () => {
    const { container } = render(<>{page.sections.map(renderSection)}</>);

    expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
    expect(
      screen.getByRole('heading', { level: 1, name: 'Services' }),
    ).toBeInTheDocument();
    for (const name of [
      'How we work',
      'What you get',
      'Clients say',
      'Questions',
      'Pricing',
      'Ready?',
      'Contact us',
    ]) {
      expect(
        screen.getByRole('heading', { level: 2, name }),
      ).toBeInTheDocument();
    }
    expect(container.querySelectorAll('[data-section]')).toHaveLength(
      page.sections.length,
    );
  });

  it('text-image shows sanitized body and its image', () => {
    render(<>{page.sections.map(renderSection)}</>);
    const region = screen.getByRole('region', { name: 'How we work' });
    expect(within(region).getByText('Discovery')).toBeInTheDocument();
    expect(
      within(region).getByRole('img', { name: 'Our team' }),
    ).toBeInTheDocument();
  });

  it('feature grid lists items with optional links', () => {
    render(<>{page.sections.map(renderSection)}</>);
    const region = screen.getByRole('region', { name: 'What you get' });
    expect(within(region).getAllByRole('listitem')).toHaveLength(2);
    expect(
      within(region).getByRole('heading', { level: 3, name: 'Strategy' }),
    ).toBeInTheDocument();
    expect(
      within(region).getByRole('link', { name: 'Learn more' }),
    ).toHaveAttribute('href', '/strategy');
  });

  it('testimonials use figure, blockquote and caption', () => {
    render(<>{page.sections.map(renderSection)}</>);
    const region = screen.getByRole('region', { name: 'Clients say' });
    expect(within(region).getByText('“They delivered.”')).toBeInTheDocument();
    expect(within(region).getByText('Director, Acme')).toBeInTheDocument();
    expect(region.querySelector('figure blockquote')).not.toBeNull();
  });

  it('FAQ uses native disclosure widgets', () => {
    render(<>{page.sections.map(renderSection)}</>);
    const region = screen.getByRole('region', { name: 'Questions' });
    const details = region.querySelector('details');
    expect(details).not.toBeNull();
    expect(details?.open).toBe(false);
    expect(within(region).getByText('How long?').tagName).toBe('SUMMARY');
  });

  it('CTA and contact sections render their actions', () => {
    render(<>{page.sections.map(renderSection)}</>);
    expect(screen.getByRole('link', { name: 'Contact' })).toHaveAttribute(
      'href',
      '/contact',
    );
    const contact = screen.getByRole('region', { name: 'Contact us' });
    expect(within(contact).getByLabelText('Name')).toBeInTheDocument();
    expect(
      within(contact).getByRole('button', { name: 'Send' }),
    ).toBeInTheDocument();
  });

  it('skips empty testimonial and FAQ sections', () => {
    const empty = page.sections
      .filter((s) => s.type === 'faq' || s.type === 'testimonials')
      .map((s) => ({ ...s, items: [] }));
    const { container } = render(<>{empty.map(renderSection)}</>);
    expect(container).toBeEmptyDOMElement();
  });
});
