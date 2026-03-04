import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import Edit from './edit';
import openwpLogo from '../../../assets/openwp.png';
import {
	defaultProvider,
	defaultModel,
	adminContentType,
	adminTone,
} from '../../constants';

const blockIcon = (
	<img src={ openwpLogo } alt="" style={ { width: 30, height: 30 } } />
);

registerBlockType('openwp/ai-content-generator', {
	apiVersion: 2,
	title: __('OpenWP AI Agent', 'openwp'),
	description: __('Generate Gutenberg-ready content from a prompt and insert it into your post or CPT editor.', 'openwp'),
	icon: blockIcon,
	category: 'openwp',
	supports: {
		html: false,
		reusable: false,
	},
	attributes: {
		prompt: { type: 'string', default: '' },
		contentType: { type: 'string', default: adminContentType },
		tone: { type: 'string', default: adminTone },
		provider: { type: 'string', default: defaultProvider },
		model: { type: 'string', default: defaultModel },
		generatedContent: { type: 'string', default: '' },
		// Color palette: empty means "use admin-saved palette"
		colorPalette: { type: 'array', default: [] },
		// When true the block defers to the admin palette instead of its own
		useAdminPalette: { type: 'boolean', default: true },
	},
	edit: Edit,
	save: () => null,
});
