import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import metadata from './block.json';
import { LibrarySelect } from '../shared/library';
import { registerSection } from '../shared/register';

function Edit({ attributes, setAttributes }) {
  return (
    <div {...useBlockProps()}>
      <LibrarySelect
        postType="ws_testimonial"
        label={__('Testimonial', 'websage-headless')}
        value={attributes.testimonialId}
        onChange={(id) => setAttributes({ testimonialId: id })}
      />
    </div>
  );
}

registerSection(metadata, Edit);
