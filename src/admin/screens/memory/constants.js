import { __, sprintf } from '@wordpress/i18n';

export const MEMORY_TYPE_OPTIONS = [
	{ value: 'preference', label: __( 'Preference', 'openwp' ) },
	{ value: 'constraint', label: __( 'Constraint', 'openwp' ) },
	{ value: 'fact', label: __( 'Fact', 'openwp' ) },
	{ value: 'workflow', label: __( 'Workflow', 'openwp' ) },
];

export const strings = {
	title: () => __( 'Memory', 'openwp' ),
	subtitle: () =>
		__(
			'Store explicit memory entries for the agent. Entries are only created when you ask to remember something.',
			'openwp'
		),
	type: () => __( 'Type', 'openwp' ),
	key: () => __( 'Key', 'openwp' ),
	value: () => __( 'Memory Text', 'openwp' ),
	tags: () => __( 'Tags', 'openwp' ),
	tagsHint: () => __( 'Comma-separated tags', 'openwp' ),
	search: () => __( 'Search memory…', 'openwp' ),
	allTypes: () => __( 'All types', 'openwp' ),
	save: () => __( 'Save Memory', 'openwp' ),
	saving: () => __( 'Saving…', 'openwp' ),
	delete: () => __( 'Delete', 'openwp' ),
	memoryDisabled: () =>
		__(
			'Memory is currently disabled in Settings. Enable "Agent Memory" to allow remember/forget actions.',
			'openwp'
		),
	requiredType: () => __( 'Type is required.', 'openwp' ),
	requiredKey: () => __( 'Key is required.', 'openwp' ),
	requiredValue: () => __( 'Memory text is required.', 'openwp' ),
	noMemory: () => __( 'No memory entries yet', 'openwp' ),
	noMemoryMessage: () =>
		__( 'Save your first explicit memory entry to guide future agent decisions.', 'openwp' ),
	noFilteredMemory: () => __( 'No memory entries match your filters.', 'openwp' ),
	keyPlaceholder: () => __( 'draft-post-default', 'openwp' ),
	valuePlaceholder: () =>
		__(
			'Always keep post status as draft unless I explicitly say publish.',
			'openwp'
		),
	tagsPlaceholder: () => __( 'content, safety', 'openwp' ),
	created: () => __( 'Created', 'openwp' ),
	updated: () => __( 'Updated', 'openwp' ),
	actions: () => __( 'Actions', 'openwp' ),
	confirmDelete: ( key ) =>
		sprintf(
			/* translators: %s: memory key */
			__( 'Delete memory key "%s"?', 'openwp' ),
			key
		),
};
