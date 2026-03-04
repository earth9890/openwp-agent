import { useState, useRef, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { streamRequest } from '../../shared/api';

/**
 * Strip markdown code fences that AI providers sometimes wrap around block markup.
 */
function cleanStreamBuffer(raw) {
	let content = raw;

	// Strip markdown code fences.
	const fenceMatch = content.match(/^```(?:html|markup|wordpress|json)?\s*\n?/);
	if (fenceMatch) {
		content = content.slice(fenceMatch[0].length);
		content = content.replace(/\n?```\s*$/, '');
	}

	// Strip JSON wrapper: {"content":"..."} — also handles keys before "content".
	const jsonMatch = content.match(/\{\s*(?:"[^"]*"\s*:\s*"[^"]*"\s*,\s*)*"content"\s*:\s*"/);
	if (jsonMatch) {
		content = content.slice(jsonMatch.index + jsonMatch[0].length);
		// Remove trailing "} if present (may not be yet during streaming).
		content = content.replace(/"\s*\}\s*$/, '');
		// Unescape JSON string escapes.
		content = content
			.replace(/\\n/g, '\n')
			.replace(/\\t/g, '\t')
			.replace(/\\"/g, '"')
			.replace(/\\\\/g, '\\');
	}

	return content;
}

/**
 * Hook that streams AI content and exposes the accumulated markup for live
 * preview. The consumer decides when to parse and insert blocks.
 *
 * @return {Object}
 */
export default function useStreamingGeneration() {
	const [isGenerating, setIsGenerating] = useState(false);
	const [generatedContent, setGeneratedContent] = useState('');
	const [errorMessage, setErrorMessage] = useState('');
	const abortRef = useRef(null);
	const mountedRef = useRef(true);
	const bufferRef = useRef('');

	useEffect(() => {
		mountedRef.current = true;
		return () => {
			mountedRef.current = false;
			if (abortRef.current) {
				abortRef.current.abort();
			}
		};
	}, []);

	const cancel = useCallback(() => {
		if (abortRef.current) {
			abortRef.current.abort();
			abortRef.current = null;
		}
		if (mountedRef.current) {
			setIsGenerating(false);
		}
	}, []);

	const reset = useCallback(() => {
		setGeneratedContent('');
		setErrorMessage('');
	}, []);

	const generate = useCallback(async (params) => {
		setIsGenerating(true);
		setErrorMessage('');
		setGeneratedContent('');
		bufferRef.current = '';

		const controller = new AbortController();
		abortRef.current = controller;

		try {
			await streamRequest(
				'/openwp/v1/editor/generate/stream',
				params,
				(event) => {
					if (!mountedRef.current) return;

					if (event.type === 'chunk' && event.content) {
						bufferRef.current += event.content;
						setGeneratedContent(cleanStreamBuffer(bufferRef.current));
					}

					if (event.type === 'done' && event.content) {
						// Server's final processed content - authoritative.
						setGeneratedContent(event.content);
					}

					if (event.type === 'error') {
						setErrorMessage(
							event.message || __('Failed to generate content.', 'openwp')
						);
					}
				},
				{ signal: controller.signal }
			);
		} catch (err) {
			if (mountedRef.current && err?.name !== 'AbortError') {
				setErrorMessage(
					err?.message || __('Failed to generate content.', 'openwp')
				);
			}
		} finally {
			if (mountedRef.current) {
				setIsGenerating(false);
				abortRef.current = null;
			}
		}
	}, []);

	return { isGenerating, generatedContent, errorMessage, setErrorMessage, generate, cancel, reset };
}
