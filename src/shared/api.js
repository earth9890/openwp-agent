import apiFetch from '@wordpress/api-fetch';

function resolveRuntimeConfig( options = {} ) {
	const runtime = options.runtime || {};
	const sitewide =
		typeof window !== 'undefined' && window.openwpSiteChat
			? window.openwpSiteChat
			: {};
	const admin =
		typeof window !== 'undefined' && window.openwpAdmin
			? window.openwpAdmin
			: {};
	const wpApi =
		typeof window !== 'undefined' && window.wpApiSettings
			? window.wpApiSettings
			: {};

	const root =
		runtime.root ||
		sitewide.root ||
		wpApi.root ||
		'/wp-json/';
	const nonce =
		runtime.nonce ||
		sitewide.nonce ||
		admin.nonce ||
		wpApi.nonce ||
		'';

	return { root, nonce, runtime, sitewide };
}

function buildUrl( root, path ) {
	return root.replace( /\/$/, '' ) + '/' + path.replace( /^\//, '' );
}

export function request( path, options = {} ) {
	const { root, nonce, runtime, sitewide } = resolveRuntimeConfig( options );
	const apiOptions = { ...options };
	delete apiOptions.runtime;

	const headers = {
		...( apiOptions.headers || {} ),
	};
	if ( nonce && ! headers[ 'X-WP-Nonce' ] ) {
		headers[ 'X-WP-Nonce' ] = nonce;
	}

	const shouldUseUrl =
		!! runtime.root ||
		!! runtime.nonce ||
		!! sitewide.root;

	if ( shouldUseUrl ) {
		return apiFetch( {
			...apiOptions,
			url: buildUrl( root, path ),
			headers,
		} );
	}

	return apiFetch( {
		path,
		...apiOptions,
		headers,
	} );
}

/**
 * Stream an SSE response from a POST endpoint using fetch + ReadableStream.
 *
 * @param {string}   path    REST route path (e.g. '/openwp/v1/agent/execute/stream').
 * @param {Object}   data    JSON body payload.
 * @param {Function} onEvent Called with each parsed SSE event object.
 * @param {Object}   options Optional settings. Pass `signal` (AbortSignal) to allow cancellation.
 * @return {Promise<void>} Resolves when stream ends.
 */
export async function streamRequest( path, data, onEvent, options = {} ) {
	const { root, nonce } = resolveRuntimeConfig( options );
	const url = buildUrl( root, path );

	const fetchOptions = {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
		},
		body: JSON.stringify( data ),
	};

	if ( options.signal ) {
		fetchOptions.signal = options.signal;
	}

	const response = await window.fetch( url, fetchOptions );

	if ( ! response.ok ) {
		let errorMessage = `HTTP ${ response.status }`;
		try {
			const errorBody = await response.json();
			errorMessage = errorBody?.message || errorMessage;
		} catch {
			// Ignore JSON parse errors.
		}
		onEvent( { type: 'error', message: errorMessage } );
		return;
	}

	if ( ! response.body ) {
		onEvent( {
			type: 'error',
			message: 'Streaming response body not available.',
		} );
		return;
	}

	const reader = response.body.getReader();
	const decoder = new TextDecoder();
	let buffer = '';

	while ( true ) {
		const { done, value } = await reader.read();
		if ( done ) {
			break;
		}

		buffer += decoder.decode( value, { stream: true } );

		// Split on double newlines (SSE event boundary).
		let boundary;
		while ( ( boundary = buffer.indexOf( '\n\n' ) ) !== -1 ) {
			const rawEvent = buffer.slice( 0, boundary );
			buffer = buffer.slice( boundary + 2 );

			// Extract data: lines.
			const dataLines = [];
			for ( const line of rawEvent.split( '\n' ) ) {
				if ( line.startsWith( 'data:' ) ) {
					dataLines.push( line.slice( 5 ).trimStart() );
				}
			}

			if ( ! dataLines.length ) {
				continue;
			}

			const payload = dataLines.join( '\n' ).trim();
			if ( ! payload || payload === '[DONE]' ) {
				continue;
			}

			try {
				const parsed = JSON.parse( payload );
				onEvent( parsed );
			} catch {
				// Skip malformed events.
			}
		}
	}
}

/**
 * Upload multipart form data to a REST endpoint.
 *
 * @param {string} path REST route path.
 * @param {FormData} formData Multipart form data.
 * @param {Object} options Optional settings (supports runtime config and signal).
 * @return {Promise<Object>} Parsed JSON response.
 */
export async function uploadRequest( path, formData, options = {} ) {
	const { root, nonce } = resolveRuntimeConfig( options );
	const url = buildUrl( root, path );

	const fetchOptions = {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'X-WP-Nonce': nonce,
		},
		body: formData,
	};

	if ( options.signal ) {
		fetchOptions.signal = options.signal;
	}

	const response = await window.fetch( url, fetchOptions );
	let payload = null;

	try {
		payload = await response.json();
	} catch {
		payload = null;
	}

	if ( ! response.ok ) {
		const message = payload?.message || `HTTP ${ response.status }`;
		throw new Error( message );
	}

	return payload || {};
}
