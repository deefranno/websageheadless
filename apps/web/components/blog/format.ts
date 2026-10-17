/** Long date in UTC so server and client render the same text. */
export function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en', {
    dateStyle: 'long',
    timeZone: 'UTC',
  });
}
