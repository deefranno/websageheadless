import { __ } from '@wordpress/i18n';
import { RichText, useBlockProps } from '@wordpress/block-editor';
import { Notice } from '@wordpress/components';
import metadata from './block.json';
import { LinkPanel, PlainField, ThemePanel } from '../shared/controls';
import { registerSection } from '../shared/register';

function Edit({ attributes, setAttributes }) {
  const { heading, body, link, theme } = attributes;
  return (
    <>
      <ThemePanel theme={theme} setAttributes={setAttributes}>
        <LinkPanel
          id="link"
          title={__('Button', 'websage-headless')}
          value={link}
          initialOpen
          onChange={(value) => setAttributes({ link: value })}
        />
      </ThemePanel>
      <section {...useBlockProps({ className: `wsh-theme-${theme}` })}>
        {!link?.label || !link?.href ? (
          <Notice status="warning" isDismissible={false}>
            {__(
              'Add button text and a link in the sidebar. Without them this section is not shown.',
              'websage-headless',
            )}
          </Notice>
        ) : null}
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
