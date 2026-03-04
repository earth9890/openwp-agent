/**
 * Reusable badge selector - renders options as clickable pill badges.
 *
 * @param {Object}   props
 * @param {string}   [props.label]    Optional uppercase heading above the badges.
 * @param {Array}    props.options    Array of { label, value } objects.
 * @param {string}   props.value      Currently selected value.
 * @param {Function} props.onChange   Called with the new value when a badge is clicked.
 * @param {boolean}  [props.disabled] Disable all badges.
 */
export default function BadgeSelector({ label, options, value, onChange, disabled }) {
	return (
		<div className="grid gap-1.5">
			{label && (
				<span className="text-[10px] font-bold uppercase tracking-wider text-[#7083a0]">
					{label}
				</span>
			)}
			<div className="flex flex-wrap gap-1">
				{options.map((opt) => (
					<button
						key={opt.value}
						type="button"
						onClick={() => onChange(opt.value)}
						disabled={disabled}
						className={`!shadow-none outline-none focus:outline-none !border !border-solid rounded-full px-2.5 py-[4px] text-[11px] font-semibold transition-all duration-150 cursor-pointer ${value === opt.value
								? '!border-[#0057ff] bg-[#0057ff] text-white'
								: '!border-[#d9e2f2] bg-white text-[#3e5478] hover:!border-[#93b4f5] hover:text-[#0057ff] hover:bg-[#f0f4ff]'
							} disabled:opacity-50 disabled:cursor-not-allowed`}
					>
						{opt.badge || opt.label}
					</button>
				))}
			</div>
		</div>
	);
}
