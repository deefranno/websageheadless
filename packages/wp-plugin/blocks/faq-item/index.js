import { __ } from '@wordpress/i18n';
import { RichText, useBlockProps } from '@wordpress/block-editor';
import metadata from './block.json';
import { PlainField } from '../shared/controls';
import { registerSection } from '../shared/register';

function Edit({ attributes, setAttributes }) {
  return (
    <div {...useBlockProps()}>
      <PlainField
        label={__('Question', 'websage-headless')}
        value={attributes.question}
        onChange={(value) => setAttributes({ question: value })}
      />
      <RichText
        tagName="p"
        aria-label={__('Answer', 'websage-headless')}
        placeholder={__('Answer…', 'websage-headless')}
        allowedFormats={['core/bold', 'core/italic', 'core/link']}
        value={attributes.answer}
        onChange={(value) => setAttributes({ answer: value })}
      />
    </div>
  );
}

registerSection(metadata, Edit);
