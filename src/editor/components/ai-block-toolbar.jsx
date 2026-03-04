import { useState } from '@wordpress/element';
import { BlockControls } from '@wordpress/block-editor';
import { ToolbarButton } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { serialize } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import AiModifyPopover from './ai-modify-popover';
import openwpLogo from '../../assets/openwp.png';

export default function AiBlockToolbar( { clientId } ) {
	const [ isOpen, setIsOpen ] = useState( false );

	const blockContent = useSelect(
		( select ) => {
			const block = select( 'core/block-editor' ).getBlock( clientId );
			return block ? serialize( block ) : '';
		},
		[ clientId ]
	);

	return (
		<BlockControls group="other">
			<ToolbarButton
				icon={ <img src={ openwpLogo } alt="" style={ { width: 20, height: 20 } } /> }
				label={ __( 'AI Modify', 'openwp' ) }
				onClick={ () => setIsOpen( ( prev ) => ! prev ) }
				isPressed={ isOpen }
			/>
			{ isOpen && (
				<AiModifyPopover
					clientId={ clientId }
					blockContent={ blockContent }
					onClose={ () => setIsOpen( false ) }
				/>
			) }
		</BlockControls>
	);
}
