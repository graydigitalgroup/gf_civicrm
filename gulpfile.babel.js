import gulp from 'gulp'
import gulpLoadPlugins from 'gulp-load-plugins'

const $ = gulpLoadPlugins({ pattern: ['*'] })
const argv = $.yargs.argv
const phpcs = '../../../vendor/bin/phpcs'
const phpfix = '../../../vendor/bin/phpcbf'

function reload(done) {
	$.browserSync.reload()

	done()
}

// Paths for source and distribution files
function directory_list() {
	const directory_list = {
		plugin_components: 'plugin_components',
		build_dir: '../gf-civicrm-build',
		assets: 'assets',
	}

	if (argv.build) {
		directory_list.assets = '../gf-civicrm-build/assets'
	}

	return directory_list
}

const dir = directory_list()

function prettier_js(done) {
	if (argv.skip_lint) {
		return done()
	}

	return gulp
		.src('package.json', { read: false })
		.pipe($.shell('./node_modules/prettier/bin-prettier.js --write --loglevel warn "./**/*.js"'))
}

function prettier_scss(done) {
	if (argv.skip_lint) {
		return done()
	}

	return gulp
		.src('package.json', { read: false })
		.pipe($.shell('./node_modules/prettier/bin-prettier.js --write --loglevel warn "./**/*.scss"'))
}

function phpcs_task(done) {
	if (argv.skip_lint) {
		return done()
	}

	return gulp.src('package.json', { read: false }).pipe(
		$.shell(`${phpcs} ${argv.file ? argv.file : ''}`, {
			verbose: true,
			ignoreErrors: true,
		})
	)
}

gulp.task('phpcs', gulp.series(phpcs_task))

gulp.task(
	'phpfix',
	$.shell.task(`${phpfix} ${argv.file ? argv.file : ''}`, {
		verbose: true,
		ignoreErrors: true,
	})
)

gulp.task(
	'webpack',
	$.shell.task(`'./node_modules/webpack-cli/bin/cli.js' --env.output ${dir.assets}/js`, {
		verbose: true,
		ignoreErrors: true,
	})
)

gulp.task('scripts', gulp.series(prettier_js, 'webpack'))

function jquery_task() {
	// Sets up modern jQuery for WordPress to use in functions.php
	return gulp
		.src('./node_modules/jquery/dist/jquery.js')
		.pipe($.plumber())
		.pipe($.sourcemaps.init())
		.pipe($.uglify())
		.pipe($.rename({ suffix: '.min' }))
		.pipe($.sourcemaps.write('.'))
		.pipe(gulp.dest(`${dir.assets}/js/vendors`))
}

function style_lint(done) {
	if (argv.skip_lint) {
		return done()
	}

	const stylelint_src = './node_modules/stylelint/bin/stylelint.js'

	return gulp.src('package.json', { read: false }).pipe(
		$.shell(`${stylelint_src} '${dir.plugin_components}/sass/**/*.scss' --fix`, {
			verbose: true,
			ignoreErrors: true,
		})
	)
}

gulp.task('lint:sass', gulp.series(prettier_scss, style_lint))

function styles_task() {
	return gulp
		.src(`${dir.plugin_components}/sass/**/*.scss`)
		.pipe($.plumber())
		.pipe($.sourcemaps.init())
		.pipe(
			$.sass
				.sync({
					outputStyle: 'compact',
					precision: 10,
					includePaths: ['node_modules'],
				})
				.on('error', $.sass.logError)
		)
		.pipe(
			$.postcss([
				$.autoprefixer({
					grid: true,
				}),
				$.cssnano(),
			])
		)
		.pipe($.rename({ suffix: '.min' }))
		.pipe($.sourcemaps.write('.'))
		.pipe(
			$.size({
				title: 'Styles: ',
				gzip: true,
				pretty: true,
			})
		)
		.pipe(gulp.dest(`${dir.assets}/css`))
		.pipe($.browserSync.stream({ match: '**/*.css' }))
}

gulp.task('styles', gulp.series('lint:sass', styles_task))

