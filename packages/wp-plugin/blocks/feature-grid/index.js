import { __ } from '@wordpress/i18n';
import {
  RichText,
  useBlockProps,
  useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import metadata from './block.json';
import { PlainField, ThemePanel } from '../shared/controls';
import { registerSection } from '../shared/register';

function Edit({ attributes, setAttributes }) {
  const { heading, introduction, columns, theme } = attributes;
  const blockProps = useBlockProps({ className: `wsh-theme-${theme}` });
  const innerProps = useInnerBlocksProps(
    {},
    {
      allowedBlocks: ['websage/feature'],
      template: [['websage/feature'], ['websage/feature'], ['websage/feature']],
      orientation: 'horizontal',
    },
  );
  return (
    <>
      <ThemePanel theme={theme} setAttributes={setAttributes}>
        <PanelBody title={__('Layout', 'websage-headless')}>
          <SelectControl
            __next40pxDefaultSize
            label={__('Columns', 'websage-headless')}
            value={String(columns)}
            options={[2, 3, 4].map((n) => ({
              label: String(n),
              value: String(n),
            }))}
            onChange={(value) => setAttributes({ columns: Number(value) })}
          />
        </PanelBody>
      </ThemePanel>
      <section {...blockProps}>
        <PlainField
          label={__('Heading', 'websage-headless')}
          value={heading}
          onChange={(value) => setAttributes({ heading: value })}
        />
        <RichText
          tagName="p"
          aria-label={__('Introduction', 'websage-headless')}
          placeholder={__('Introduction…', 'websage-headless')}
          allowedFormats={['core/bold', 'core/italic', 'core/link']}
          value={introduction}
          onChange={(value) => setAttributes({ introduction: value })}
        />
        <div {...innerProps} />
      </section>
    </>
  );
}

registerSection(metadata, Edit, { parent: true });
