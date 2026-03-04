export default function JsonBox( { value } ) {
	return (
		<pre className="m-0 break-words whitespace-pre-wrap rounded-lg border-[0.5px] border-solid border-line bg-surface-2/50 p-[11px] font-mono text-xs leading-[1.45] text-muted">
			{ JSON.stringify( value || {}, null, 2 ) }
		</pre>
	);
}
