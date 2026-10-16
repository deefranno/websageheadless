import { __ } from '@wordpress/i18n';
import {
  InspectorControls,
  LinkControl,
  MediaUpload,
  MediaUploadCheck,
} from '@wordpress/block-editor';
import {
  Button,
  PanelBody,
  SelectControl,
  TextControl,
} from '@wordpress/components';

export const THEMES = [
  { label: __('Default', 'websage-headless'), value: 'default' },
  { label: __('Muted', 'websage-headless'), value: 'muted' },
  { label: __('Brand', 'websage-headless'), value: 'brand' },
];

/** Inspector panel with the colour theme select. */
export function ThemePanel({ theme, setAttributes, children }) {
  return (
    <InspectorControls>
      <PanelBody title={__('Appearance', 'websage-headless')}>
        <SelectControl
          __next40pxDefaultSize
          label={__('Colour theme', 'websage-headless')}
          value={theme}
          options={THEMES}
          onChange={(value) => setAttributes({ theme: value })}
        />
      </PanelBody>
      {children}
    </InspectorControls>
  );
}

/** Media library picker storing only the attachment ID. */
export function ImagePanel({ imageId, onChange }) {
  return (
    <PanelBody title={__('Image', 'websage-headless')}>
      <MediaUploadCheck>
        <MediaUpload
          allowedTypes={['image']}
          value={imageId}
          onSelect={(media) => onChange(media.id)}
          render={({ open }) => (
            <Button variant="secondary" onClick={open}>
              {imageId
                ? __('Replace image', 'websage-headless')
                : __('Choose image', 'websage-headless')}
            </Button>
          )}
        />
      </MediaUploadCheck>
      {imageId ? (
        <Button
          variant="link"
          isDestructive
          onClick={() => onChange(undefined)}
        >
          {__('Remove image', 'websage-headless')}
        </Button>
      ) : null}
    </PanelBody>
  );
}

/** One optional link stored as { label, href, newTab }. */
export function LinkPanel({ id, title, value, onChange, initialOpen = false }) {
  const link = value ?? { label: '', href: '', newTab: false };
  return (
    <PanelBody title={title} initialOpen={initialOpen}>
      <TextControl
        __next40pxDefaultSize
        label={__('Button text', 'websage-headless')}
        value={link.label}
        onChange={(label) => onChange({ ...link, label })}
      />
      <LinkControl
        key={id}
        value={{ url: link.href, opensInNewTab: link.newTab }}
        onChange={({ url = '', opensInNewTab = false }) =>
          onChange({ ...link, href: url, newTab: opensInNewTab })
        }
        onRemove={() => onChange(undefined)}
        settings={[
          {
            id: 'opensInNewTab',
            title: __('Open in new tab', 'websage-headless'),
          },
        ]}
      />
    </PanelBody>
  );
}

/** Plain-text field shown on the canvas. */
export function PlainField({ label, value, onChange }) {
  return (
    <TextControl
      __next40pxDefaultSize
      label={label}
      value={value ?? ''}
      onChange={onChange}
    />
  );
}
