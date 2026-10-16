interface PreviewBannerProps {
  expired?: boolean;
}

/** Shown on every page while Draft Mode is on. */
export function PreviewBanner({ expired = false }: PreviewBannerProps) {
  return (
    <>
      <meta name="robots" content="noindex" />
      <div
        role="status"
        className="flex flex-wrap items-center justify-center gap-3 bg-foreground px-4 py-2 text-sm text-background"
      >
        <span>
          {expired
            ? 'Your preview link has expired. Use Preview in WordPress again to see unpublished changes.'
            : 'Preview: you are seeing unpublished changes.'}
        </span>
        <form action="/api/preview/exit" method="post">
          <button type="submit" className="min-h-9 rounded-control underline">
            Exit preview
          </button>
        </form>
      </div>
    </>
  );
}
