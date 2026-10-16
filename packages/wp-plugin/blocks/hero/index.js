import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import deprecated from './deprecated';

// Dynamic block: attributes live in the block comment and the plugin's
// serializer turns them into API data. Nothing is saved as HTML.
registerBlockType(metadata.name, {
  edit: Edit,
  save: () => null,
  deprecated,
});
