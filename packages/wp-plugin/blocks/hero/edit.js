import { __ } from '@wordpress/i18n';
import {
  InspectorControls,
  LinkControl,
  MediaUpload,
  MediaUploadCheck,
  RichText,
  useBlockProps,
} from '@wordpress/block-editor';
import {
  Button,
  PanelBody,
  SelectControl,
  TextControl,
} from '@wordpress/components';

const THEMES = [
  { label: __('Default', 'websage-headless'), value: 'default' },
  { label: __('Muted', 'websage-headless'), value: 'muted' },
  { label: __('Brand', 'websage-headless'), value: 'brand' },
];

/**
 * Edits one optional link stored as { label, href, newTab }.
 */
function LinkField({ id, title, value, onChange }) {
  const link = value ?? { label: '', href: '', newTab: false };
  return (
    <PanelBody title={title} initialOpen={false}>
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

export default function Edit({ attributes, setAttributes }) {
  const { eyebrow, heading, body, imageId, primary, secondary, theme } =
    attributes;
  const blockProps = useBlockProps({
    className: `wsh-hero wsh-theme-${theme}`,
  });

  return (
    <>
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
        <PanelBody title={__('Image', 'websage-headless')}>
          <MediaUploadCheck>
            <MediaUpload
              allowedTypes={['image']}
              value={imageId}
              onSelect={(media) => setAttributes({ imageId: media.id })}
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
              onClick={() => setAttributes({ imageId: undefined })}
            >
              {__('Remove image', 'websage-headless')}
            </Button>
          ) : null}
        </PanelBody>
        <LinkField
          id="primary"
          title={__('Primary button', 'websage-headless')}
          value={primary}
          onChange={(value) => setAttributes({ primary: value })}
        />
        <LinkField
          id="secondary"
          title={__('Secondary button', 'websage-headless')}
          value={secondary}
          onChange={(value) => setAttributes({ secondary: value })}
        />
      </InspectorControls>
      <section {...blockProps}>
        <TextControl
          __next40pxDefaultSize
          label={__('Eyebrow', 'websage-headless')}
          value={eyebrow}
          onChange={(value) => setAttributes({ eyebrow: value })}
        />
        <TextControl
          __next40pxDefaultSize
          label={__('Heading', 'websage-headless')}
          value={heading}
          onChange={(value) => setAttributes({ heading: value })}
        />
        <RichText
          tagName="p"
          aria-label={__('Body text', 'websage-headless')}
          placeholder={__('Supporting text…', 'websage-headless')}
          allowedFormats={['core/bold', 'core/italic', 'core/link']}
          value={body}
          onChange={(value) => setAttributes({ body: value })}
        />
      </section>
    </>
  );
}
