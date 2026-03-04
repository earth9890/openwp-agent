/**
 * Parse Gutenberg block markup and auto-recover any invalid blocks.
 *
 * Uses `parse()` from @wordpress/blocks, then walks the tree. Any block
 * that fails validation (`block.isValid === false`) is recreated via
 * `createBlock()`, which always produces spec-compliant output.
 *
 * Freeform blocks (null name) and unregistered block types are left as-is
 * since `createBlock()` cannot handle them.
 *
 * @param {string} content Gutenberg block markup.
 * @return {Array} Array of valid parsed blocks.
 */
import { parse, createBlock, getBlockType } from '@wordpress/blocks';

function recoverBlock(block) {
	const innerBlocks = block.innerBlocks?.length
		? block.innerBlocks.map(recoverBlock)
		: [];

	if (block.isValid) {
		return { ...block, innerBlocks };
	}

	// Skip recovery for freeform blocks or unregistered block types -
	// createBlock() would throw for these.
	if (!block.name || !getBlockType(block.name)) {
		return { ...block, innerBlocks };
	}

	return createBlock(block.name, block.attributes, innerBlocks);
}

export function parseAndRecover(content) {
	const blocks = parse(content);
	if (!blocks || !blocks.length) {
		return blocks;
	}
	return blocks.map(recoverBlock);
}
