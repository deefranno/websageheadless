import { __ } from '@wordpress/i18n';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import metadata from './block.json';
import { PlainField, ThemePanel } from '../shared/controls';
import { registerSection } from '../shared/register';

function Edit({ attributes, setAttributes }) {
  const { heading, theme } = attributes;
  const innerProps = useInnerBlocksProps(
    {},
    {
      allowedBlocks: ['websage/testimonial-reference'],
      template: [['websage/testimonial-reference']],
    },
  );
  return (
    <>
      <ThemePanel theme={theme} setAttributes={setAttributes} />
      <section {...useBlockProps({ className: `wsh-theme-${theme}` })}>
        <PlainField
          label={__('Heading', 'websage-headless')}
          value={heading}
          onChange={(value) => setAttributes({ heading: value })}
        />
        <div {...innerProps} />
      </section>
    </>
  );
}

registerSection(metadata, Edit, { parent: true });
