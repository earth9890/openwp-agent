import * as React from 'react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import { cn } from '../../lib/utils';

const Dialog = DialogPrimitive.Root;
const DialogTrigger = DialogPrimitive.Trigger;
const DialogPortal = DialogPrimitive.Portal;
const DialogClose = DialogPrimitive.Close;

const DialogOverlay = React.forwardRef( ( { className, ...props }, ref ) => (
	<DialogPrimitive.Overlay
		ref={ ref }
		className={ cn(
			'fixed inset-0 z-50 bg-[#13203880] backdrop-blur-[2px] data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
			className
		) }
		{ ...props }
	/>
) );
DialogOverlay.displayName = DialogPrimitive.Overlay.displayName;

const DialogContent = React.forwardRef(
	( { className, children, ...props }, ref ) => (
		<DialogPortal>
			<DialogOverlay />
			<DialogPrimitive.Content
				ref={ ref }
				className={ cn(
					'fixed left-1/2 top-1/2 z-50 grid w-full max-w-[560px] -translate-x-1/2 -translate-y-1/2 gap-4 rounded-2xl border border-solid border-line bg-surface p-5 text-ink shadow-sm duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 max-[640px]:max-w-[calc(100vw-24px)]',
					className
				) }
				{ ...props }
			>
				{ children }
				<DialogPrimitive.Close className="absolute right-3.5 top-3.5 rounded-md border border-solid border-transparent p-1 text-muted transition-colors hover:border-line hover:bg-bg-soft hover:text-ink focus:outline-none focus:ring-2 focus:ring-primary/20">
					<X className="h-4 w-4" />
					<span className="sr-only">Close</span>
				</DialogPrimitive.Close>
			</DialogPrimitive.Content>
		</DialogPortal>
	)
);
DialogContent.displayName = DialogPrimitive.Content.displayName;

const DialogHeader = ( { className, ...props } ) => (
	<div
		className={ cn( 'flex flex-col space-y-1.5 text-left', className ) }
		{ ...props }
	/>
);

const DialogFooter = ( { className, ...props } ) => (
	<div
		className={ cn(
			'flex flex-col-reverse gap-2 min-[480px]:flex-row min-[480px]:justify-end',
			className
		) }
		{ ...props }
	/>
);

const DialogTitle = React.forwardRef( ( { className, ...props }, ref ) => (
	<DialogPrimitive.Title
		ref={ ref }
		className={ cn(
			'text-[18px] font-semibold leading-none tracking-[-0.01em]',
			className
		) }
		{ ...props }
	/>
) );
DialogTitle.displayName = DialogPrimitive.Title.displayName;

const DialogDescription = React.forwardRef(
	( { className, ...props }, ref ) => (
		<DialogPrimitive.Description
			ref={ ref }
			className={ cn( 'text-sm leading-relaxed text-muted', className ) }
			{ ...props }
		/>
	)
);
DialogDescription.displayName = DialogPrimitive.Description.displayName;

export {
	Dialog,
	DialogPortal,
	DialogOverlay,
	DialogClose,
	DialogTrigger,
	DialogContent,
	DialogHeader,
	DialogFooter,
	DialogTitle,
	DialogDescription,
};
