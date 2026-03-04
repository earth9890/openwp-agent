import { statusTone } from '../../../shared/utils';
import { strings } from './constants';
import { cn } from '../../lib/utils';
import { Badge } from '../ui';

export default function Pill( { status, label, className = '' } ) {
	const tone = statusTone(status);

	const variant = {
		success: 'success',
		warning: 'warning',
		danger: 'danger',
		neutral: 'neutral',
	}[tone] || 'neutral';

	return (
		<Badge
			className={ cn(
				'px-2 py-[3px] text-[10px] font-bold uppercase tracking-[0.08em]',
				className
			) }
			variant={ variant }
		>
			{label || status || strings.unknown()}
		</Badge>
	);
}
