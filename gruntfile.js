module.exports = function (grunt) {

    require('jit-grunt')(grunt);

    grunt.initConfig({
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
        obfuscator: {
            options: {
                // global options for the obfuscator
            },
            task1: {
                options: {
                    // options for each sub task
                },
                files: {
                    'assets/js/script.js': [ // the files and their directories will be created in this folder
                        'src/js/script.js',
                    ]
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
        // compress: {
        //     main: {
        //         options: {
        //             archive: 'elementor-live-copy.zip'
        //         },
        //         expand: true,
        //         cwd: 'assets/',
        //         src: [
        //             'assets/**',
        //             'includes/**',
        //             'elementor-live-copy.php',
        //             'readme.txt',
        //         ],
        //         dest: 'public/'
        //     }
        // },
        buildnumber: {
            options: {
                field: 'buildnum',
            },
            files: ['package.json']
        },

    });

    grunt.loadNpmTasks('grunt-rtlcss');
    grunt.loadNpmTasks('grunt-contrib-obfuscator');
    grunt.loadNpmTasks('grunt-terser');
    grunt.loadNpmTasks('grunt-contrib-compress');

    grunt.registerTask('default', ['less', 'rtlcss', 'terser', 'watch']);

};

//grunt obfuscator
