import './blocks/ai-content-generator';
import './index.css';

import { dispatch, select, subscribe } from '@wordpress/data';
import { store as keyboardShortcutsStore } from '@wordpress/keyboard-shortcuts';
import { createBlock } from '@wordpress/blocks';
import { registerPlugin } from '@wordpress/plugins';
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import GlobalAIModal from './global-modal';
import AiBlockToolbar from './components/ai-block-toolbar';

// Register the keyboard shortcut so useShortcut can bind to it.
// Cmd+Shift+Space on Mac / Ctrl+Shift+Space on Windows/Linux.
dispatch( keyboardShortcutsStore ).registerShortcut( {
	name: 'openwp/ai-generator',
	category: 'block',
	description: __( 'Open OpenWP AI Agent', 'openwp' ),
	keyCombination: {
		modifier: 'primaryShift',
		character: ' ',
	},
} );

// Mount the modal component globally in the editor via the plugin API.
// The component renders nothing until the shortcut is triggered.
registerPlugin( 'openwp-ai-global', { render: GlobalAIModal } );

// Add AI Modify toolbar button to every block (except the AI generator block).
const withAiModifyToolbar = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		if (
			props.name === 'openwp/ai-content-generator' ||
			! props.isSelected
		) {
			return <BlockEdit { ...props } />;
		}

		return (
			<>
				<BlockEdit { ...props } />
				<AiBlockToolbar clientId={ props.clientId } />
			</>
		);
	};
}, 'withAiModifyToolbar' );

addFilter(
	'editor.BlockEdit',
	'openwp/ai-modify-toolbar',
	withAiModifyToolbar
);

// Auto-insert the AI Agent block at the top of the editor if not already present.
// Runs once after the editor has finished loading blocks.
{
	let inserted = false;
	const unsubscribe = subscribe( () => {
		if ( inserted ) {
			return;
		}
		const blocks = select( 'core/block-editor' ).getBlocks();
		// Wait until the editor has resolved its blocks.
		if ( ! blocks ) {
			return;
		}
		inserted = true;
		unsubscribe();

		const alreadyPresent = blocks.some(
			( block ) => block.name === 'openwp/ai-content-generator'
		);
		if ( ! alreadyPresent ) {
			const aiBlock = createBlock( 'openwp/ai-content-generator' );
			dispatch( 'core/block-editor' ).insertBlocks( aiBlock, 0 );
		}
	} );
}
