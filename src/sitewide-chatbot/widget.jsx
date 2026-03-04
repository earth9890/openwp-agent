import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { AlertTriangle, Brain, CheckCircle, FileText, Loader2, Paperclip, Rocket, X, XCircle } from 'lucide-react';
import openwpLogo from '../assets/openwp.png';
import { request, streamRequest, uploadRequest } from '../shared/api';
import { OPENAI_MODELS, OPENROUTER_MODELS } from '../shared/constants';

function formatBytes( bytes = 0 ) {
	const size = Number( bytes ) || 0;
	if ( size <= 0 ) return '0 B';
	const units = [ 'B', 'KB', 'MB', 'GB' ];
	const exp = Math.min(
		Math.floor( Math.log( size ) / Math.log( 1024 ) ),
		units.length - 1
	);
	const value = size / Math.pow( 1024, exp );
	return `${ value.toFixed( value >= 10 || exp === 0 ? 0 : 1 ) } ${ units[ exp ] }`;
}

function normalizeConversationText( value, max = 500 ) {
	const plain = String( value || '' )
		.replace( /\s+/g, ' ' )
		.trim();

	if ( plain.length <= max ) {
		return plain;
	}

	return plain.slice( 0, max );
}

function escapeRegExp( value ) {
	return String( value || '' ).replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
}

function hasPageKeyword( prompt, keyword ) {
	const cleanedPrompt = String( prompt || '' );
	const cleanedKeyword = String( keyword || '' ).trim();
	if ( '' === cleanedPrompt || '' === cleanedKeyword ) {
		return false;
	}

	const pattern = new RegExp(
		`(^|\\s)${ escapeRegExp( cleanedKeyword ) }(?=\\s|$)`,
		'i'
	);

	return pattern.test( cleanedPrompt );
}

function stripPageKeyword( prompt, keyword ) {
	const cleanedPrompt = String( prompt || '' );
	const cleanedKeyword = String( keyword || '' ).trim();
	if ( '' === cleanedPrompt || '' === cleanedKeyword ) {
		return cleanedPrompt.trim();
	}

	const pattern = new RegExp(
		`(^|\\s)${ escapeRegExp( cleanedKeyword ) }(?=\\s|$)`,
		'gi'
	);

	return cleanedPrompt.replace( pattern, ' ' ).replace( /\s+/g, ' ' ).trim();
}

function findTagToken( value, cursorPos ) {
	const input = String( value || '' );
	const safeCursor = Number.isFinite( cursorPos )
		? Math.max( 0, Math.min( cursorPos, input.length ) )
		: input.length;
	const beforeCursor = input.slice( 0, safeCursor );
	const match = /(?:^|\s)(@[A-Za-z0-9_-]*)$/.exec( beforeCursor );
	if ( ! match ) {
		return null;
	}

	const token = String( match[ 1 ] || '' );
	if ( '' === token ) {
		return null;
	}

	return {
		token,
		start: beforeCursor.length - token.length,
		end: safeCursor,
	};
}

/**
 * Lightweight markdown-to-HTML for assistant replies.
 * Handles: **bold**, *italic*, `code`, [links](url), line breaks.
 */
