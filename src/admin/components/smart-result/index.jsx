/**
 * SmartResult – auto-detecting response renderer.
 *
 * Inspects the `data` object returned by an action execution and picks the
 * best visual treatment:
 *   - Array-like  (data.items / data.rows / data.results / Array)  → Table
 *   - Flat object  ({post_id, link}, {user_id}, …)                → Key-value card
 *   - Empty / null                                                  → nothing
 *
 * @package OpenWP
 */

import { ExternalLink, Check, X } from 'lucide-react';

// ---------------------------------------------------------------------------
// Detection helpers
// ---------------------------------------------------------------------------

function getArrayField( data ) {
	if ( Array.isArray( data ) && data.length > 0 ) {
		return '__root';
	}

	// Check well-known names first for stable ordering.
	const KNOWN = [ 'items', 'rows', 'results', 'products', 'orders',
		'customers', 'reviews', 'plugins', 'themes', 'users', 'comments',
		'posts', 'terms', 'media', 'taxonomies', 'post_types', 'languages',
		'translations', 'updated' ];

	for ( const key of KNOWN ) {
		if ( Array.isArray( data[ key ] ) ) {
			return key;
		}
	}

	// Fallback: find the first array-of-objects field.
	for ( const [ key, val ] of Object.entries( data ) ) {
		if (
			Array.isArray( val ) &&
			val.length > 0 &&
			typeof val[ 0 ] === 'object' &&
			val[ 0 ] !== null
		) {
			return key;
		}
	}

	return null;
}

function detectShape( data ) {
	if ( ! data || ( typeof data === 'object' && Object.keys( data ).length === 0 ) ) {
		return 'empty';
	}
	if ( getArrayField( data ) ) {
		return 'table';
	}
	if ( typeof data === 'object' && ! Array.isArray( data ) ) {
		return 'keyvalue';
	}
	return 'empty';
}

function extractRows( data ) {
	const field = getArrayField( data );
	if ( field === '__root' ) {
		return data;
	}
	return field ? data[ field ] : [];
}

function extractMeta( data ) {
	const meta = {};
	if ( data.total !== undefined ) {
		meta.total = data.total;
	}
	if ( data.total_pages !== undefined ) {
		meta.totalPages = data.total_pages;
	}
	if ( data.count !== undefined ) {
		meta.count = data.count;
	}
	if ( data.statement_type !== undefined ) {
		meta.statementType = data.statement_type;
	}
	if ( data.affected_rows !== undefined ) {
		meta.affectedRows = data.affected_rows;
	}
	return meta;
}

// ---------------------------------------------------------------------------
// Column logic
// ---------------------------------------------------------------------------

const PRIORITY_COLS = [
	'ID',
	'id',
	'number',
	'order_number',
	'term_id',
	'user_id',
	'post_title',
	'name',
	'user_login',
	'display_name',
	'title',
	'slug',
	'file',
	'table',
	'post_type',
	'post_status',
	'status',
	'active',
	'version',
	'total',
	'currency',
	'date_created',
];

const SKIP_COLS = [
	'post_password',
	'post_content_filtered',
	'post_content',
	'post_excerpt',
	'to_ping',
	'pinged',
	'guid',
	'filter',
];

function formatLabel( key ) {
	return key
		.replace( /^(post_|user_|term_|comment_)/, '' )
		.replace( /_/g, ' ' )
		.replace( /\bid\b/gi, 'ID' )
		.replace( /\burl\b/gi, 'URL' )
		.replace( /\b\w/g, ( l ) => l.toUpperCase() );
}

function isComplexValue( val ) {
	if ( val === null || val === undefined ) {
		return false;
	}
	if ( Array.isArray( val ) && val.length > 0 && typeof val[ 0 ] === 'object' ) {
		return true;
	}
	if ( typeof val === 'object' && ! Array.isArray( val ) ) {
		return true;
	}
	return false;
}

function deriveColumns( rows ) {
	if ( ! rows.length ) {
		return [];
	}

	const keys = new Set();
	rows.forEach( ( row ) => {
		if ( typeof row === 'object' && row !== null ) {
			Object.keys( row ).forEach( ( k ) => keys.add( k ) );
		}
	} );

	// Drop columns where the majority of values are nested objects/arrays.
	const filtered = Array.from( keys ).filter( ( k ) => {
		if ( SKIP_COLS.includes( k ) ) {
			return false;
		}
		const complexCount = rows.filter( ( row ) =>
			isComplexValue( row?.[ k ] )
		).length;
		return complexCount / rows.length < 0.6;
	} );

	filtered.sort( ( a, b ) => {
		const ai = PRIORITY_COLS.indexOf( a );
		const bi = PRIORITY_COLS.indexOf( b );
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
	} );

	const max = 6;
	return filtered.slice( 0, max ).map( ( key ) => ( {
		key,
		label: formatLabel( key ),
	} ) );
}

