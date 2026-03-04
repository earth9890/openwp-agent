import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cva } from 'class-variance-authority';
import { cn } from '../../lib/utils';

const buttonVariants = cva(
	'inline-flex appearance-none items-center justify-center gap-2 whitespace-nowrap rounded-lg border-solid text-sm font-semibold shadow-sm transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 disabled:pointer-events-none disabled:opacity-50',
	{
		variants: {
			variant: {
				default: 'border border-primary bg-primary text-white hover:border-primary-strong hover:bg-primary-strong hover:shadow-sm',
				destructive: 'border border-danger bg-danger text-white hover:border-[#8f1f16] hover:bg-[#8f1f16]',
				outline: 'border border-line bg-surface text-ink hover:border-[#b8c6db] hover:bg-bg-soft',
				secondary: 'border border-[#bdd0f1] bg-surface text-[#153567] hover:border-[#94b3eb] hover:bg-[#f4f8ff]',
				ghost: 'border border-transparent text-ink shadow-none hover:border-line hover:bg-bg-soft hover:shadow-none',
				link: 'text-primary underline-offset-4 hover:underline',
			},
			size: {
				default: 'h-10 px-4 py-2',
				xs: 'h-7 rounded-md px-2.5 text-xs',
				sm: 'h-9 rounded-md px-3',
				lg: 'h-11 rounded-md px-8',
				icon: 'h-10 w-10',
			},
		},
		defaultVariants: {
			variant: 'default',
			size: 'default',
		},
	}
);

const Button = React.forwardRef( ( {
	className,
	variant,
	size,
	asChild = false,
	...props
}, ref ) => {
	const Comp = asChild ? Slot : 'button';
	return (
		<Comp
			className={ cn( buttonVariants( { variant, size, className } ) ) }
			ref={ ref }
			{ ...props }
		/>
	);
} );

Button.displayName = 'Button';

export { Button, buttonVariants };
