import { useMemo, useState } from '@wordpress/element';
import Panel from '../../components/panel';
import EmptyState from '../../components/empty-state';
import {
	Alert,
	Badge,
	Button,
	Card,
	Input,
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
	Table,
	TableBody,
	TableCell,
	TableHead,
	TableHeader,
	TableRow,
	Textarea,
} from '../../components/ui';
import { MEMORY_TYPE_OPTIONS, strings } from './constants';

const INITIAL_FORM = {
	type: 'preference',
	key: '',
	value: '',
	tags: '',
};

function normalizeKey( value ) {
	return value
		.trim()
		.toLowerCase()
		.replace( /[^a-z0-9-_ ]+/g, '' )
		.replace( /\s+/g, '-' );
}

function formatDate( value ) {
	if ( ! value ) {
		return '-';
	}

	const isoValue = value.includes( ' ' )
		? `${ value.replace( ' ', 'T' ) }Z`
		: value;
	const parsed = new Date( isoValue );
	if ( Number.isNaN( parsed.getTime() ) ) {
		return value;
	}

	return parsed.toLocaleString();
}

function typeLabel( type ) {
	const match = MEMORY_TYPE_OPTIONS.find( ( item ) => item.value === type );
	return match?.label || type;
}

export default function MemoryTab( {
	items = [],
	enabled = true,
	onCreate,
	onDelete,
} ) {
	const [ form, setForm ] = useState( INITIAL_FORM );
	const [ formError, setFormError ] = useState( '' );
	const [ submitting, setSubmitting ] = useState( false );
	const [ removingKey, setRemovingKey ] = useState( '' );
	const [ query, setQuery ] = useState( '' );
	const [ typeFilter, setTypeFilter ] = useState( 'all' );

	const filteredItems = useMemo( () => {
		const lowerQuery = query.trim().toLowerCase();
		return items.filter( ( item ) => {
			const matchesType =
				typeFilter === 'all' || item.type === typeFilter;

			if ( ! lowerQuery ) {
				return matchesType;
			}

			const haystack = [
				item.key || '',
				item.text || '',
				...( item.tags || [] ),
			]
				.join( ' ' )
				.toLowerCase();

			return matchesType && haystack.includes( lowerQuery );
		} );
	}, [ items, query, typeFilter ] );

	const handleCreate = async () => {
		setFormError( '' );

		const payload = {
			type: form.type,
			key: normalizeKey( form.key ),
			value: form.value.trim(),
			tags: form.tags
				.split( ',' )
				.map( ( tag ) => tag.trim() )
				.filter( Boolean ),
		};

		if ( ! payload.type ) {
			setFormError( strings.requiredType() );
			return;
		}
		if ( ! payload.key ) {
			setFormError( strings.requiredKey() );
			return;
		}
		if ( ! payload.value ) {
			setFormError( strings.requiredValue() );
			return;
		}

		setSubmitting( true );
		try {
			await onCreate( payload );
			setForm( ( prev ) => ( { ...prev, key: '', value: '', tags: '' } ) );
		} catch ( err ) {
			setFormError( err?.message || strings.requiredValue() );
		} finally {
			setSubmitting( false );
		}
	};

	const handleDelete = async ( item ) => {
		const confirmed = window.confirm( strings.confirmDelete( item.key ) );
		if ( ! confirmed ) {
			return;
		}

		setRemovingKey( `${ item.type }::${ item.key }` );
		try {
			await onDelete( { type: item.type, key: item.key } );
		} finally {
			setRemovingKey( '' );
		}
	};

	return (
		<Panel title={ strings.title() } subtitle={ strings.subtitle() }>
			{ ! enabled && (
				<Alert className="mb-3" variant="warning">
					{ strings.memoryDisabled() }
				</Alert>
			) }

			<Card className="mb-3 rounded-xl border-[0.5px] border-solid border-line shadow-none">
				<div className="grid gap-2.5 p-3">
					<div className="flex flex-wrap items-end justify-between gap-2.5">
						<div className="grid gap-1.5">
							<span className="text-[11px] font-semibold uppercase tracking-[0.07em] text-muted">
								{ strings.type() }
							</span>
							<Select value={ form.type } onValueChange={ ( value ) => setForm( ( prev ) => ( { ...prev, type: value } ) ) }>
								<SelectTrigger className="w-[220px] max-[620px]:w-full">
									<SelectValue />
								</SelectTrigger>
								<SelectContent>
									{ MEMORY_TYPE_OPTIONS.map( ( option ) => (
										<SelectItem
											key={ option.value }
											value={ option.value }
										>
											{ option.label }
										</SelectItem>
									) ) }
								</SelectContent>
							</Select>
						</div>

						<Button
							onClick={ handleCreate }
							disabled={ ! enabled || submitting }
							className="min-w-[140px] max-[620px]:w-full"
						>
							{ submitting ? strings.saving() : strings.save() }
						</Button>
					</div>

						<div className="grid grid-cols-2 gap-2.5 max-[960px]:grid-cols-1">
							<div className="grid gap-1.5">
								<span className="text-[11px] font-semibold uppercase tracking-[0.07em] text-muted">
									{ strings.key() }
								</span>
							<Input
								value={ form.key }
								onChange={ ( event ) =>
									setForm( ( prev ) => ( {
										...prev,
										key: event.target.value,
									} ) )
									}
									placeholder={ strings.keyPlaceholder() }
								/>
								<span className="invisible text-[11px] select-none">
									{ strings.tagsHint() }
								</span>
							</div>
							<div className="grid gap-1.5">
								<span className="text-[11px] font-semibold uppercase tracking-[0.07em] text-muted">
									{ strings.tags() }
								</span>
							<Input
								value={ form.tags }
								onChange={ ( event ) =>
									setForm( ( prev ) => ( {
										...prev,
										tags: event.target.value,
									} ) )
									}
									placeholder={ strings.tagsPlaceholder() }
								/>
								<span className="text-[11px] text-muted">
									{ strings.tagsHint() }
								</span>
							</div>
						</div>

						<div className="grid gap-1.5">
						<span className="text-[11px] font-semibold uppercase tracking-[0.07em] text-muted">
							{ strings.value() }
						</span>
						<Textarea
							value={ form.value }
							onChange={ ( event ) =>
								setForm( ( prev ) => ( {
									...prev,
									value: event.target.value,
								} ) )
							}
							placeholder={ strings.valuePlaceholder() }
							rows={ 4 }
						/>
					</div>

					{ formError && <Alert variant="destructive">{ formError }</Alert> }
				</div>
			</Card>

			<div className="mb-3 grid grid-cols-[minmax(0,1.4fr)_minmax(180px,1fr)] gap-2.5 max-[920px]:grid-cols-1">
				<Input
					placeholder={ strings.search() }
					value={ query }
					onChange={ ( event ) => setQuery( event.target.value ) }
				/>
				<Select value={ typeFilter } onValueChange={ setTypeFilter }>
					<SelectTrigger>
						<SelectValue placeholder={ strings.type() } />
					</SelectTrigger>
					<SelectContent>
						<SelectItem value="all">{ strings.allTypes() }</SelectItem>
						{ MEMORY_TYPE_OPTIONS.map( ( option ) => (
							<SelectItem key={ option.value } value={ option.value }>
								{ option.label }
							</SelectItem>
						) ) }
					</SelectContent>
				</Select>
			</div>

			{ ! items.length ? (
				<EmptyState
					title={ strings.noMemory() }
					message={ strings.noMemoryMessage() }
				/>
			) : ! filteredItems.length ? (
				<EmptyState title={ strings.noFilteredMemory() } />
			) : (
				<Card className="overflow-hidden rounded-xl shadow-none">
						<Table>
							<TableHeader>
								<TableRow>
									<TableHead className="w-[150px]">
										{ strings.type() }
									</TableHead>
									<TableHead className="w-[220px]">
										{ strings.key() }
									</TableHead>
									<TableHead>{ strings.value() }</TableHead>
									<TableHead className="w-[180px]">
										{ strings.updated() }
									</TableHead>
									<TableHead className="w-[110px] text-right">
										{ strings.actions() }
									</TableHead>
								</TableRow>
							</TableHeader>
							<TableBody>
								{ filteredItems.map( ( item ) => {
									const deleteId = `${ item.type }::${ item.key }`;

									return (
										<TableRow key={ deleteId }>
											<TableCell>
												<Badge variant="neutral">
													{ typeLabel( item.type ) }
												</Badge>
											</TableCell>
											<TableCell className="font-mono text-xs">
												{ item.key }
											</TableCell>
											<TableCell>
												<div className="grid gap-1.5">
													<p className="m-0 break-words text-sm text-ink">
														{ item.text }
													</p>
													{ item.tags?.length > 0 && (
														<div className="flex flex-wrap gap-1">
															{ item.tags.map(
																( tag ) => (
																	<Badge
																		key={
																			tag
																		}
																		variant="outline"
																		className="rounded-md px-1.5 py-0 text-[10px] uppercase tracking-[0.06em]"
																	>
																		{ tag }
																	</Badge>
																)
															) }
														</div>
													) }
												</div>
											</TableCell>
											<TableCell className="text-xs text-muted">
												{ formatDate( item.updated_at ) }
											</TableCell>
											<TableCell className="text-right">
												<Button
													size="xs"
													variant="ghost"
													disabled={
														! enabled ||
														removingKey ===
															deleteId
													}
													onClick={ () =>
														handleDelete( item )
													}
												>
													{ strings.delete() }
												</Button>
											</TableCell>
										</TableRow>
									);
								} ) }
							</TableBody>
						</Table>
				</Card>
			) }
		</Panel>
	);
}
