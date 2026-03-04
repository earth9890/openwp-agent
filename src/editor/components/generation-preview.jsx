import { useRef, useEffect, RawHTML } from '@wordpress/element';

/**
 * Strip WordPress block comments so the preview shows clean renderable HTML.
 */
function stripBlockComments( html ) {
	return html.replace( /<!--\s*\/?wp:[\s\S]*?-->/g, '' );
}

/**
 * Sanitise AI output for safe rendering – strips block comments and script tags.
 */
function sanitizeForPreview( raw ) {
	let content = stripBlockComments( raw );
	content = content.replace( /<script\b[\s\S]*?<\/script>/gi, '' );
	content = content.replace( /\n{3,}/g, '\n\n' ).trim();
	return content;
}

/**
 * Renders a live HTML preview of AI-generated block markup.
 *
 * Uses WordPress RawHTML component to safely render sanitised HTML,
 * auto-scrolling to the bottom during streaming so the latest content
 * stays visible.
 *
 * @param {Object}  props
 * @param {string}  props.content     Raw block markup from the stream.
 * @param {boolean} props.isStreaming  Whether the stream is still in progress.
 */
export default function GenerationPreview( { content, isStreaming } ) {
	const containerRef = useRef( null );
	const cleanHtml = sanitizeForPreview( content );

	// Auto-scroll to bottom while streaming.
	useEffect( () => {
		if ( isStreaming && containerRef.current ) {
			containerRef.current.scrollTop = containerRef.current.scrollHeight;
		}
	}, [ cleanHtml, isStreaming ] );

	if ( ! content ) {
		return null;
	}

	return (
		<div
			ref={ containerRef }
			className="openwp-generation-preview max-h-[400px] overflow-y-auto rounded-xl border border-[#d9e2f2] bg-[linear-gradient(130deg,#ffffff,#f8fbff)] p-4 shadow-sm"
		>
			<RawHTML>{ cleanHtml }</RawHTML>
		</div>
	);
}
