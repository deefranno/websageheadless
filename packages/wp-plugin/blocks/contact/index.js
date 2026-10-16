import { __ } from '@wordpress/i18n';
import { RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import metadata from './block.json';
import { PlainField, ThemePanel } from '../shared/controls';
import { registerSection } from '../shared/register';

function Edit({ attributes, setAttributes }) {
  const { heading, body, submitLabel, successMessage, theme } = attributes;
  return (
    <>
      <ThemePanel theme={theme} setAttributes={setAttributes}>
        <PanelBody title={__('Form text', 'websage-headless')}>
          <PlainField
            label={__('Button text', 'websage-headless')}
            value={submitLabel}
            onChange={(value) => setAttributes({ submitLabel: value })}
          />
          <PlainField
            label={__('Thank-you message', 'websage-headless')}
            value={successMessage}
            onChange={(value) => setAttributes({ successMessage: value })}
          />
        </PanelBody>
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
          placeholder={__('Text above the form…', 'websage-headless')}
          allowedFormats={['core/bold', 'core/italic', 'core/link']}
          value={body}
          onChange={(value) => setAttributes({ body: value })}
        />
        <p>
          <em>
            {__(
              'Name, email, message and consent fields appear here on the website.',
              'websage-headless',
            )}
          </em>
        </p>
      </section>
    </>
  );
}

registerSection(metadata, Edit);
