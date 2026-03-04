import * as React from 'react';
import { cva } from 'class-variance-authority';
import { cn } from '../../lib/utils';

const alertVariants = cva(
	'relative w-full rounded-lg border p-4 text-sm [&>svg~*]:pl-7 [&>svg]:absolute [&>svg]:left-4 [&>svg]:top-4 [&>svg]:text-foreground',
	{
		variants: {
			variant: {
				default: 'border-line bg-[#f4f8ff] text-ink',
				destructive: 'border-[#b4231847] bg-[#b4231821] text-[#7f1d1d]',
				success: 'border-[#0f8b5740] bg-[#0f8b5721] text-[#065f46]',
				warning: 'border-[#9462003d] bg-[#94620019] text-[#6e4900]',
			},
		},
		defaultVariants: {
			variant: 'default',
		},
	}
);

const Alert = React.forwardRef( ( { className, variant, ...props }, ref ) => (
	<div
		ref={ ref }
		role="alert"
		className={ cn( alertVariants( { variant } ), className ) }
		{ ...props }
	/>
) );
Alert.displayName = 'Alert';

const AlertTitle = React.forwardRef( ( { className, ...props }, ref ) => (
	<h5
		ref={ ref }
		className={ cn( 'mb-1 font-medium leading-none tracking-tight', className ) }
		{ ...props }
	/>
) );
AlertTitle.displayName = 'AlertTitle';

const AlertDescription = React.forwardRef( ( { className, ...props }, ref ) => (
	<div
		ref={ ref }
		className={ cn( 'text-sm [&_p]:leading-relaxed', className ) }
		{ ...props }
	/>
) );
AlertDescription.displayName = 'AlertDescription';

export { Alert, AlertTitle, AlertDescription };
