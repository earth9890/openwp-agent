import Panel from '../../components/panel';
import Pill from '../../components/pill';
import EmptyState from '../../components/empty-state';
import {
	Button,
	Card,
	Table,
	TableBody,
	TableCell,
	TableHead,
	TableHeader,
	TableRow,
} from '../../components/ui';
import { fmtInt } from '../../../shared/utils';
import { strings } from './constants';

export default function BackupsTab( { items, onRequestRestore } ) {
	return (
		<Panel
			title={ strings.backupsTitle() }
			subtitle={ strings.backupsSubtitle() }
		>
			{ items.length ? (
				<Card className="overflow-hidden rounded-xl shadow-none">
						<Table>
							<TableHeader>
								<TableRow>
									<TableHead className="w-[100px]">
										{ strings.backupId() }
									</TableHead>
									<TableHead className="w-[180px]">
										{ strings.status() }
									</TableHead>
									<TableHead className="w-[170px]">
										{ strings.size() }
									</TableHead>
									<TableHead>
										{ strings.checksumLabel() }
									</TableHead>
									<TableHead className="w-[170px]">
										{ strings.created() }
									</TableHead>
									<TableHead className="w-[170px] text-right">
										{ strings.action() }
									</TableHead>
								</TableRow>
							</TableHeader>
							<TableBody>
								{ items.map( ( item ) => (
									<TableRow key={ `backup-${ item.id }` }>
										<TableCell className="font-mono text-xs text-muted">
											#{ item.id }
										</TableCell>
										<TableCell>
											<Pill status={ item.status } />
										</TableCell>
										<TableCell className="text-xs text-muted">
											{ fmtInt( item.file_size ) } bytes
										</TableCell>
										<TableCell className="font-mono text-xs text-muted">
											{ item.checksum || '-' }
										</TableCell>
										<TableCell className="text-xs text-muted">
											{ item.created_at || '-' }
										</TableCell>
										<TableCell className="text-right">
											<Button
												variant="secondary"
												size="xs"
												onClick={ () =>
													onRequestRestore( item )
												}
											>
												{ strings.restoreCritical() }
											</Button>
										</TableCell>
									</TableRow>
								) ) }
							</TableBody>
						</Table>
				</Card>
			) : (
				<EmptyState
					title={ strings.noBackupsYet() }
					message={ strings.noBackupsYetMessage() }
				/>
			) }
		</Panel>
	);
}
