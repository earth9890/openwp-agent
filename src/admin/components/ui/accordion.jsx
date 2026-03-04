import * as React from 'react';
import * as AccordionPrimitive from '@radix-ui/react-accordion';
import { ChevronDown } from 'lucide-react';
import { cn } from '../../lib/utils';

const Accordion = AccordionPrimitive.Root;

const AccordionItem = React.forwardRef( ( { className, ...props }, ref ) => (
	<AccordionPrimitive.Item
		ref={ ref }
		className={ cn( 'border-b border-solid border-line', className ) }
		{ ...props }
	/>
) );
AccordionItem.displayName = 'AccordionItem';

const AccordionTrigger = React.forwardRef(
	( { className, children, ...props }, ref ) => (
		<AccordionPrimitive.Header className="flex">
			<AccordionPrimitive.Trigger
				ref={ ref }
				className={ cn(
					'flex flex-1 appearance-none items-center justify-between rounded-md border-0 border-solid border-transparent bg-transparent px-0 py-3 text-left text-sm font-medium text-ink shadow-none transition-all hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20 [&[data-state=open]>svg]:rotate-180',
					className
				) }
				{ ...props }
			>
				{ children }
				<ChevronDown className="h-4 w-4 shrink-0 text-muted transition-transform duration-200" />
			</AccordionPrimitive.Trigger>
		</AccordionPrimitive.Header>
	)
);
AccordionTrigger.displayName = AccordionPrimitive.Trigger.displayName;

const AccordionContent = React.forwardRef(
	( { className, children, ...props }, ref ) => (
		<AccordionPrimitive.Content
			ref={ ref }
			className="overflow-hidden text-sm data-[state=closed]:animate-accordion-up data-[state=open]:animate-accordion-down"
			{ ...props }
		>
			<div className={ cn( 'pb-3 pt-1', className ) }>{ children }</div>
		</AccordionPrimitive.Content>
	)
);

AccordionContent.displayName = AccordionPrimitive.Content.displayName;

export { Accordion, AccordionItem, AccordionTrigger, AccordionContent };
