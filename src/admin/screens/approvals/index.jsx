import { Fragment, useState } from '@wordpress/element';
import Panel from '../../components/panel';
import Pill from '../../components/pill';
import EmptyState from '../../components/empty-state';
import {
	Alert,
	Button,
	Card,
	Table,
	TableBody,
	TableCell,
	TableHead,
	TableHeader,
	TableRow,
} from '../../components/ui';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { strings } from './constants';

function ParamRow( { label, value } ) {
	const display =
		typeof value === 'object' ? JSON.stringify( value ) : String( value );
	const isLong = display.length > 200;

	return (
		<tr className="border-b border-line/40 last:border-0">
			<td className="whitespace-nowrap py-1.5 pr-3 align-top font-mono text-xs font-semibold text-muted">
				{ label }
			</td>
			<td className="break-all py-1.5 text-xs text-ink">
				{ isLong ? display.slice( 0, 200 ) + '\u2026' : display }
			</td>
		</tr>
	);
}

function ParamTable( { params } ) {
	if (
		! params ||
		typeof params !== 'object' ||
		Object.keys( params ).length === 0
	) {
		return (
			<p className="text-xs text-muted">No parameters available.</p>
		);
	}

	return (
		<table className="w-full">
			<tbody>
				{ Object.entries( params ).map( ( [ key, val ] ) => (
					<ParamRow key={ key } label={ key } value={ val } />
				) ) }
			</tbody>
		</table>
	);
}

function DetailSection( { label, defaultOpen = true, children } ) {
	const [ open, setOpen ] = useState( defaultOpen );

	return (
		<div>
			<button
				type="button"
				onClick={ () => setOpen( ! open ) }
				className="!border-0 !shadow-none !bg-transparent outline-none focus:outline-none flex w-full items-center gap-1.5 py-1 text-[11px] font-bold uppercase tracking-[0.07em] text-muted transition-colors hover:text-ink"
			>
				{ open ? (
					<ChevronDown size={ 12 } />
				) : (
					<ChevronRight size={ 12 } />
				) }
				{ label }
			</button>
			{ open && <div className="mt-1">{ children }</div> }
		</div>
	);
}

export default function ApprovalsTab( {
	items,
	onRequestApprove,
	onRequestReject,
} ) {
	const [ expandedId, setExpandedId ] = useState( null );

	return (
		<Panel
			title={ strings.approvalQueue() }
			subtitle={ strings.approvalQueueSubtitle() }
		>
			{ items.length ? (
				<Card className="overflow-hidden rounded-xl shadow-none">
						<Table>
							<TableHeader>
								<TableRow>
									<TableHead className="w-[90px]">
										{ strings.id() }
									</TableHead>
									<TableHead>
										{ strings.action() }
									</TableHead>
									<TableHead className="w-[220px]">
										{ strings.status() }
									</TableHead>
									<TableHead className="w-[190px]">
										{ strings.created() }
									</TableHead>
									<TableHead className="w-[220px] text-right">
										{ strings.decision() }
									</TableHead>
								</TableRow>
							</TableHeader>
							<TableBody>
								{ items.map( ( item ) => {
									const isExpanded =
										expandedId === item.id;

									return (
										<Fragment
											key={ `approval-group-${ item.id }` }
										>
											<TableRow>
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
															className="!px-1.5 !py-px !text-[9px]"
														/>
														<Pill
															status={
																item.risk_level
															}
															label={
																item.risk_level
															}
															className="!px-1.5 !py-px !text-[9px]"
														/>
													</div>
												</TableCell>
												<TableCell className="text-xs text-muted">
													{ item.created_at ||
														'-' }
												</TableCell>
												<TableCell className="text-right">
													<div className="inline-flex items-center gap-2">
														<Button
															variant="ghost"
															size="xs"
															className="!h-6 !px-2 !text-[10px]"
															onClick={ () =>
																setExpandedId(
																	isExpanded
																		? null
																		: item.id
																)
															}
														>
															{ isExpanded
																? strings.hideDetails()
																: strings.openDetails() }
														</Button>
														{ item.status ===
															'pending' && (
															<>
																<Button
																	size="xs"
																	className="!h-6 !px-2 !text-[10px]"
																	onClick={ () =>
																		onRequestApprove(
																			item
																		)
																	}
																>
																	{ strings.approve() }
																</Button>
																<Button
																	variant="secondary"
																	size="xs"
																	className="!h-6 !px-2 !text-[10px]"
																	onClick={ () =>
																		onRequestReject(
																			item
																		)
																	}
																>
																	{ strings.reject() }
																</Button>
															</>
														) }
													</div>
												</TableCell>
											</TableRow>
											{ isExpanded && (
												<TableRow className="bg-bg-soft/35 hover:bg-bg-soft/35">
													<TableCell
														colSpan={ 5 }
													>
														<div className="grid gap-2.5 py-1">
															{ item.risk_level ===
																'critical' && (
																<Alert
																	variant="warning"
																	className="text-xs font-medium"
																>
																	{ strings.requiresTypedApproval() }
																</Alert>
															) }

															<DetailSection
																label="Action Parameters"
																defaultOpen
															>
																<div className="rounded-lg border-[0.5px] border-solid border-line/60 bg-white p-3">
																	<ParamTable
																		params={
																			item.params ||
																			{}
																		}
																	/>
																</div>
															</DetailSection>

															{ item.prompt && (
																<DetailSection
																	label="Original Prompt"
																	defaultOpen={
																		false
																	}
																>
																	<p className="rounded-lg border-[0.5px] border-solid border-line/60 bg-white p-3 text-xs text-ink">
																		{ item.prompt }
																	</p>
																</DetailSection>
															) }
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
			) : (
				<EmptyState
					title={ strings.noPendingApprovals() }
					message={ strings.noPendingApprovalsMessage() }
				/>
			) }
		</Panel>
	);
}
