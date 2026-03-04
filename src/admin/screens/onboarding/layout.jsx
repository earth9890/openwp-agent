import { __ } from '@wordpress/i18n';
import { Check, ChevronLeft, ChevronRight, Loader2, X } from 'lucide-react';
import { Button, cn, Separator } from '../../components/ui';
import openwpLogo from '../../../assets/openwp.png';

function StepIndicator( { step, index, currentIndex, totalSteps, onNavigate } ) {
	const stepNumber = index + 1;
	const isActive = index === currentIndex;
	const isCompleted = index < currentIndex;
	const isClickable = ! isActive;
	const isLast = index === totalSteps - 1;

	return (
		<>
			<button
				type="button"
				onClick={ isClickable ? () => onNavigate( step.key ) : undefined }
				disabled={ ! isClickable }
				className={ cn(
					'flex items-center gap-2 rounded-full border-0 bg-transparent p-0 transition-colors',
					isClickable && 'cursor-pointer',
					! isClickable && 'cursor-default'
				) }
				aria-label={ step.label }
				aria-current={ isActive ? 'step' : undefined }
			>
				<div
					className={ cn(
						'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-all',
						isActive && 'bg-primary text-white shadow-sm shadow-primary/25',
						isCompleted && 'bg-success text-white',
						! isActive && ! isCompleted && 'border border-line bg-surface text-muted'
					) }
				>
					{ isCompleted ? (
						<Check className="h-3.5 w-3.5" strokeWidth={ 3 } />
					) : (
						stepNumber
					) }
				</div>
				<span
					className={ cn(
						'hidden text-sm font-medium sm:inline',
						isActive && 'text-ink',
							isCompleted && 'text-success',
						! isActive && ! isCompleted && 'text-muted'
					) }
				>
					{ step.label }
				</span>
			</button>
			{ ! isLast && (
				<div
					className={ cn(
						'mx-1 h-px w-6 sm:w-10',
							index < currentIndex ? 'bg-success/35' : 'bg-line'
					) }
				/>
			) }
		</>
	);
}

export default function OnboardingLayout( {
	steps = [],
	currentIndex = 0,
	title = '',
	subtitle = '',
	children,
	onBack,
	onContinue,
	onExit,
	showBack = true,
	showContinue = true,
	showSkip = true,
	backDisabled = false,
	continueDisabled = false,
	continueLoading = false,
	skipDisabled = false,
	backLabel,
	skipLabel,
	continueLabel,
	onSkip,
	onNavigate,
	isWide = false,
} ) {
	return (
		<div className="min-h-[calc(100dvh-40px)] bg-[radial-gradient(circle_at_14%_8%,#ffffff_0%,#f7fcff_28%,#eef6ff_62%,#e8f4f2_100%)] px-4 py-6 sm:px-6 sm:py-8">
			{ /* Top bar */ }
			<div className="mx-auto flex w-full max-w-3xl items-center justify-between gap-4 px-1">
				<div className="flex items-center gap-2.5">
					<img
						src={ openwpLogo }
						alt={ __( 'OpenWP', 'openwp' ) }
						className="h-10 w-10"
					/>
					<span className="text-sm font-bold tracking-tight text-ink">
						{ __( 'OpenWP', 'openwp' ) }
					</span>
				</div>

				<Button
					variant="ghost"
					size="sm"
					className="text-muted hover:text-ink"
					onClick={ onExit }
				>
					<X className="h-3.5 w-3.5" />
					{ __( 'Exit Setup', 'openwp' ) }
				</Button>
			</div>

			{ /* Stepper */ }
			<div className="mx-auto mt-6 flex w-full max-w-3xl items-center justify-center gap-0">
				{ steps.map( ( step, index ) => (
					<StepIndicator
						key={ step.key }
						step={ step }
						index={ index }
						currentIndex={ currentIndex }
						totalSteps={ steps.length }
						onNavigate={ onNavigate }
					/>
				) ) }
			</div>

			{ /* Content card */ }
			<div
				className={ cn(
						'mx-auto mt-8 w-full overflow-hidden rounded-3xl border border-solid border-line bg-[linear-gradient(140deg,#ffffff_0%,#f7fbff_64%,#eef3ff_100%)] shadow-sm',
					isWide ? 'max-w-[640px]' : 'max-w-[560px]'
				) }
			>
				{ /* Card header */ }
				<div className="px-6 pt-6 pb-0">
					<h2 className="m-0 text-xl font-semibold tracking-tight text-ink">
						{ title }
					</h2>
					{ subtitle && (
						<p className="m-0 mt-1.5 text-sm leading-relaxed text-muted">
							{ subtitle }
						</p>
					) }
				</div>

				{ /* Card body */ }
				<div className="px-6 py-5">
					{ children }
				</div>

				{ /* Card footer */ }
				<Separator />
				<div className="flex items-center justify-between gap-3 px-6 py-4">
					<div>
						{ showBack && (
							<Button
								variant="outline"
								size="sm"
								onClick={ onBack }
								disabled={ backDisabled }
							>
								<ChevronLeft className="h-3.5 w-3.5" />
								{ backLabel || __( 'Back', 'openwp' ) }
							</Button>
						) }
					</div>
					<div className="flex items-center gap-2">
						{ showSkip && (
							<Button
								variant="ghost"
								size="sm"
								onClick={ onSkip }
								disabled={ skipDisabled || continueLoading }
							>
								{ skipLabel || __( 'Skip', 'openwp' ) }
							</Button>
						) }
						{ showContinue && (
							<Button
								size="sm"
								onClick={ onContinue }
								disabled={ continueDisabled || continueLoading }
							>
								{ continueLoading && (
									<Loader2 className="h-3.5 w-3.5 animate-spin" />
								) }
								{ continueLabel || __( 'Continue', 'openwp' ) }
								{ ! continueLoading && (
									<ChevronRight className="h-3.5 w-3.5" />
								) }
							</Button>
						) }
					</div>
				</div>
			</div>
		</div>
	);
}
