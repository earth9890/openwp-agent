/**
 * BSF Plugin Add-ons Data.
 *
 * @package OpenWP
 */

import { __ } from '@wordpress/i18n';
import {
	SureFormsLogo,
	SpectraLogo,
	SureDashLogo,
	SureCartLogo,
	SureTriggersLogo,
	StartersTemplatesLogo,
	PrestoPlayerLogo,
	CartFlowsLogo,
	SureFeedbackLogo,
	AbandonedCartsLogo,
	UAE,
	SureRankLogo,
	SureMailLogo,
	AstraThemeLogo,
} from '../../assets/icons';

export const pluginAddons = [
	{
		slug: 'sureforms',
		title: __( 'SureForms', 'openwp' ),
		description: __( 'Best no code WordPress form builder.', 'openwp' ),
		type: 'plugin',
		init: 'sureforms/sureforms.php',
		icon: SureFormsLogo,
	},
	{
		slug: 'ultimate-addons-for-gutenberg',
		title: __( 'Spectra', 'openwp' ),
		description: __( 'Free WordPress Page Builder.', 'openwp' ),
		type: 'plugin',
		init: 'ultimate-addons-for-gutenberg/ultimate-addons-for-gutenberg.php',
		icon: SpectraLogo,
	},
	{
		slug: 'suredash',
		title: __( 'SureDash', 'openwp' ),
		description: __( 'Manage your business with SureDash.', 'openwp' ),
		type: 'plugin',
		init: 'suredash/suredash.php',
		icon: SureDashLogo,
	},
	{
		slug: 'surecart',
		title: __( 'SureCart', 'openwp' ),
		description: __( 'The new way to sell on WordPress.', 'openwp' ),
		type: 'plugin',
		init: 'surecart/surecart.php',
		icon: SureCartLogo,
	},
	{
		slug: 'suretriggers',
		title: __( 'OttoKit', 'openwp' ),
		description: __( 'Automate your WordPress setup.', 'openwp' ),
		type: 'plugin',
		init: 'suretriggers/suretriggers.php',
		icon: SureTriggersLogo,
	},
	{
		slug: 'astra-sites',
		title: __( 'Starter Templates', 'openwp' ),
		description: __( 'Launch sites quickly with Starter Templates.', 'openwp' ),
		type: 'plugin',
		init: 'astra-sites/astra-sites.php',
		icon: StartersTemplatesLogo,
	},
	{
		slug: 'presto-player',
		title: __( 'Presto Player', 'openwp' ),
		description: __( 'Enhance video delivery with Presto Player.', 'openwp' ),
		type: 'plugin',
		init: 'presto-player/presto-player.php',
		icon: PrestoPlayerLogo,
	},
	{
		slug: 'cartflows',
		title: __( 'CartFlows', 'openwp' ),
		description: __( 'Boost conversions with CartFlows.', 'openwp' ),
		type: 'plugin',
		init: 'cartflows/cartflows.php',
		icon: CartFlowsLogo,
	},
	{
		slug: 'projecthuddle-child-site',
		title: __( 'SureFeedback', 'openwp' ),
		description: __( 'Collect user feedback directly on your site.', 'openwp' ),
		type: 'plugin',
		init: 'projecthuddle-child-site/ph-child.php',
		icon: SureFeedbackLogo,
	},
	{
		slug: 'woo-cart-abandonment-recovery',
		title: __( 'Cart Abandonment Recovery', 'openwp' ),
		description: __( 'Recover lost sales with automated abandoned cart emails.', 'openwp' ),
		type: 'plugin',
		init: 'woo-cart-abandonment-recovery/woo-cart-abandonment-recovery.php',
		icon: AbandonedCartsLogo,
	},
	{
		slug: 'header-footer-elementor',
		title: __( 'Ultimate Addons for Elementor', 'openwp' ),
		description: __( 'Build modern websites with elementor addons.', 'openwp' ),
		type: 'plugin',
		init: 'header-footer-elementor/header-footer-elementor.php',
		icon: UAE,
	},
	{
		slug: 'surerank',
		title: __( 'SureRank', 'openwp' ),
		description: __( 'Optimize your website for search engines.', 'openwp' ),
		type: 'plugin',
		init: 'surerank/surerank.php',
		icon: SureRankLogo,
	},
	{
		slug: 'suremails',
		title: __( 'SureMails', 'openwp' ),
		description: __( 'Reliable email delivery for WordPress.', 'openwp' ),
		type: 'plugin',
		init: 'suremails/suremails.php',
		icon: SureMailLogo,
	},
	{
		slug: 'astra',
		title: __( 'Astra', 'openwp' ),
		description: __( 'A fast and customizable WordPress theme.', 'openwp' ),
		type: 'theme',
		init: 'astra',
		icon: AstraThemeLogo,
	},
];

export const recommendedSlugs = [
	'sureforms',
	'ultimate-addons-for-gutenberg',
	'surecart',
	'suretriggers',
	'suredash',
	'cartflows',
	'presto-player',
	'astra-sites',
];
