import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';

/**
 * Registers a dynamic block. Leaf blocks save nothing; parent blocks save
 * only their inner blocks so children persist in post content.
 */
export function registerSection(
  metadata,
  Edit,
  { parent = false, deprecated = [] } = {},
) {
  registerBlockType(metadata.name, {
    edit: Edit,
    save: parent ? () => <InnerBlocks.Content /> : () => null,
    deprecated,
  });
}
