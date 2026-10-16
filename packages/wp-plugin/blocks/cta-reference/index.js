import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import metadata from './block.json';
import { ThemePanel } from '../shared/controls';
import { LibrarySelect } from '../shared/library';
import { registerSection } from '../shared/register';

function Edit({ attributes, setAttributes }) {
  const { ctaId, theme } = attributes;
  return (
    <>
      <ThemePanel theme={theme} setAttributes={setAttributes} />
      <section {...useBlockProps()}>
        <LibrarySelect
          postType="ws_cta"
          label={__('Saved call to action', 'websage-headless')}
          value={ctaId}
          onChange={(id) => setAttributes({ ctaId: id })}
        />
      </section>
    </>
  );
}

registerSection(metadata, Edit);
