'use client';

import dynamic from 'next/dynamic';

/*
 * Code-split boundary: the form and its Zod schema download only on pages
 * that render a contact section. It is still rendered on the server.
 */
export const ContactFormLoader = dynamic(() =>
  import('./ContactForm').then((m) => m.ContactForm),
);
