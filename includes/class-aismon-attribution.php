<?php
/**
 * Caller attribution for AI Spend Monitor.
 *
 * @package Axtolab_AI_Spend_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves which plugin or theme initiated an AI Client call.
 *
 * The WordPress AI Client does not pass caller identity to its hooks, so
 * this class walks the debug backtrace and maps the first file outside
 * WordPress core (and outside this plugin) to a plugin or theme.
 *
 * @since 1.0.0
 */
class Aismon_Attribution {

	/**
	 * Cached plugin directory => display name map.
	 *
	 * @var array<string,string>|null
	 */
	private static $plugin_names = null;

	/**
	 * Resolves the calling source from the current backtrace.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $skip_dirs Additional directories whose frames should be
	 *                            ignored (e.g. an enforcement plugin calling this
	 *                            from inside an AI Client filter).
	 * @return array {
	 *     @type string $type Source type: plugin|theme|core|unknown.
	 *     @type string $slug Source slug (plugin directory or theme stylesheet).
	 *     @type string $name Human-readable name.
	 * }
	 */
	public static function resolve( $skip_dirs = array() ) {
		$plugin_dir   = wp_normalize_path( WP_PLUGIN_DIR );
		$mu_dir       = wp_normalize_path( WPMU_PLUGIN_DIR );
		$themes_dir   = wp_normalize_path( get_theme_root() );
		$self_dir     = wp_normalize_path( AISMON_PLUGIN_DIR );
		$includes_dir = wp_normalize_path( ABSPATH . WPINC );

		$skip_dirs = array_map( 'wp_normalize_path', (array) $skip_dirs );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Used for caller attribution only; arguments are excluded.
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 40 );

		foreach ( $trace as $frame ) {
			if ( empty( $frame['file'] ) ) {
				continue;
			}

			$file = wp_normalize_path( $frame['file'] );

			// Skip frames inside this plugin and inside wp-includes (the AI Client itself).
			if ( 0 === strpos( $file, $self_dir ) || 0 === strpos( $file, $includes_dir ) ) {
				continue;
			}

			// Skip caller-supplied directories (e.g. enforcement add-ons).
			$skipped = false;
			foreach ( $skip_dirs as $skip_dir ) {
				if ( '' !== $skip_dir && 0 === strpos( $file, $skip_dir ) ) {
					$skipped = true;
					break;
				}
			}
			if ( $skipped ) {
				continue;
			}

			if ( 0 === strpos( $file, $plugin_dir ) || 0 === strpos( $file, $mu_dir ) ) {
				$base     = ( 0 === strpos( $file, $plugin_dir ) ) ? $plugin_dir : $mu_dir;
				$relative = ltrim( substr( $file, strlen( $base ) ), '/' );
				$parts    = explode( '/', $relative );
				$slug     = sanitize_key( $parts[0] );

				// Single-file plugins live directly in the plugins dir.
				if ( false !== strpos( $parts[0], '.php' ) ) {
					$slug = sanitize_key( basename( $parts[0], '.php' ) );
				}

				return array(
					'type' => 'plugin',
					'slug' => $slug,
					'name' => self::plugin_name( $slug ),
				);
			}

			if ( 0 === strpos( $file, $themes_dir ) ) {
				$relative = ltrim( substr( $file, strlen( $themes_dir ) ), '/' );
				$parts    = explode( '/', $relative );
				$slug     = sanitize_key( $parts[0] );
				$theme    = wp_get_theme( $parts[0] );

				return array(
					'type' => 'theme',
					'slug' => $slug,
					'name' => $theme->exists() ? $theme->get( 'Name' ) : $slug,
				);
			}

			// A file outside plugins/themes/wp-includes: core admin code or custom code.
			return array(
				'type' => 'core',
				'slug' => 'wordpress-core',
				'name' => __( 'WordPress core / direct code', 'axtolab-ai-spend-monitor' ),
			);
		}

		return array(
			'type' => 'unknown',
			'slug' => '',
			'name' => __( 'Unknown', 'axtolab-ai-spend-monitor' ),
		);
	}

	/**
	 * Returns the display name for a plugin directory slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Plugin directory slug.
	 * @return string
	 */
	private static function plugin_name( $slug ) {
		if ( null === self::$plugin_names ) {
			self::$plugin_names = array();

			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			foreach ( get_plugins() as $file => $headers ) {
				$dir = sanitize_key( dirname( $file ) );
				if ( '.' === dirname( $file ) ) {
					$dir = sanitize_key( basename( $file, '.php' ) );
				}
				if ( '' !== $dir && empty( self::$plugin_names[ $dir ] ) ) {
					self::$plugin_names[ $dir ] = $headers['Name'];
				}
			}
		}

		return isset( self::$plugin_names[ $slug ] ) ? self::$plugin_names[ $slug ] : $slug;
	}
}
