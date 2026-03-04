import { Fragment, useMemo, useState } from '@wordpress/element';
import Panel from '../../components/panel';
import Pill from '../../components/pill';
import JsonBox from '../../components/json-box';
import EmptyState from '../../components/empty-state';
import {
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
} from '../../components/ui';
import { strings } from './constants';

function getUniqueValues( items, key ) {
	const values = new Set();
	items.forEach( ( item ) => {
		const value = item?.[ key ];
		if ( value ) {
			values.add( String( value ) );
		}
	} );
	return Array.from( values );
}

export default function LogsTab( { items, onRequestRollback } ) {
	const [ expandedId, setExpandedId ] = useState( null );
	const [ query, setQuery ] = useState( '' );
	const [ statusFilter, setStatusFilter ] = useState( 'all' );
	const [ riskFilter, setRiskFilter ] = useState( 'all' );

	const statusOptions = useMemo(
		() => getUniqueValues( items, 'status' ),
		[ items ]
	);
	const riskOptions = useMemo(
		() => getUniqueValues( items, 'risk_level' ),
		[ items ]
	);

	const filteredItems = useMemo( () => {
		const lowerQuery = query.trim().toLowerCase();
		return items.filter( ( item ) => {
			const matchesQuery =
				! lowerQuery ||
				String( item.action_key || '' )
					.toLowerCase()
					.includes( lowerQuery );
			const matchesStatus =
				statusFilter === 'all' || item.status === statusFilter;
			const matchesRisk =
				riskFilter === 'all' || item.risk_level === riskFilter;
			return matchesQuery && matchesStatus && matchesRisk;
		} );
	}, [ items, query, statusFilter, riskFilter ] );

	return (
		<Panel
			title={ strings.executionLogs() }
			subtitle={ strings.executionLogsSubtitle() }
		>
			{ items.length ? (
				<>
					<div className="mb-3 grid grid-cols-[minmax(0,1.4fr)_minmax(150px,1fr)_minmax(140px,1fr)] gap-2.5 max-[920px]:grid-cols-1">
						<Input
							placeholder={ strings.filterByAction() }
							value={ query }
							onChange={ ( e ) => setQuery( e.target.value ) }
						/>

						<Select
							value={ statusFilter }
							onValueChange={ setStatusFilter }
						>
							<SelectTrigger>
								<SelectValue placeholder={ strings.status() } />
							</SelectTrigger>
							<SelectContent>
								<SelectItem value="all">
									{ strings.allStatuses() }
								</SelectItem>
								{ statusOptions.map( ( status ) => (
									<SelectItem key={ status } value={ status }>
										{ status }
									</SelectItem>
								) ) }
							</SelectContent>
						</Select>

						<Select
							value={ riskFilter }
							onValueChange={ setRiskFilter }
						>
							<SelectTrigger>
								<SelectValue placeholder={ strings.risk() } />
							</SelectTrigger>
							<SelectContent>
								<SelectItem value="all">
									{ strings.allRisks() }
								</SelectItem>
								{ riskOptions.map( ( risk ) => (
									<SelectItem key={ risk } value={ risk }>
										{ risk }
									</SelectItem>
								) ) }
							</SelectContent>
						</Select>
					</div>

					<Card className="overflow-hidden rounded-xl shadow-none">
							<Table>
								<TableHeader>
									<TableRow>
										<TableHead className="w-[80px]">
											{ strings.id() }
										</TableHead>
										<TableHead>
											{ strings.action() }
										</TableHead>
										<TableHead className="w-[210px]">
											{ strings.status() }
										</TableHead>
										<TableHead className="w-[190px]">
											{ strings.created() }
										</TableHead>
										<TableHead className="w-[120px] text-right">
											{ strings.details() }
										</TableHead>
									</TableRow>
								</TableHeader>
								<TableBody>
									{ filteredItems.map( ( item ) => {
										const isExpanded =
											expandedId === item.id;
										const hasRollback = !! (
											item.rollback_snapshot &&
											Object.keys(
												item.rollback_snapshot
											).length > 0
										);

										return (
											<Fragment
												key={ `log-group-${ item.id }` }
											>
												<TableRow
													key={ `log-row-${ item.id }` }
												>
													<TableCell className="font-mono text-xs text-muted">
														#{ item.id }
													</TableCell>
													<TableCell className="font-medium">
														{ item.action_key }
													</TableCell>
													<TableCell>
														<div className="flex flex-wrap items-center gap-1.5">
															<Pill
																status={
																	item.status
																}
															/>
															<Pill
																status={
																	item.risk_level
																}
																label={
																	item.risk_level
																}
															/>
														</div>
													</TableCell>
													<TableCell className="text-xs text-muted">
														{ item.created_at ||
															'-' }
													</TableCell>
													<TableCell className="text-right">
														<Button
															variant="ghost"
															size="xs"
															onClick={ () =>
																setExpandedId(
																	isExpanded
																		? null
																		: item.id
																)
															}
														>
															{ isExpanded
																? strings.collapse()
																: strings.expand() }
														</Button>
													</TableCell>
												</TableRow>
												{ isExpanded && (
													<TableRow
														key={ `log-expanded-${ item.id }` }
														className="bg-bg-soft/35 hover:bg-bg-soft/35"
													>
														<TableCell
															colSpan={ 5 }
														>
															<div className="grid gap-3">
																<div className="flex flex-wrap items-center justify-between gap-2">
																	<div className="text-xs font-medium text-muted">
																		{ hasRollback
																			? strings.rollbackAvailable()
																			: strings.rollbackUnavailable() }
																	</div>
																	{ hasRollback && (
																		<Button
																			variant="secondary"
																			size="sm"
																			onClick={ () =>
																				onRequestRollback(
																					item
																				)
																			}
																		>
																			{ strings.rollback() }
																		</Button>
																	) }
																</div>
																<JsonBox
																	value={
																		item.result ||
																		{}
																	}
																/>
															</div>
														</TableCell>
													</TableRow>
												) }
											</Fragment>
										);
									} ) }
								</TableBody>
							</Table>
					</Card>

					{ ! filteredItems.length && (
						<EmptyState
							title={ strings.noMatchingLogs() }
						/>
					) }
				</>
			) : (
				<EmptyState
					title={ strings.noLogsYet() }
					message={ strings.noLogsYetMessage() }
				/>
			) }
		</Panel>
	);
}