// ---------------------------------------------------------------------------
// Cell renderer
// ---------------------------------------------------------------------------

function isUrl( val ) {
	return (
		typeof val === 'string' &&
		( val.startsWith( 'http://' ) || val.startsWith( 'https://' ) )
	);
}

const STATUS_TONES = {
	publish: 'bg-success/15 text-success',
	published: 'bg-success/15 text-success',
	active: 'bg-success/15 text-success',
	success: 'bg-success/15 text-success',
	draft: 'bg-warning/15 text-warning',
	pending: 'bg-warning/15 text-warning',
	inactive: 'bg-muted/20 text-muted',
	trash: 'bg-danger/15 text-danger',
	failed: 'bg-danger/15 text-danger',
	private: 'bg-muted/20 text-muted',
	future: 'bg-primary/15 text-primary',
};

function CellValue( { value, colKey } ) {
	if ( value === null || value === undefined ) {
		return <span className="text-muted/60">&mdash;</span>;
	}

	// Boolean
	if ( typeof value === 'boolean' ) {
		return value ? (
			<span className="inline-flex items-center gap-0.5 font-semibold text-success">
				<Check size={ 12 } /> Yes
			</span>
		) : (
			<span className="inline-flex items-center gap-0.5 font-semibold text-danger">
				<X size={ 12 } /> No
			</span>
		);
	}

	// Array
	if ( Array.isArray( value ) ) {
		if ( ! value.length ) {
			return <span className="text-muted/60">&mdash;</span>;
		}
		// Array of objects → show count badge
		if ( typeof value[ 0 ] === 'object' && value[ 0 ] !== null ) {
			return (
				<span className="inline-block rounded bg-bg-soft px-1.5 py-0.5 font-mono text-[10px] text-muted">
					{ value.length } items
				</span>
			);
		}
		const joined = value.join( ', ' );
		return (
			<span>
				{ joined.length > 60 ? joined.slice( 0, 57 ) + '\u2026' : joined }
			</span>
		);
	}

	// Nested object → show most readable scalar field, fallback to field count
	if ( typeof value === 'object' ) {
		const READABLE_KEYS = [ 'email', 'name', 'title', 'first_name', 'label', 'slug', 'sku' ];
		let display = null;
		for ( const rk of READABLE_KEYS ) {
			if ( typeof value[ rk ] === 'string' && value[ rk ].trim() !== '' ) {
				display = value[ rk ];
				break;
			}
		}
		if ( ! display ) {
			const firstStr = Object.values( value ).find(
				( v ) => typeof v === 'string' && v.trim() !== ''
			);
			display = firstStr || `{ ${ Object.keys( value ).length } fields }`;
		}
		const text =
			display.length > 40 ? display.slice( 0, 37 ) + '\u2026' : display;
		return (
			<code className="rounded bg-bg-soft px-1 py-0.5 text-[10px]">
				{ text }
			</code>
		);
	}

	const str = String( value );

	// URL
	if ( isUrl( str ) ) {
		let display;
		try {
			const u = new URL( str );
			display = u.pathname === '/' ? u.hostname : u.pathname;
			if ( display.length > 40 ) {
				display = display.slice( 0, 37 ) + '\u2026';
			}
		} catch {
			display = str.slice( 0, 40 );
		}
		return (
			<a
				href={ str }
				target="_blank"
				rel="noopener noreferrer"
				className="inline-flex items-center gap-1 text-primary hover:underline"
			>
				{ display }
				<ExternalLink size={ 10 } />
			</a>
		);
	}

	// Status/state columns → pill
	if (
		colKey === 'status' ||
		colKey === 'post_status' ||
		colKey === 'active'
	) {
		const tone =
			STATUS_TONES[ str.toLowerCase() ] || 'bg-muted/15 text-muted';
		return (
			<span
				className={ `inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${ tone }` }
			>
				{ str }
			</span>
		);
	}

	// ID-like numeric
	if ( /(_id|^ID$|^id$)/.test( colKey ) && ! isNaN( value ) ) {
		return <span className="font-mono text-muted">{ value }</span>;
	}

	// Long text truncation
	if ( str.length > 80 ) {
		return <span title={ str }>{ str.slice( 0, 77 ) }&hellip;</span>;
	}

	return <span>{ str }</span>;
}

// ---------------------------------------------------------------------------
// Table view
// ---------------------------------------------------------------------------

