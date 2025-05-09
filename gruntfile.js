module.exports = function (grunt) {

	require( 'jit-grunt' )( grunt );

	grunt.initConfig(
		{
			less: {
				development: {
					options: {
						compress: true,
						yuicompress: true,
						optimization: 2
					},
					files: {
						// target.css file: source.less file
						"assets/css/style.css": "src/less/style.less"
					}
				},
			},
			rtlcss: {
				siteRTL: {
					// task options
					options: {
						// rtlcss options
						opts: {
							clean: true
						},
						// rtlcss plugins
						plugins: [],
						// save unmodified files
						saveUnmodified: true
					},
					expand: true,
					cwd: 'assets/css/',
					dest: 'assets/css/',
					src: ['**/*.css', '!**/*.rtl.css'],
					ext: '.rtl.css'
				},
			},
			terser: {
				options: {
					mangle: true
				},
				my_target: {
					files: {
						'assets/js/script.js': ['src/js/script.js'],
					}
				}
			},
			watch: {
				styles: {
					files: ['src/less/*.less'], // which files to watch
					tasks: ['less', 'rtlcss'],
					options: {
						nospawn: true
					}
				},
				scripts: {
					files: ['src/js/**/*.js'],
					tasks: ['concat', 'terser'],
					options: {
						spawn: false,
					},
				}
			},
			compress: {
				main: {
					options: {
						archive: 'live-copy.zip'
					},
					expand: true,
					cwd: './',  // Changed from 'assets/' to root directory
					src: [
					'assets/**',
					'includes/**',
					'live-copy.php',
					'readme.txt',
					],
					dest: 'live-copy/'
				}
			},
			buildnumber: {
				options: {
					field: 'buildnum',
				},
				files: ['package.json']
			},

		}
	);

	grunt.loadNpmTasks( 'grunt-rtlcss' );
	grunt.loadNpmTasks( 'grunt-terser' );
	grunt.loadNpmTasks( 'grunt-contrib-compress' );

	grunt.registerTask( 'default', ['less', 'rtlcss', 'terser', 'watch'] );

};
