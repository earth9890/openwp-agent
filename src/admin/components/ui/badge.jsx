import * as React from 'react';
import { cva } from 'class-variance-authority';
import { cn } from '../../lib/utils';

const badgeVariants = cva(
	'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-primary/30 focus:ring-offset-2',
	{
		variants: {
			variant: {
				default: 'border-transparent bg-primary text-white hover:bg-primary-strong',
				secondary: 'border-transparent bg-surface-2 text-ink hover:bg-bg-soft',
				destructive: 'border-transparent bg-danger text-white hover:bg-[#8f1f16]',
				outline: 'text-ink border-line',
				success: 'border-[#0f8b5728] bg-[#0f8b5720] text-[#06643e]',
				warning: 'border-[#94620030] bg-[#94620020] text-[#6e4900]',
				danger: 'border-[#b4231833] bg-[#b4231821] text-[#8e1f16]',
				neutral: 'border-[#3c557f2b] bg-[#3c557f1c] text-[#304869]',
			},
		},
		defaultVariants: {
			variant: 'default',
		},
	}
);

function Badge( { className, variant, ...props } ) {
	return (
		<div className={ cn( badgeVariants( { variant } ), className ) } { ...props } />
	);
}

export { Badge, badgeVariants };
