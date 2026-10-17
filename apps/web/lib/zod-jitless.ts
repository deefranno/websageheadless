import { config } from 'zod';

/*
 * Imported before any schema module in client code: Zod otherwise probes
 * `new Function()` when schemas are built, which the CSP (no 'unsafe-eval')
 * reports as a violation. Zod then validates without code generation.
 */
config({ jitless: true });
