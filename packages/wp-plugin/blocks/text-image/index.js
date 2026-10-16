import { __ } from '@wordpress/i18n';
import { RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import metadata from './block.json';
import { ImagePanel, PlainField, ThemePanel } from '../shared/controls';
import { registerSection } from '../shared/register';

function Edit({ attributes, setAttributes }) {
  const { heading, body, imageId, position, theme } = attributes;
  return (
    <>
      <ThemePanel theme={theme} setAttributes={setAttributes}>
        <PanelBody title={__('Layout', 'websage-headless')}>
          <SelectControl
            __next40pxDefaultSize
            label={__('Image position', 'websage-headless')}
            value={position}
            options={[
              { label: __('Right', 'websage-headless'), value: 'right' },
              { label: __('Left', 'websage-headless'), value: 'left' },
            ]}
            onChange={(value) => setAttributes({ position: value })}
          />
        </PanelBody>
        <ImagePanel
          imageId={imageId}
          onChange={(id) => setAttributes({ imageId: id })}
        />
      </ThemePanel>
      <section {...useBlockProps({ className: `wsh-theme-${theme}` })}>
        <PlainField
          label={__('Heading', 'websage-headless')}
          value={heading}
          onChange={(value) => setAttributes({ heading: value })}
        />
        <RichText
          tagName="p"
          aria-label={__('Text', 'websage-headless')}
          placeholder={__('Text…', 'websage-headless')}
          allowedFormats={['core/bold', 'core/italic', 'core/link']}
          value={body}
          onChange={(value) => setAttributes({ body: value })}
        />
      </section>
    </>
  );
}

registerSection(metadata, Edit);
