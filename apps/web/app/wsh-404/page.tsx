import { notFound } from 'next/navigation';

/**
 * Target of proxy.ts rewrites for paths absent from the routes list.
 * notFound() runs before anything streams, so the response is a real 404.
 */
export default function MissingPage() {
  notFound();
}
