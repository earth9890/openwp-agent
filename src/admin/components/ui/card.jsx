import * as React from 'react';
import { cn } from '../../lib/utils';

const Card = React.forwardRef( ( { className, ...props }, ref ) => (
	<div
		ref={ ref }
		className={ cn( 'rounded-2xl border border-solid border-line bg-surface/95 text-ink shadow-openwp backdrop-blur-[1px]', className ) }
		{ ...props }
	/>
) );
Card.displayName = 'Card';

const CardHeader = React.forwardRef( ( { className, ...props }, ref ) => (
	<div
		ref={ ref }
		className={ cn( 'flex flex-col space-y-1.5 p-6', className ) }
		{ ...props }
	/>
) );
CardHeader.displayName = 'CardHeader';

const CardTitle = React.forwardRef( ( { className, ...props }, ref ) => (
	<h3
		ref={ ref }
		className={ cn( 'text-[19px] font-semibold leading-tight tracking-[-0.02em]', className ) }
		{ ...props }
	/>
) );
CardTitle.displayName = 'CardTitle';

const CardDescription = React.forwardRef( ( { className, ...props }, ref ) => (
	<p
		ref={ ref }
		className={ cn( 'text-xs leading-5 text-muted', className ) }
		{ ...props }
	/>
) );
CardDescription.displayName = 'CardDescription';

const CardContent = React.forwardRef( ( { className, ...props }, ref ) => (
	<div ref={ ref } className={ cn( 'p-6 pt-0', className ) } { ...props } />
) );
CardContent.displayName = 'CardContent';

const CardFooter = React.forwardRef( ( { className, ...props }, ref ) => (
	<div
		ref={ ref }
		className={ cn( 'flex items-center p-6 pt-0', className ) }
		{ ...props }
	/>
) );
CardFooter.displayName = 'CardFooter';

export {
	Card,
	CardHeader,
	CardFooter,
	CardTitle,
	CardDescription,
	CardContent,
};