function TableView( { rows, meta, arrayFieldName } ) {
	if ( ! rows.length ) {
		const label = arrayFieldName
			? formatLabel( arrayFieldName )
			: 'items';
		return (
			<div className="rounded-lg border-[0.5px] border-solid border-line bg-surface-2 px-4 py-3 text-xs text-muted">
				No { label.toLowerCase() } found.
			</div>
		);
	}

	const columns = deriveColumns( rows );

	if ( ! columns.length ) {
		return null;
	}

	const totalCols =
		rows.length > 0 && typeof rows[ 0 ] === 'object'
			? Object.keys( rows[ 0 ] ).length
			: 0;
	const hiddenCols = totalCols - columns.length;

	return (
		<div>
			{ /* Meta line */ }
			{ Object.keys( meta ).length > 0 && (
				<div className="mb-1.5 flex flex-wrap gap-3 text-[11px] text-muted">
					{ meta.total !== undefined && (
						<span>{ meta.total } total</span>
					) }
					{ meta.count !== undefined && (
						<span>{ meta.count } rows returned</span>
					) }
					{ meta.affectedRows !== undefined && (
						<span>{ meta.affectedRows } rows affected</span>
					) }
					{ meta.statementType && (
						<span className="rounded bg-bg-soft px-1.5 py-0.5 font-mono text-[10px] uppercase">
							{ meta.statementType }
						</span>
					) }
					{ meta.totalPages !== undefined && meta.totalPages > 1 && (
						<span>page 1 of { meta.totalPages }</span>
					) }
				</div>
			) }

			{ /* Scrollable table */ }
			<div className="overflow-x-auto rounded-lg border-[0.5px] border-solid border-line">
				<table className="w-max min-w-full text-left text-xs">
					<thead>
						<tr className="border-b border-line bg-surface-2">
							{ columns.map( ( col ) => (
								<th
									key={ col.key }
									className="whitespace-nowrap px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-muted"
								>
									{ col.label }
								</th>
							) ) }
						</tr>
					</thead>
					<tbody>
						{ rows.map( ( row, i ) => (
							<tr
								key={ i }
								className={ `border-b border-line/50 last:border-0 ${
									i % 2 === 1 ? 'bg-bg-soft/30' : ''
								}` }
							>
								{ columns.map( ( col ) => (
									<td
										key={ col.key }
										className="max-w-[280px] px-3 py-1.5 text-ink"
									>
										<CellValue
											value={ row[ col.key ] }
											colKey={ col.key }
										/>
									</td>
								) ) }
							</tr>
						) ) }
					</tbody>
				</table>
			</div>

			{ hiddenCols > 0 && (
				<p className="mt-1 text-[10px] text-muted">
					Showing { columns.length } of { totalCols } columns.
				</p>
			) }
		</div>
	);
}

// ---------------------------------------------------------------------------
// Key-value view
// ---------------------------------------------------------------------------

const ARRAY_FIELDS = [ 'items', 'rows', 'results', 'products', 'orders',
	'customers', 'reviews', 'plugins', 'themes', 'users', 'comments',
	'posts', 'terms', 'media', 'taxonomies', 'post_types', 'languages',
	'translations', 'updated' ];
const META_FIELDS = [ 'total', 'total_pages', 'count', 'statement_type', 'reply' ];
const KV_SKIP = [ ...ARRAY_FIELDS, ...META_FIELDS ];

function KeyValueView( { data } ) {
	const pairs = [];
	const nested = [];

	for ( const [ key, val ] of Object.entries( data ) ) {
		if ( KV_SKIP.includes( key ) ) {
			continue;
		}
		if (
			Array.isArray( val ) &&
			val.length > 0 &&
			typeof val[ 0 ] === 'object'
		) {
			nested.push( { key, rows: val } );
		} else {
			pairs.push( { key, value: val } );
		}
	}

	return (
		<div className="grid gap-2">
			{ pairs.length > 0 && (
				<div className="grid gap-1.5 rounded-lg border-[0.5px] border-solid border-line bg-surface-2 p-3">
					{ pairs.map( ( { key, value } ) => (
						<div
							key={ key }
							className="flex items-start gap-3 text-xs"
						>
							<span className="min-w-[110px] shrink-0 font-mono font-semibold text-muted">
								{ formatLabel( key ) }
							</span>
							<span className="text-ink">
								<CellValue value={ value } colKey={ key } />
							</span>
						</div>
					) ) }
				</div>
			) }

			{ nested.map( ( { key, rows } ) => (
				<div key={ key }>
					<div className="mb-1 text-[10px] font-bold uppercase tracking-wider text-muted">
						{ formatLabel( key ) }
					</div>
					<TableView rows={ rows } meta={ {} } arrayFieldName={ key } />
				</div>
			) ) }
		</div>
	);
}

// ---------------------------------------------------------------------------
// Main export
// ---------------------------------------------------------------------------

export default function SmartResult( { data } ) {
	if (
		! data ||
		( typeof data === 'object' && Object.keys( data ).length === 0 )
	) {
		return null;
	}

	const shape = detectShape( data );

	if ( shape === 'empty' ) {
		return null;
	}

	if ( shape === 'table' ) {
		const arrayField = getArrayField( data );
		const rows = extractRows( data );
		const meta = extractMeta( data );
		return <TableView rows={ rows } meta={ meta } arrayFieldName={ arrayField !== '__root' ? arrayField : null } />;
	}

	return <KeyValueView data={ data } />;
}