function images_task() {
	// TODO: Improve with SVG/PNG sprite generator
	// TODO: Added Favicon/App Icon generator
	return gulp
		.src(`${dir.plugin_components}/images/**/*`)
		.pipe($.plumber())
		.pipe($.newer(`${dir.assets}/images`))
		.pipe(
			$.imagemin([
				// https://www.npmjs.com/package/imagemin-pngquant
				$.imageminPngquant({
					speed: 4,
					strip: true,
					quality: [0.6, 0.8],
					dithering: false,
				}),
				// https://www.npmjs.com/package/imagemin-mozjpeg
				$.imageminMozjpeg({
					quality: 60,
					progressive: true,
				}),
				// https://www.npmjs.com/package/imagemin-gifsicle
				$.imageminGifsicle({
					interlaced: true,
					optimizationLevel: 3,
					colors: 50,
				}),
				// https://www.npmjs.com/package/imagemin-gifsicle
				$.imageminSvgo({
					cleanupIDs: false,
				}),
			]).on('error', error => {
				console.log(error) // eslint-disable-line no-console
			})
		)
		.pipe(gulp.dest(`${dir.assets}/images`))
}

gulp.task('images', gulp.series(images_task))

function serve_task() {
	/**
	 * @link https://www.joezimjs.com/javascript/complete-guide-upgrading-gulp-4/
	 */
	const assets = dir.plugin_components

	// pass '--https' as an argument into the gulp serve task
	$.browserSync('**/*.php', {
		proxy: {
			target: `${argv.https ? 'https' : 'http'}://localhost`,
		},
		open: false,
		notify: false,
	})

	gulp.watch('**/*.php').on('change', function (path) {
		$.shell.task(`${phpfix} ${path} && ${phpcs} ${path}`, {
			verbose: true,
			ignoreErrors: true,
		})()
	})

	gulp.watch(`${assets}/sass/**/*`, gulp.series('styles'))
	gulp.watch(`${assets}/js/**/*`, gulp.series('scripts', reload))
	gulp.watch(`${assets}/images/**/*`, gulp.series(images_task, reload))
}

gulp.task('serve', gulp.series(gulp.parallel('styles', 'scripts', jquery_task, images_task), serve_task))

function watch_task() {
	gulp.watch('**/*.php').on('change', function (path) {
		$.shell.task(`${phpfix} ${path} && ${phpcs} ${path}`, {
			verbose: true,
			ignoreErrors: true,
		})()
	})
	gulp.watch(`${dir.plugin_components}/sass/**/*`, gulp.series('styles'))
	gulp.watch(`${dir.plugin_components}/js/**/*`, gulp.series('scripts'))
	gulp.watch(`${dir.plugin_components}/images/**/*`, gulp.series(images_task))
}

gulp.task('watch', gulp.series(gulp.parallel('styles', 'scripts', jquery_task, images_task), watch_task))

function watch_code_task() {
	gulp.watch('**/*.php').on('change', function (path) {
		$.shell.task(`${phpfix} ${path} && ${phpcs} ${path}`, {
			verbose: true,
			ignoreErrors: true,
		})()
	})
	gulp.watch(`${dir.plugin_components}/sass/**/*`, gulp.series('styles'))
	gulp.watch(`${dir.plugin_components}/js/**/*`, gulp.series('scripts'))
}

gulp.task('watch:code', gulp.series(gulp.parallel('styles', 'scripts'), watch_code_task))

gulp.task('clean', $.del.bind(null, [dir.build_dir, 'assets'], { force: true }))

function copy_task(done) {
	if (!argv.build) {
		return done()
	}

	return gulp
		.src([
			'./**/*',
			'!./node_modules{,/**}',
			'!./plugin_components{,/**}',
			'!./codesniffer.ruleset.xml',
			'!./phpcs.xml',
			'!./gulpfile.babel.js',
			'!./webpack.config.js',
			'!./webpack.config.babel.js',
			'!./package.json',
			'!./yarn.lock',
			'!./package-lock.json',
			'!./.babelrc',
			'!./.sass-lint.yml',
			'!./.eslintrc.json',
			'!./.prettierignore',
			'!./.prettierrc',
			'!./*.todo',
			'!./*.log',
			'!./*.logs',
			'!./*.code-workspace',
		])
		.pipe(gulp.dest(dir.build_dir))
}

gulp.task('build', gulp.series('phpcs', gulp.parallel('styles', 'scripts', jquery_task, images_task, copy_task)))

gulp.task('build:code', gulp.series(gulp.parallel('styles', 'scripts')))

gulp.task('default', gulp.series('clean', 'build'))
