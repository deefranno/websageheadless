import { __ } from '@wordpress/i18n';
import {
  InspectorControls,
  RichText,
  useBlockProps,
} from '@wordpress/block-editor';
import metadata from './block.json';
import { ImagePanel, LinkPanel, PlainField } from '../shared/controls';
import { registerSection } from '../shared/register';

function Edit({ attributes, setAttributes }) {
  const { title, body, imageId, link } = attributes;
  return (
    <>
      <InspectorControls>
        <ImagePanel
          imageId={imageId}
          onChange={(id) => setAttributes({ imageId: id })}
        />
        <LinkPanel
          id="link"
          title={__('Link', 'websage-headless')}
          value={link}
          onChange={(value) => setAttributes({ link: value })}
        />
      </InspectorControls>
      <div {...useBlockProps()}>
        <PlainField
          label={__('Title', 'websage-headless')}
          value={title}
          onChange={(value) => setAttributes({ title: value })}
        />
        <RichText
          tagName="p"
          aria-label={__('Description', 'websage-headless')}
          placeholder={__('Description…', 'websage-headless')}
          allowedFormats={['core/bold', 'core/italic', 'core/link']}
          value={body}
          onChange={(value) => setAttributes({ body: value })}
        />
      </div>
    </>
  );
}

registerSection(metadata, Edit);
