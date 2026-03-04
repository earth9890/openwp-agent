import { Toaster as Sonner } from 'sonner';

const Toaster = ( { ...props } ) => {
	return (
		<Sonner
			theme="light"
			className="toaster group"
			position="bottom-right"
			toastOptions={ {
				classNames: {
					toast: 'group toast group-[.toaster]:bg-surface group-[.toaster]:text-ink group-[.toaster]:border-line group-[.toaster]:shadow-sm group-[.toaster]:font-archivo',
					description: 'group-[.toast]:text-muted',
					actionButton:
						'group-[.toast]:bg-primary group-[.toast]:text-white',
					cancelButton:
						'group-[.toast]:bg-bg-soft group-[.toast]:text-ink',
					success:
						'group-[.toaster]:border-success/30 [&>[data-icon]]:text-success',
					error: 'group-[.toaster]:border-danger/30 [&>[data-icon]]:text-danger',
					warning:
						'group-[.toaster]:border-warning/30 [&>[data-icon]]:text-warning',
				},
			} }
			{ ...props }
		/>
	);
};

export { Toaster };