function markdownToHtml( text ) {
	if ( ! text ) {
		return '';
	}

	let html = String( text )
		// Escape HTML entities first.
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		// Bold: **text** or __text__.
		.replace( /\*\*(.+?)\*\*/g, '<strong>$1</strong>' )
		.replace( /__(.+?)__/g, '<strong>$1</strong>' )
		// Italic: *text* or _text_ (but not inside words).
		.replace( /(?<!\w)\*(?!\s)(.+?)(?<!\s)\*(?!\w)/g, '<em>$1</em>' )
		.replace( /(?<!\w)_(?!\s)(.+?)(?<!\s)_(?!\w)/g, '<em>$1</em>' )
		// Inline code: `text`.
		.replace( /`([^`]+?)`/g, '<code>$1</code>' )
		// Links: [text](url).
		.replace(
			/\[([^\]]+?)\]\((https?:\/\/[^\s)]+)\)/g,
			'<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>'
		)
		// Line breaks.
		.replace( /\n/g, '<br />' );

	return html;
}

/**
 * Extract the "thought" value from a partial/complete JSON string
 * being streamed from the LLM agent decision.
 */
function extractThoughtFromJson( text ) {
	if ( ! text ) {
		return null;
	}
	// Match "thought": "..." even if the closing quote isn't there yet.
	const match = text.match( /"thought"\s*:\s*"((?:[^"\\]|\\[\s\S])*?)(?:"|$)/ );
	if ( match && match[ 1 ] ) {
		return match[ 1 ]
			.replace( /\\"/g, '"' )
			.replace( /\\n/g, '\n' )
			.replace( /\\t/g, '\t' )
			.replace( /\\\\/g, '\\' );
	}
	return null;
}

// ---------------------------------------------------------------------------
// Widget-native data renderer (no Tailwind — uses .openwp-sitewide-* classes)
// ---------------------------------------------------------------------------

const DATA_ARRAY_KEYS = [ 'items', 'rows', 'results', 'products', 'orders',
	'customers', 'reviews', 'plugins', 'themes', 'users', 'comments',
	'posts', 'terms', 'media', 'taxonomies', 'post_types', 'languages',
	'translations', 'updated' ];
const DATA_META_KEYS = [ 'total', 'total_pages', 'count', 'statement_type', 'reply',
	'affected_rows', 'threshold' ];
const DATA_SKIP_COLS = [ 'post_content', 'post_content_filtered', 'post_password',
	'post_excerpt', 'to_ping', 'pinged', 'guid', 'filter' ];
const DATA_PRIORITY_COLS = [ 'ID', 'id', 'name', 'post_title', 'title', 'slug',
	'user_login', 'display_name', 'status', 'post_status', 'active', 'price',
	'regular_price', 'stock_quantity', 'sku', 'version', 'date_created' ];

function findDataArrayField( data ) {
	if ( Array.isArray( data ) && data.length > 0 ) {
		return '__root';
	}
	for ( const key of DATA_ARRAY_KEYS ) {
		if ( Array.isArray( data[ key ] ) ) {
			return key;
		}
	}
	for ( const [ key, val ] of Object.entries( data ) ) {
		if ( Array.isArray( val ) && val.length > 0 && typeof val[ 0 ] === 'object' && val[ 0 ] !== null ) {
			return key;
		}
	}
	return null;
}

function formatColLabel( key ) {
	return key
		.replace( /^(post_|user_|term_|comment_)/, '' )
		.replace( /_/g, ' ' )
		.replace( /\bid\b/gi, 'ID' )
		.replace( /\burl\b/gi, 'URL' )
		.replace( /\b\w/g, ( l ) => l.toUpperCase() );
}

function formatCellValue( val ) {
	if ( val === null || val === undefined ) {
		return '\u2014';
	}
	if ( typeof val === 'boolean' ) {
		return val ? 'Yes' : 'No';
	}
	if ( Array.isArray( val ) ) {
		if ( ! val.length ) {
			return '\u2014';
		}
		if ( typeof val[ 0 ] === 'object' ) {
			return `${ val.length } items`;
		}
		return val.join( ', ' );
	}
	if ( typeof val === 'object' ) {
		const readable = [ 'email', 'name', 'title', 'label', 'slug', 'sku' ];
		for ( const rk of readable ) {
			if ( typeof val[ rk ] === 'string' && val[ rk ].trim() ) {
				return val[ rk ];
			}
		}
		return `{ ${ Object.keys( val ).length } fields }`;
	}
	const str = String( val );
	if ( str.length > 60 ) {
		return str.slice( 0, 57 ) + '\u2026';
	}
	return str;
}

function WidgetDataTable( { data } ) {
	if ( ! data || typeof data !== 'object' ) {
		return null;
	}

	const arrayKey = findDataArrayField( data );
	if ( ! arrayKey ) {
		// Key-value display for flat objects.
		const pairs = Object.entries( data ).filter(
			( [ k ] ) => ! DATA_META_KEYS.includes( k )
		);
		if ( ! pairs.length ) {
			return null;
		}
		return (
			<div className="openwp-sitewide-data-kv">
				{ pairs.map( ( [ k, v ] ) => (
					<div key={ k } className="openwp-sitewide-data-kv-row">
						<span className="openwp-sitewide-data-kv-key">{ formatColLabel( k ) }</span>
						<span className="openwp-sitewide-data-kv-val">{ formatCellValue( v ) }</span>
					</div>
				) ) }
			</div>
		);
	}

	const rows = arrayKey === '__root' ? data : data[ arrayKey ];
	if ( ! rows.length ) {
		const label = arrayKey !== '__root'
			? formatColLabel( arrayKey ).toLowerCase()
			: 'items';
		return (
			<div className="openwp-sitewide-data-meta">
				No { label } found.
			</div>
		);
	}

	// Derive columns.
	const allKeys = new Set();
	rows.forEach( ( row ) => {
		if ( row && typeof row === 'object' ) {
			Object.keys( row ).forEach( ( k ) => allKeys.add( k ) );
		}
	} );

	const cols = Array.from( allKeys )
		.filter( ( k ) => ! DATA_SKIP_COLS.includes( k ) )
		.filter( ( k ) => {
			const complexCount = rows.filter( ( r ) => {
				const v = r?.[ k ];
				return ( Array.isArray( v ) && v.length > 0 && typeof v[ 0 ] === 'object' ) ||
					( typeof v === 'object' && v !== null && ! Array.isArray( v ) );
			} ).length;
			return complexCount / rows.length < 0.6;
		} )
		.sort( ( a, b ) => {
			const ai = DATA_PRIORITY_COLS.indexOf( a );
			const bi = DATA_PRIORITY_COLS.indexOf( b );
			if ( ai !== -1 && bi !== -1 ) {
				return ai - bi;
			}
			if ( ai !== -1 ) {
				return -1;
			}
			if ( bi !== -1 ) {
				return 1;
			}
			return a.localeCompare( b );
		} )
		.slice( 0, 5 );

	if ( ! cols.length ) {
		return null;
	}

	const countVal = data.count ?? data.total ?? rows.length;

	return (
		<div className="openwp-sitewide-data-table-wrap">
			{ countVal > 0 && (
				<div className="openwp-sitewide-data-meta">
					{ countVal } { countVal === 1 ? 'item' : 'items' }
				</div>
			) }
			<div className="openwp-sitewide-data-scroll">
				<table className="openwp-sitewide-data-table">
					<thead>
						<tr>
							{ cols.map( ( col ) => (
								<th key={ col }>{ formatColLabel( col ) }</th>
							) ) }
						</tr>
					</thead>
					<tbody>
						{ rows.slice( 0, 25 ).map( ( row, i ) => (
							<tr key={ i }>
								{ cols.map( ( col ) => (
									<td key={ col }>{ formatCellValue( row?.[ col ] ) }</td>
								) ) }
							</tr>
						) ) }
					</tbody>
				</table>
			</div>
			{ rows.length > 25 && (
				<div className="openwp-sitewide-data-meta">
					Showing 25 of { rows.length } rows.
				</div>
			) }
		</div>
	);
}

function buildAssistantConversationText( summary ) {
	if ( ! summary || typeof summary !== 'object' ) {
		return '';
	}

	if ( summary.execution?.data?.reply ) {
		return normalizeConversationText( summary.execution.data.reply, 700 );
	}

	if ( summary.execution?.message ) {
		return normalizeConversationText( summary.execution.message, 700 );
	}

	if ( Array.isArray( summary.executions ) && summary.executions.length > 0 ) {
		const last = summary.executions[ summary.executions.length - 1 ];
		if ( last?.execution?.message ) {
			return normalizeConversationText( last.execution.message, 700 );
		}
	}

	if ( summary.status === 'awaiting_approval' && summary.execution?.action ) {
		return normalizeConversationText(
			`Awaiting approval for ${ summary.execution.action }.`,
			700
		);
	}

	if ( typeof summary.status === 'string' && summary.status ) {
		if ( summary.status === 'no_action' ) {
			const thought = summary.provider_output?.thought || summary.message || '';
			if ( thought ) {
				return normalizeConversationText( thought, 700 );
			}
		}

		return normalizeConversationText(
			`Completed with status ${ summary.status }.`,
			700
		);
	}

	return normalizeConversationText(
		__( 'OpenWP completed your request.', 'openwp' ),
		700
	);
}

function getEventMessage( event ) {
	if ( ! event || typeof event !== 'object' ) {
		return '';
	}

	switch ( event.type ) {
		case 'thinking':
			return event.content || '';
		case 'action':
			return event.action ? `Running ${ event.action }` : '';
		case 'result':
			return event.message || event.status || '';
		case 'approval':
			return event.action
				? `Approval needed for ${ event.action }`
				: 'Approval needed';
		case 'error':
			return event.message || '';
		default:
			return '';
	}
}

const RISK_COLORS = {
	low: 'openwp-approval-risk-low',
	medium: 'openwp-approval-risk-medium',
	high: 'openwp-approval-risk-high',
	critical: 'openwp-approval-risk-critical',
};

/**
 * Inline approval card rendered inside an assistant bubble when an action
 * is queued for approval. Calls the REST API directly and updates the message.
 */
function ApprovalCard( { approval, onResolved } ) {
	const [ typed, setTyped ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ done, setDone ] = useState( null ); // 'approved' | 'rejected'
	const [ error, setError ] = useState( '' );

	const isCritical = approval.risk === 'critical';
	const canApprove = ! isCritical || typed.trim().toUpperCase() === 'APPROVE';

	const handle = async ( action ) => {
		setBusy( true );
		setError( '' );
		try {
			const body =
				action === 'approve'
					? { typed_confirmation: isCritical ? typed.trim() : '' }
					: {};
			await request(
				`/openwp/v1/approvals/${ approval.id }/${ action }`,
				{
					method: 'POST',
					data: body,
				}
			);
			const resolvedState = action === 'approve' ? 'approved' : 'rejected';
			setDone( resolvedState );
			if ( onResolved ) {
				onResolved( resolvedState );
			}
		} catch ( err ) {
			setError( err?.message || __( 'Request failed.', 'openwp' ) );
		} finally {
			setBusy( false );
		}
	};

	if ( done ) {
		return (
			<div className="openwp-approval-done">
				{ done === 'approved' ? (
					<>
						<CheckCircle size={ 14 } />
						{ __( 'Action approved — executing…', 'openwp' ) }
					</>
				) : (
					<>
						<XCircle size={ 14 } />
						{ __( 'Action rejected.', 'openwp' ) }
					</>
				) }
			</div>
		);
	}

	return (
		<div className="openwp-approval-card">
			<div className="openwp-approval-header">
				<AlertTriangle size={ 14 } />
				<span>{ __( 'Approval required', 'openwp' ) }</span>
				<span
					className={ `openwp-approval-risk ${ RISK_COLORS[ approval.risk ] || RISK_COLORS.medium }` }
				>
					{ approval.risk }
				</span>
			</div>

			<div className="openwp-approval-action">
				<code>{ approval.action }</code>
			</div>

			{ isCritical && (
				<div className="openwp-approval-typed">
					<input
						type="text"
						value={ typed }
						onChange={ ( e ) => setTyped( e.target.value ) }
						placeholder={ __( 'Type APPROVE to confirm', 'openwp' ) }
						disabled={ busy }
					/>
				</div>
			) }

			{ error && (
				<div className="openwp-approval-error">{ error }</div>
			) }

			<div className="openwp-approval-actions">
				<button
					className="openwp-approval-btn openwp-approval-btn-approve"
					onClick={ () => handle( 'approve' ) }
					disabled={ busy || ! canApprove }
				>
					{ busy ? (
						<Loader2 size={ 12 } className="openwp-sitewide-spin" />
					) : (
						<CheckCircle size={ 12 } />
					) }
					{ __( 'Approve', 'openwp' ) }
				</button>
				<button
					className="openwp-approval-btn openwp-approval-btn-reject"
					onClick={ () => handle( 'reject' ) }
					disabled={ busy }
				>
					<XCircle size={ 12 } />
					{ __( 'Reject', 'openwp' ) }
				</button>
			</div>
		</div>
	);
}

export default function SitewideChatbotWidget() {
	const runtimeConfig = window.openwpSiteChat || {};
	const strings = runtimeConfig.strings || {};
	const limits = runtimeConfig.limits || {};
	const contextKeyword = String( runtimeConfig.contextKeyword || '@page' );
	const memoryKeyword = String( runtimeConfig.memoryKeyword || '@memory' );

	const maxFiles = Number( limits.maxFiles ) || 5;
	const maxFileSizeBytes = Number( limits.maxFileSizeBytes ) || 10485760;

	const [ isOpen, setIsOpen ] = useState( false );
	const [ input, setInput ] = useState( '' );
	const [ provider, setProvider ] = useState(
		runtimeConfig.defaultProvider || 'openrouter'
	);
	const [ model, setModel ] = useState( runtimeConfig.defaultModel || '' );
	const [ messages, setMessages ] = useState( [] );
	const [ conversationTurns, setConversationTurns ] = useState( [] );
	const [ attachments, setAttachments ] = useState( [] );
	const [ isSending, setIsSending ] = useState( false );
	const [ isUploading, setIsUploading ] = useState( false );
	const [ isLaunching, setIsLaunching ] = useState( false );
	const [ tagHint, setTagHint ] = useState( {
		visible: false,
		start: 0,
		end: 0,
		query: '',
	} );
	const [ activeTagIndex, setActiveTagIndex ] = useState( 0 );
	const [ statusText, setStatusText ] = useState( '' );
	const [ error, setError ] = useState( '' );

	const messageEndRef = useRef( null );
	const fileInputRef = useRef( null );
	const promptInputRef = useRef( null );

	const sessionToken = useMemo( () => {
		if ( typeof window !== 'undefined' && window.crypto?.randomUUID ) {
			return window.crypto
				.randomUUID()
				.replace( /[^A-Za-z0-9_-]/g, '' );
		}

		const randomPart = `${ Date.now() }_${ Math.random() }`;
		return randomPart.replace( /[^A-Za-z0-9_-]/g, '' );
	}, [] );

	const providerOptions = useMemo( () => {
		const configured = runtimeConfig.providers || {};
		return [
			{
				value: 'openai',
				label: 'OpenAI',
				model: configured.openai || 'gpt-5.2',
			},
			{
				value: 'anthropic',
				label: 'Anthropic',
				model: configured.anthropic || 'claude-3-5-sonnet-latest',
			},
			{ value: 'glm', label: 'GLM', model: configured.glm || 'glm-5' },
			{
				value: 'openrouter',
				label: 'OpenRouter',
				model: configured.openrouter || 'anthropic/claude-sonnet-4-5',
			},
		];
	}, [ runtimeConfig.providers ] );

	const modelOptions = useMemo( () => {
		const byProvider = {
			openai: OPENAI_MODELS.map( ( option ) => option.value ),
			anthropic: [ 'claude-3-5-sonnet-latest' ],
			glm: [ 'glm-5' ],
			openrouter: OPENROUTER_MODELS.map( ( option ) => option.value ),
		};

		const options = [ ...( byProvider[ provider ] || [] ) ];
		if ( model && ! options.includes( model ) ) {
			options.unshift( model );
		}
		return options;
	}, [ provider, model ] );

	const availableTags = useMemo( () => {
		const runtimeTags = Array.isArray( runtimeConfig.availableTags )
			? runtimeConfig.availableTags
			: [];
		const normalized = runtimeTags
			.map( ( item ) => String( item || '' ).trim() )
			.filter( ( item ) => item.startsWith( '@' ) );

		if ( ! normalized.includes( contextKeyword ) ) {
			normalized.unshift( contextKeyword );
		}

		return Array.from( new Set( normalized ) );
	}, [ contextKeyword, runtimeConfig.availableTags ] );

	const filteredTagSuggestions = useMemo( () => {
		if ( ! tagHint.visible ) {
			return [];
		}

		const query = String( tagHint.query || '@' ).toLowerCase();
		return availableTags
			.filter( ( tag ) => tag.toLowerCase().startsWith( query ) )
			.slice( 0, 6 );
	}, [ availableTags, tagHint.query, tagHint.visible ] );

	useEffect( () => {
		const selected = providerOptions.find(
			( item ) => item.value === provider
		);
		if ( selected && ! model ) {
			setModel( selected.model );
		}
	}, [ providerOptions, provider, model ] );

	useEffect( () => {
		if ( messageEndRef.current ) {
			messageEndRef.current.scrollIntoView( {
				behavior: 'smooth',
				block: 'end',
			} );
		}
	}, [ messages, statusText ] );

	useEffect( () => {
		if ( filteredTagSuggestions.length === 0 ) {
			return;
		}

		if ( activeTagIndex >= filteredTagSuggestions.length ) {
			setActiveTagIndex( 0 );
		}
	}, [ activeTagIndex, filteredTagSuggestions.length ] );

	useEffect( () => {
		const onBeforeUnload = () => {
			const root = String( runtimeConfig.root || '/wp-json/' );
			const endpoint = `${ root.replace(
				/\/$/,
				''
			) }/openwp/v1/chatbot/session/clear`;
			const payload = JSON.stringify( { session_token: sessionToken } );
			window
				.fetch( endpoint, {
					method: 'POST',
					credentials: 'same-origin',
					keepalive: true,
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': String( runtimeConfig.nonce || '' ),
					},
					body: payload,
				} )
				.catch( () => {} );
		};

		window.addEventListener( 'beforeunload', onBeforeUnload );
		return () => {
			window.removeEventListener( 'beforeunload', onBeforeUnload );
		};
	}, [ runtimeConfig.nonce, runtimeConfig.root, sessionToken ] );

	const setNextInclude = ( id, enabled ) => {
		setAttachments( ( prev ) =>
			prev.map( ( item ) =>
				item.id === id ? { ...item, includeNext: !! enabled } : item
			)
		);
	};

	const removeAttachment = ( id ) => {
		setAttachments( ( prev ) =>
			prev.filter( ( item ) => item.id !== id )
		);
	};

	const handleUploadFiles = async ( event ) => {
		const files = Array.from( event.target.files || [] );
		if ( ! files.length ) {
			return;
		}

		setError( '' );

		if ( attachments.length >= maxFiles ) {
			setError(
				strings.fileLimitReached || 'File limit reached for this session.'
			);
			event.target.value = '';
			return;
		}

		setIsUploading( true );
		try {
			let currentCount = attachments.length;
			for ( const file of files ) {
				if ( currentCount >= maxFiles ) {
					setError(
						strings.fileLimitReached ||
							'File limit reached for this session.'
					);
					break;
				}

				if ( file.size > maxFileSizeBytes ) {
					setError(
						strings.uploadTooLarge || 'File exceeds the 10MB limit.'
					);
					continue;
				}

				const formData = new FormData();
				formData.append( 'session_token', sessionToken );
				formData.append( 'file', file );

				const response = await uploadRequest(
					'/openwp/v1/chatbot/upload',
					formData,
					{ runtime: runtimeConfig }
				);

				if ( response?.item ) {
					currentCount += 1;
					setAttachments( ( prev ) => [
						...prev,
						{ ...response.item, includeNext: false },
					] );
				}
			}
		} catch ( uploadError ) {
			setError(
				uploadError?.message ||
					`${ strings.errorPrefix || 'Error:' } ${ __(
						'Unable to upload attachment.',
						'openwp'
					) }`
			);
		} finally {
			setIsUploading( false );
			event.target.value = '';
		}
	};

	const clearSessionOnServer = async () => {
		await request( '/openwp/v1/chatbot/session/clear', {
			method: 'POST',
			data: {
				session_token: sessionToken,
			},
			runtime: runtimeConfig,
		} );
	};

	const handleClearChat = async () => {
		setMessages( [] );
		setConversationTurns( [] );
		setAttachments( [] );
		setStatusText( '' );
		setError( '' );
		setTagHint( { visible: false, start: 0, end: 0, query: '' } );

		try {
			await clearSessionOnServer();
		} catch {
			// Best-effort clear.
		}
	};

	const buildPromptPayload = ( rawInput ) => {
		const rawPrompt = String( rawInput || '' ).trim();
		const includePageContext = hasPageKeyword( rawPrompt, contextKeyword );
		const includeMemory = hasPageKeyword( rawPrompt, memoryKeyword );

		let cleanedPrompt = rawPrompt;
		if ( includePageContext ) {
			cleanedPrompt = stripPageKeyword( cleanedPrompt, contextKeyword );
		}
		if ( includeMemory ) {
			cleanedPrompt = stripPageKeyword( cleanedPrompt, memoryKeyword );
		}

		return {
			rawPrompt,
			cleanedPrompt,
			includePageContext,
			includeMemory,
		};
	};

	const updateTagHintFromCursor = ( nextValue, cursorPos ) => {
		const token = findTagToken( nextValue, cursorPos );
		if ( ! token || ! token.token.startsWith( '@' ) ) {
			setTagHint( { visible: false, start: 0, end: 0, query: '' } );
			return;
		}

		setTagHint( {
			visible: true,
			start: token.start,
			end: token.end,
			query: token.token,
		} );
		setActiveTagIndex( 0 );
	};

	const applyTagSuggestion = ( tag ) => {
		if ( ! tagHint.visible ) {
			return;
		}

		const before = input.slice( 0, tagHint.start );
		const after = input.slice( tagHint.end );
		const nextValue = `${ before }${ tag } ${ after.replace( /^\s+/, '' ) }`;
		const nextCursor = before.length + tag.length + 1;

		setInput( nextValue );
		setTagHint( { visible: false, start: 0, end: 0, query: '' } );
		setActiveTagIndex( 0 );

		window.setTimeout( () => {
			if ( promptInputRef.current ) {
				promptInputRef.current.focus();
				promptInputRef.current.setSelectionRange( nextCursor, nextCursor );
			}
		}, 0 );
	};

	const handleInputChange = ( event ) => {
		const nextValue = event.target.value;
		setInput( nextValue );
		updateTagHintFromCursor( nextValue, event.target.selectionStart );
	};

	const handleSend = async ( preparedPayload = null ) => {
		if ( isSending ) {
			return;
		}

		const payload = preparedPayload || buildPromptPayload( input );
		if ( ! payload.rawPrompt ) {
			return;
		}

		if ( ! payload.cleanedPrompt ) {
			setError(
				strings.contextEmptyPrompt ||
					'Add a request after @page so OpenWP knows what to do.'
			);
			return;
		}

		const prompt = payload.cleanedPrompt;

		setInput( '' );
		setError( '' );
		setTagHint( { visible: false, start: 0, end: 0, query: '' } );
		setStatusText( strings.waitingResponse || 'OpenWP is thinking…' );

		const userMessage = {
			id: `msg-user-${ Date.now() }`,
			role: 'user',
			text: payload.rawPrompt,
		};
		setMessages( ( prev ) => [ ...prev, userMessage ] );

		const assistantId = `msg-assistant-${ Date.now() }`;
		setMessages( ( prev ) => [
			...prev,
			{
				id: assistantId,
				role: 'assistant',
				text: '',
				pending: true,
			},
		] );

		const selectedAttachmentIds = attachments
			.filter( ( item ) => !! item.includeNext )
			.map( ( item ) => item.id );

		const requestData = {
			prompt,
			provider,
			model,
			session_token: sessionToken,
			attachment_ids: selectedAttachmentIds,
		};

		if (
			payload.includePageContext &&
			runtimeConfig.pageContext &&
			typeof runtimeConfig.pageContext === 'object'
		) {
			requestData.page_context = {
				...runtimeConfig.pageContext,
				client_url: window.location.href,
				client_title: document.title,
				trigger: contextKeyword,
			};
		}

		if ( payload.includeMemory ) {
			requestData.include_memory = true;
		}

		if ( conversationTurns.length > 0 ) {
			requestData.conversation_context = {
				enabled: true,
				turns: conversationTurns.slice( -3 ).map( ( turn ) => ( {
					user: turn.user,
					assistant: turn.assistant,
				} ) ),
			};
		}

		setIsSending( true );

		let finalSummary = null;
		let streamedText = '';
		let eventMessage = '';
		try {
			await streamRequest(
				'/openwp/v1/chatbot/execute/stream',
				requestData,
				( event ) => {
					if ( event.type === 'done' && event.summary ) {
						finalSummary = event.summary;
						return;
					}

					if ( event.type === 'error' ) {
						const message =
							event.message || __( 'Request failed.', 'openwp' );
						eventMessage = message;
						return;
					}

					// Stream thinking chunks — extract the "thought" field
					// from the LLM's JSON decision as it builds up.
					if ( event.type === 'thinking' && event.content ) {
						streamedText += event.content;
						const thought = extractThoughtFromJson( streamedText );
						setMessages( ( prev ) =>
							prev.map( ( msg ) =>
								msg.id === assistantId
									? { ...msg, thinking: thought || null, pending: false }
									: msg
							)
						);
						return;
					}

					if ( event.type === 'status' && event.message ) {
						setStatusText( event.message );
						setMessages( ( prev ) =>
							prev.map( ( msg ) =>
								msg.id === assistantId
									? { ...msg, agentStatus: event.message, pending: false }
									: msg
							)
						);
						return;
					}

					// Pipe agent execution events into the bubble.
					if ( event.type === 'action' && event.action ) {
						const label = `Running **${ event.action }**…`;
						setMessages( ( prev ) =>
							prev.map( ( msg ) =>
								msg.id === assistantId
									? { ...msg, agentStatus: label, pending: false }
									: msg
							)
						);
						setStatusText( `Running ${ event.action }` );
						return;
					}

					if ( event.type === 'step' ) {
						const stepLabel = event.status === 'executing'
							? `Executing step ${ event.step || 1 }${ event.total > 1 ? ` of ${ event.total }` : '' }…`
							: `Step ${ event.step || 1 } — ${ event.status || 'processing' }`;
						setMessages( ( prev ) =>
							prev.map( ( msg ) =>
								msg.id === assistantId
									? { ...msg, agentStatus: stepLabel, pending: false }
									: msg
							)
						);
						return;
					}

					if ( event.type === 'result' ) {
						const resultLabel = event.status === 'success'
							? ( event.message || __( 'Action completed.', 'openwp' ) )
							: ( event.message || event.status || '' );
						if ( resultLabel ) {
							eventMessage = resultLabel;
						}
						setMessages( ( prev ) =>
							prev.map( ( msg ) =>
								msg.id === assistantId
									? { ...msg, agentStatus: resultLabel, pending: false }
									: msg
							)
						);
						return;
					}

					const message = getEventMessage( event );
					if ( message ) {
						eventMessage = message;
						setStatusText( message );
					}
				},
				{ runtime: runtimeConfig }
			);
		} catch ( streamError ) {
			const message =
				streamError?.message || __( 'Request failed.', 'openwp' );
			eventMessage = message;
		}

		const assistantText = finalSummary
			? buildAssistantConversationText( finalSummary )
			: streamedText ||
			  normalizeConversationText(
					eventMessage || __( 'No response.', 'openwp' ),
					700
			  );

		const approvalInfo =
			finalSummary?.status === 'awaiting_approval' &&
			finalSummary?.execution?.approval_id
				? {
						id: finalSummary.execution.approval_id,
						action: finalSummary.execution.action || '',
						risk: finalSummary.execution.risk || 'medium',
				  }
				: null;

		const memoryUsed = finalSummary?.execution?.data?.memory_used || 0;
		const memoryItems = finalSummary?.execution?.data?.memory_items || [];

		setMessages( ( prev ) =>
			prev.map( ( msg ) =>
				msg.id === assistantId
					? {
							...msg,
							text: assistantText,
							data: finalSummary?.execution?.data || null,
							approval: approvalInfo,
							memoryUsed,
							memoryItems,
							pending: false,
					  }
					: msg
			)
		);

		if ( assistantText ) {
			setConversationTurns( ( prev ) =>
				[
					...prev,
					{
						user: normalizeConversationText( prompt, 500 ),
						assistant: normalizeConversationText( assistantText, 700 ),
					},
				].slice( -3 )
			);
		}

		setAttachments( ( prev ) =>
			prev.map( ( item ) => ( {
				...item,
				includeNext: false,
			} ) )
		);
		setStatusText( '' );
		setIsSending( false );
	};

	const handleLaunch = () => {
		if ( isSending || isUploading || isLaunching ) {
			return;
		}

		const preparedPayload = buildPromptPayload( input );
		if ( ! preparedPayload.rawPrompt ) {
			return;
		}

		if ( ! preparedPayload.cleanedPrompt ) {
			setError(
				strings.contextEmptyPrompt ||
					'Add a request after @page so OpenWP knows what to do.'
			);
			return;
		}

		setError( '' );
		setIsLaunching( true );
		window.setTimeout( () => {
			setIsLaunching( false );
			void handleSend( preparedPayload );
		}, 500 );
	};

	const handlePromptKeyDown = ( event ) => {
		if ( 'Escape' === event.key && tagHint.visible ) {
			event.preventDefault();
			setTagHint( { visible: false, start: 0, end: 0, query: '' } );
			return;
		}

		if ( tagHint.visible && filteredTagSuggestions.length > 0 ) {
			if ( 'ArrowDown' === event.key ) {
				event.preventDefault();
				setActiveTagIndex( ( prev ) =>
					( prev + 1 ) % filteredTagSuggestions.length
				);
				return;
			}

			if ( 'ArrowUp' === event.key ) {
				event.preventDefault();
				setActiveTagIndex( ( prev ) =>
					prev <= 0 ? filteredTagSuggestions.length - 1 : prev - 1
				);
				return;
			}

			if (
				'Tab' === event.key ||
				( 'Enter' === event.key &&
					! event.metaKey &&
					! event.ctrlKey &&
					! event.shiftKey )
			) {
				event.preventDefault();
				applyTagSuggestion(
					filteredTagSuggestions[ activeTagIndex ] ||
						filteredTagSuggestions[ 0 ]
				);
				return;
			}
		}

		if ( event.key === 'Enter' && ( event.metaKey || event.ctrlKey ) ) {
			event.preventDefault();
			handleLaunch();
		}
	};

	return (
		<div className="openwp-sitewide-chatbot-root" aria-live="polite">
			{ isOpen && (
				<div
					className="openwp-sitewide-chat-panel"
					role="dialog"
					aria-label={ strings.title || 'OpenWP Assistant' }
				>
					<header className="openwp-sitewide-chat-header">
						<div className="openwp-sitewide-brand">
							<img src={ openwpLogo } alt="" />
							<div>
								<strong>{ strings.title || 'OpenWP Assistant' }</strong>
								<span>
									{ strings.subtitle ||
										'Session chat context resets on reload.' }
								</span>
							</div>
						</div>
						<div className="openwp-sitewide-header-actions">
							<button
								type="button"
								className="openwp-sitewide-clear-btn"
								onClick={ handleClearChat }
								disabled={ isSending || isUploading }
							>
								{ strings.clear || 'Clear' }
							</button>
							<button
								type="button"
								className="openwp-sitewide-close"
								onClick={ () => setIsOpen( false ) }
								aria-label={ __( 'Close', 'openwp' ) }
							>
								<X size={ 16 } strokeWidth={ 2 } />
							</button>
						</div>
					</header>

					<section className="openwp-sitewide-message-list">
						{ messages.length === 0 && (
							<div className="openwp-sitewide-empty">
								<div className="openwp-sitewide-empty-icon">
									<Rocket size={ 18 } />
								</div>
								{ __(
									'Ask OpenWP anything about managing your WordPress site.',
									'openwp'
								) }
							</div>
						) }
						{ messages.map( ( msg ) => (
							<div
								key={ msg.id }
								className={ `openwp-sitewide-message openwp-sitewide-message-${ msg.role }` }
							>
								{ msg.pending ? (
									<div className="openwp-sitewide-message-bubble openwp-sitewide-thinking-bubble">
										<div className="openwp-sitewide-thinking-logo-wrap">
											<img
												src={ openwpLogo }
												alt=""
												className="openwp-sitewide-thinking-logo"
											/>
											<span className="openwp-sitewide-thinking-ring"></span>
										</div>
										<span className="openwp-sitewide-thinking-label">
											{ strings.waitingResponse || 'OpenWP is thinking…' }
										</span>
									</div>
								) : msg.thinking && ! msg.text && ! msg.agentStatus ? (
									<div className="openwp-sitewide-message-bubble openwp-sitewide-thinking-live">
										<div className="openwp-sitewide-thinking-header">
											<Brain size={ 14 } className="openwp-sitewide-thinking-icon" />
											<span className="openwp-sitewide-thinking-title">
												{ __( 'Thinking…', 'openwp' ) }
											</span>
										</div>
										{ /* Thinking content is extracted from the LLM's own thought field — not user input */ }
										<div
											className="openwp-sitewide-thinking-content"
											dangerouslySetInnerHTML={ { __html: markdownToHtml( msg.thinking ) } }
										/>
									</div>
								) : msg.agentStatus && ! msg.text ? (
									<div className="openwp-sitewide-message-bubble openwp-sitewide-agent-progress">
										<div className="openwp-sitewide-agent-status-row">
											<Loader2 size={ 14 } className="openwp-sitewide-agent-spinner" />
											<span dangerouslySetInnerHTML={ { __html: markdownToHtml( msg.agentStatus ) } } />
										</div>
									</div>
								) : (
									<div className="openwp-sitewide-message-bubble">
										{ msg.role === 'assistant' && msg.thinking && msg.text && (
											<details className="openwp-sitewide-thinking-details">
												<summary className="openwp-sitewide-thinking-summary">
													<Brain size={ 12 } />
													<span>{ __( 'Show thinking', 'openwp' ) }</span>
												</summary>
												{ /* Thinking content is from the LLM thought field, not user input */ }
												<div
													className="openwp-sitewide-thinking-content"
													dangerouslySetInnerHTML={ { __html: markdownToHtml( msg.thinking ) } }
												/>
											</details>
										) }
										{ msg.role === 'assistant' && msg.text ? (
											<div
												className="openwp-sitewide-reply-html"
												dangerouslySetInnerHTML={ { __html: markdownToHtml( msg.text ) } }
											/>
										) : (
											msg.text
										) }
										{ msg.approval ? (
											<ApprovalCard
												approval={ msg.approval }
												onResolved={ ( action ) => {
													setMessages( ( prev ) =>
														prev.map( ( m ) =>
															m.id === msg.id
																? {
																		...m,
																		approval: null,
																		text:
																			action === 'approved'
																				? __( 'Action approved - it will execute shortly.', 'openwp' )
																				: __( 'Action rejected.', 'openwp' ),
																  }
																: m
														)
													);
												} }
											/>
										) : (
											msg.data && (
												<WidgetDataTable data={ msg.data } />
											)
										) }
										{ msg.role === 'assistant' && msg.memoryUsed > 0 && (
											<div className="openwp-sitewide-memory-indicator" title={ msg.memoryItems.join( ', ' ) }>
												<Brain size={ 12 } />
												<span>{ msg.memoryUsed } { msg.memoryUsed === 1 ? __( 'memory recalled', 'openwp' ) : __( 'memories recalled', 'openwp' ) }</span>
											</div>
										) }
									</div>
								) }
							</div>
						) ) }
						{ statusText && isSending && (
							<div className="openwp-sitewide-status">{ statusText }</div>
						) }
						{ error && (
							<div className="openwp-sitewide-error">{ error }</div>
						) }
						<div ref={ messageEndRef } />
					</section>

					<section className="openwp-sitewide-controls">
						<div className="openwp-sitewide-provider-row">
							<select
								value={ provider }
								onChange={ ( event ) => {
									const nextProvider = event.target.value;
									setProvider( nextProvider );
									const found = providerOptions.find(
										( item ) => item.value === nextProvider
									);
									if ( found ) {
										setModel( found.model );
									}
								} }
							>
								{ providerOptions.map( ( item ) => (
									<option key={ item.value } value={ item.value }>
										{ item.label }
									</option>
								) ) }
							</select>
							<select
								value={ model }
								onChange={ ( event ) => setModel( event.target.value ) }
								aria-label={ __( 'Model', 'openwp' ) }
							>
								{ modelOptions.map( ( option ) => (
									<option key={ option } value={ option }>
										{ option }
									</option>
								) ) }
							</select>
						</div>

						<div className="openwp-sitewide-context-hint">
							{ strings.contextHint ||
								'Type @page to include current page context.' }
						</div>

						<input
							ref={ fileInputRef }
							type="file"
							multiple
							onChange={ handleUploadFiles }
							className="openwp-sitewide-file-input"
							disabled={ isUploading || isSending }
						/>

						{ attachments.length > 0 && (
							<div
								className="openwp-sitewide-attachment-list"
								aria-label={ strings.attachments || 'Attachments' }
							>
								{ attachments.map( ( item ) => (
									<div
										key={ item.id }
										className="openwp-sitewide-attachment-chip"
									>
										<div className="openwp-sitewide-attachment-top">
											<div className="openwp-sitewide-attachment-file-icon">
												<FileText size={ 14 } />
											</div>
											<div className="openwp-sitewide-attachment-main">
												<strong title={ item.original_name }>
													{ item.original_name }
												</strong>
												<span className="openwp-sitewide-attachment-size">
													{ formatBytes( item.size_bytes ) }
												</span>
											</div>
											<button
												type="button"
												className="openwp-sitewide-attachment-remove"
												onClick={ () => removeAttachment( item.id ) }
												disabled={ isSending }
												aria-label="Remove attachment"
											>
												<X size={ 11 } strokeWidth={ 2.5 } />
											</button>
										</div>
										<label className="openwp-sitewide-attachment-include">
											<input
												type="checkbox"
												checked={ !! item.includeNext }
												onChange={ ( event ) =>
													setNextInclude( item.id, event.target.checked )
												}
												disabled={ isSending }
											/>
											<span>
												{ strings.includeForMessage ||
													'Include in next message' }
											</span>
										</label>
									</div>
								) ) }
							</div>
						) }

							<div className="openwp-sitewide-input-row">
								{ tagHint.visible && filteredTagSuggestions.length > 0 && (
									<div
										className="openwp-sitewide-tag-suggestions"
										role="listbox"
										aria-label={ __( 'Tag suggestions', 'openwp' ) }
									>
										{ filteredTagSuggestions.map( ( tag, index ) => (
											<button
												key={ tag }
												type="button"
												className={ `openwp-sitewide-tag-suggestion${ index === activeTagIndex ? ' is-active' : '' }` }
												onMouseDown={ ( event ) => {
													event.preventDefault();
													applyTagSuggestion( tag );
												} }
												aria-selected={ index === activeTagIndex }
											>
												<span className="openwp-sitewide-tag-token">
													{ tag }
												</span>
												<span className="openwp-sitewide-tag-meta">
													{ tag === contextKeyword
														? strings.tagPageContext ||
															'Include current page/screen context'
														: tag === memoryKeyword
															? strings.tagMemory ||
																'Reference your saved preferences and rules'
															: strings.tagAvailable || 'Available tag' }
												</span>
											</button>
										) ) }
									</div>
								) }
								<textarea
									ref={ promptInputRef }
									value={ input }
									onChange={ handleInputChange }
									onClick={ ( event ) =>
										updateTagHintFromCursor(
											event.target.value,
											event.target.selectionStart
										)
									}
									onFocus={ ( event ) =>
										updateTagHintFromCursor(
											event.target.value,
											event.target.selectionStart
										)
									}
									onBlur={ () =>
										setTagHint( {
											visible: false,
											start: 0,
											end: 0,
											query: '',
										} )
									}
									onKeyDown={ handlePromptKeyDown }
									placeholder={
									strings.placeholder ||
									'Ask OpenWP to help with your site…'
								}
								disabled={ isSending || isLaunching }
							/>
							<div className="openwp-sitewide-input-actions">
								<button
									type="button"
									className="openwp-sitewide-file-icon"
									onClick={ () => fileInputRef.current?.click() }
									disabled={ isUploading || isSending }
									aria-label={ strings.upload || 'Add files' }
									title={ strings.upload || 'Add files' }
								>
									{ isUploading ? (
										<Loader2 className="openwp-sitewide-spin" size={ 15 } />
									) : (
										<Paperclip size={ 15 } />
									) }
								</button>
								<span className="openwp-sitewide-attachment-count">
									{ attachments.length } / { maxFiles }
								</span>
								<button
									type="button"
									onClick={ handleLaunch }
									disabled={ isSending || isLaunching || ! input.trim() }
									className="openwp-rocket-btn openwp-sitewide-send-btn"
									aria-label={ strings.send || 'Send' }
									title={ strings.send || 'Send' }
								>
									{ isSending ? (
										<Loader2 className="openwp-sitewide-spin" size={ 15 } />
									) : (
										<span
											className={
												isLaunching
													? 'openwp-rocket-launch inline-flex'
													: 'inline-flex transition-transform'
											}
										>
											<Rocket size={ 15 } />
										</span>
									) }
								</button>
							</div>
						</div>
					</section>
				</div>
			) }

			<button
				type="button"
				className="openwp-sitewide-trigger"
				onClick={ () => setIsOpen( ( prev ) => ! prev ) }
				title={ strings.title || 'OpenWP Assistant' }
			>
				<img src={ openwpLogo } alt="" />
			</button>
		</div>
	);
}
