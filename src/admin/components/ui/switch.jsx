import * as React from 'react';
import * as SwitchPrimitives from '@radix-ui/react-switch';
import { cn } from '../../lib/utils';

const Switch = React.forwardRef( ( { className, ...props }, ref ) => (
	<SwitchPrimitives.Root
		className={ cn(
			'peer inline-flex !h-5 !w-9 !min-h-0 !min-w-0 shrink-0 cursor-pointer appearance-none items-center overflow-hidden rounded-full border border-solid border-transparent !p-0 text-transparent shadow-none transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=unchecked]:bg-[#c4d2e6]',
			className
		) }
		{ ...props }
		ref={ ref }
	>
		<SwitchPrimitives.Thumb
			className={ cn(
				'pointer-events-none block h-4 w-4 rounded-full bg-white shadow-sm ring-0 transition-transform data-[state=checked]:translate-x-4 data-[state=unchecked]:translate-x-0'
			) }
		/>
	</SwitchPrimitives.Root>
) );

Switch.displayName = SwitchPrimitives.Root.displayName;

export { Switch };
