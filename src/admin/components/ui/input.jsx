import * as React from 'react';
import { cn } from '../../lib/utils';

const Input = React.forwardRef( ( { className, type, ...props }, ref ) => {
	return (
		<input
			type={ type }
			className={ cn(
				'flex h-10 w-full appearance-none rounded-lg border border-solid border-line bg-[#f7fbff] px-3 py-2 text-sm text-ink shadow-none file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted/70 transition-colors focus-visible:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50',
				className
			) }
			ref={ ref }
			{ ...props }
		/>
	);
} );

Input.displayName = 'Input';

export { Input };
