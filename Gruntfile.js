module.exports = function ( grunt ) {
	// Project configuration.
	grunt.initConfig( {
		pkg: grunt.file.readJSON( 'package.json' ),

		copy: {
			main: {
				options: { mode: true },
				src: [
					'**',
					'!.git/**',
					'!.gitignore',
					'!.gitattributes',
					'!*.sh',
					'!*.zip',
					'!eslintrc.json',
					'!README.md',
					'!Gruntfile.js',
					'!package.json',
					'!package-lock.json',
					'!composer.json',
					'!composer.lock',
					'!phpcs.xml',
					'!phpcs.xml.dist',
					'!phpunit.xml.dist',
					'!node_modules/**',
					'!vendor/**',
					'!tests/**',
					'!scripts/**',
					'!config/**',
					'!bin/**',
					'!postcss.config.js',
					'!webpack.config.js',
					'!tailwind.config.js',
					'!phpstan.neon',
					'!phpstan-baseline.neon',
					'!jsconfig.json',
					'!artifacts/**',
					'!artifact/**',
					'!docs/**',
					'!answers.txt',
				],
				dest: 'openwp/',
			},
		},
		compress: {
			main: {
				options: {
					archive: 'openwp-<%= pkg.version %>.zip',
					mode: 'zip',
				},
				files: [ { src: [ './openwp/**' ] } ],
			},
		},
		clean: {
			main: [ 'openwp' ],
			zip: [ '*.zip' ],
		},

		rtlcss: {
			options: {
				// rtlcss options
				config: {
					preserveComments: true,
					greedy: true,
				},
				// Generate source maps if needed
				map: false,
			},
			dist: {
				files: [
					{
						expand: true,
						cwd: 'build/', // Adjust the path if your CSS files are located elsewhere
						src: [ '*.css', '!*-rtl.css' ],
						dest: 'build/', // Destination directory for RTL CSS
						ext: '-rtl.css',
					},
				],
			},
		},

		// wp_readme_to_markdown Configuration.
		wp_readme_to_markdown: {
			your_target: {
				files: {
					'README.md': 'readme.txt',
				},
			},
		},

		bumpup: {
			options: {
				updateProps: {
					pkg: 'package.json',
				},
			},
			file: 'package.json',
		},

		replace: {
			plugin_main: {
				src: [ 'openwp.php' ],
				overwrite: true,
				replacements: [
					{
						from: /Version: \bv?(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)(?:-[\da-z-A-Z-]+(?:\.[\da-z-A-Z-]+)*)?(?:\+[\da-z-A-Z-]+(?:\.[\da-z-A-Z-]+)*)?\b/g,
						to: 'Version: <%= pkg.version %>',
					},
				],
			},

			stable_tag: {
				src: [ 'readme.txt' ],
				overwrite: true,
				replacements: [
					{
						from: /Stable tag:\ .*/g,
						to: 'Stable tag: <%= pkg.version %>',
					},
				],
			},

			plugin_const: {
				src: [ 'openwp.php' ],
				overwrite: true,
				replacements: [
					{
						from: /OPENWP_VERSION', '.*?'/g,
						to: "OPENWP_VERSION', '<%= pkg.version %>'",
					},
				],
			},

			plugin_function_comment: {
				src: [
					'*.php',
					'**/*.php',
					'!node_modules/**',
					'!tests/**',
					'!bin/**',
					'!build/**',
					'!vendor/**',
				],
				overwrite: true,
				replacements: [
					{
						from: 'x.x.x',
						to: '<%=pkg.version %>',
					},
				],
			},
		},
	} );

	/* Load Tasks */
	grunt.loadNpmTasks( 'grunt-contrib-copy' );
	grunt.loadNpmTasks( 'grunt-contrib-compress' );
	grunt.loadNpmTasks( 'grunt-contrib-clean' );
	grunt.loadNpmTasks( 'grunt-rtlcss' );
	grunt.loadNpmTasks( 'grunt-wp-readme-to-markdown' );
	grunt.loadNpmTasks( 'grunt-bumpup' );
	grunt.loadNpmTasks( 'grunt-text-replace' );

	/* Register Tasks */
	grunt.registerTask( 'release', [
		'clean:zip',
		'copy',
		'compress',
		'clean:main',
	] );

	grunt.registerTask( 'release-no-clean', [ 'copy', 'compress' ] );
	grunt.registerTask( 'rtl', [ 'rtlcss' ] );
	grunt.registerTask( 'readme', [ 'wp_readme_to_markdown' ] );

	grunt.registerTask( 'version-bump', function ( ver ) {
		let newVersion = grunt.option( 'ver' );

		if ( newVersion ) {
			newVersion = newVersion ? newVersion : 'patch';

			grunt.task.run( 'bumpup:' + newVersion );
			grunt.task.run( 'replace' );
		}
	} );
};
