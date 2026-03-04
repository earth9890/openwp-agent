import { __ } from '@wordpress/i18n';
import { SECTION_TYPES, FULL_PAGE_TYPES } from '../constants';

/**
 * Minimal streaming indicator shown while blocks are being inserted into the editor.
 *
 * @param {Object} props
 * @param {string} props.contentType The content_type being generated.
 */
export default function GenerationProgress( { contentType } ) {
	const isPage = typeof contentType === 'string' && contentType.startsWith( 'full_' );
	const allTypes = [ ...SECTION_TYPES, ...FULL_PAGE_TYPES ];
	const typeLabel = ( allTypes.find( ( t ) => t.value === contentType ) || {} ).label || contentType;

	const statusText = isPage
		? __( 'Building full page\u2026', 'openwp' )
		: __( 'Generating section\u2026', 'openwp' );

	return (
		<div className="flex flex-col gap-2">
			<div className="flex items-center gap-2">
				<span className="relative flex h-2.5 w-2.5">
					<span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#0057ff] opacity-75" />
					<span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-[#0057ff]" />
				</span>
				<span className="text-xs font-semibold text-[#17304f]">
					{ statusText }
				</span>
				<span className="text-[11px] text-[#637696]">
					{ typeLabel }
				</span>
			</div>

			{ /* Indeterminate progress bar */ }
			<div className="relative h-1 w-full overflow-hidden rounded-full bg-[#d7e3f2]">
				<div className="openwp-indeterminate-bar absolute inset-y-0 w-2/5 rounded-full bg-[#0057ff]" />
			</div>
		</div>
	);
}
