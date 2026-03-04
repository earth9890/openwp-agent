import * as React from 'react';
import { cn } from '../../lib/utils';

const Textarea = React.forwardRef( ( { className, ...props }, ref ) => {
	return (
		<textarea
			className={ cn(
				'flex min-h-[110px] w-full appearance-none rounded-lg border border-solid border-line bg-[#f7fbff] px-3 py-2 text-sm text-ink shadow-none placeholder:text-muted/70 transition-colors focus-visible:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50',
				className
			) }
			ref={ ref }
			{ ...props }
		/>
	);
} );

Textarea.displayName = 'Textarea';

export { Textarea };
