/** @type {import('tailwindcss').Config} */
module.exports = {
	content: [ './src/**/*.{js,jsx,ts,tsx}' ],
	theme: {
		extend: {
			colors: {
				primary: '#0057ff',
				'primary-strong': '#0043c4',
				accent: '#ff7d1f',
				ink: '#10203b',
				muted: '#5f6f89',
				line: '#d9e2f2',
				surface: '#ffffff',
				'surface-2': '#fbfdff',
				bg: '#f3f6fb',
				'bg-soft': '#e8eef8',
				success: '#0f8b57',
				warning: '#946200',
				danger: '#b42318',
			},
			fontFamily: {
				archivo: [ 'Sora', 'Manrope', '"Segoe UI"', 'sans-serif' ],
				mono: [ '"JetBrains Mono"', '"IBM Plex Mono"', 'monospace' ],
			},
			boxShadow: {
				openwp: '0 2px 10px rgba(19, 32, 56, 0.08)',
			},
		},
	},
	corePlugins: {
		// Disable Preflight reset to avoid conflicts with WordPress admin styles.
		preflight: false,
	},
	plugins: [],
};
