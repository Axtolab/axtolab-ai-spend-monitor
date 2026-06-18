<?php
/**
 * Plugin Name: Axtolab AI Spend Monitor
 * Plugin URI: https://axtolab.com/products/
 * Description: AI usage and cost tracking for the WordPress AI Client. See which plugins make AI calls, how many tokens they use, and what it costs — per plugin, per model, per day.
 * Version: 1.0.1
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Author: Axtolab
 * Author URI: https://axtolab.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: axtolab-ai-spend-monitor
 *
 * @package Axtolab_AI_Spend_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AISMON_VERSION', '1.0.1' );
define( 'AISMON_PLUGIN_FILE', __FILE__ );
define( 'AISMON_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AISMON_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Returns the support inbox for plugin-row links.
 *
 * @since 1.0.1
 *
 * @return string
 */
function aismon_support_email() {
	return (string) apply_filters( 'aismon_support_email', 'support@axtolab.com' );
}

/**
 * Builds a mailto URL for support or feature-request links.
 *
 * @since 1.0.1
 *
 * @param string $subject_prefix Subject prefix.
 * @param string $body           Optional body template.
 * @return string
 */
function aismon_email_url( $subject_prefix, $body = '' ) {
	$url = 'mailto:' . rawurlencode( aismon_support_email() )
		. '?subject=' . rawurlencode( $subject_prefix . ': AI Spend Monitor v' . AISMON_VERSION );

	if ( '' !== $body ) {
		$url .= '&body=' . rawurlencode( $body );
	}

	return $url;
}

/**
 * Adds Settings and Support to the Plugins screen action links.
 *
 * @since 1.0.1
 *
 * @param array $links Existing action links.
 * @return array
 */
function aismon_plugin_action_links( $links ) {
	$action_links = array(
		'settings' => sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=aismon' ) ),
			esc_html__( 'Settings', 'axtolab-ai-spend-monitor' )
		),
		'support'  => sprintf(
			'<a href="%s">%s</a>',
			esc_url( aismon_email_url( 'Support' ) ),
			esc_html__( 'Support', 'axtolab-ai-spend-monitor' )
		),
	);

	return array_merge( $action_links, $links );
}

/**
 * Adds support, forum, docs, and feature-request links to the plugin row meta.
 *
 * @since 1.0.1
 *
 * @param array  $links Existing row meta links.
 * @param string $file  Plugin basename for the current row.
 * @return array
 */
function aismon_plugin_row_meta( $links, $file ) {
	if ( $file !== plugin_basename( AISMON_PLUGIN_FILE ) ) {
		return $links;
	}

	$body = "Hi Axtolab,\n\n"
		. "I'd like to suggest a feature for AI Spend Monitor.\n\n"
		. "What I'd like to do:\n\n\n"
		. "Why it matters / what I'm doing today instead:\n\n\n"
		. "Thanks,\n";

	$extra_links = array(
		'email_support'   => sprintf(
			'<a href="%s">%s</a>',
			esc_url( aismon_email_url( 'Support' ) ),
			esc_html__( 'Email support', 'axtolab-ai-spend-monitor' )
		),
		'wporg_forum'     => sprintf(
			'<a href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( 'https://wordpress.org/support/plugin/axtolab-ai-spend-monitor/' ),
			esc_html__( 'WordPress.org forum', 'axtolab-ai-spend-monitor' )
		),
		'docs'            => sprintf(
			'<a href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( 'https://axtolab.com/docs/ai-spend-monitor' ),
			esc_html__( 'Docs', 'axtolab-ai-spend-monitor' )
		),
		'feature_request' => sprintf(
			'<a href="%s">%s</a>',
			esc_url( aismon_email_url( 'Feature request', $body ) ),
			esc_html__( 'Suggest a feature', 'axtolab-ai-spend-monitor' )
		),
	);

	return array_merge( $links, $extra_links );
}

if ( is_admin() ) {
	add_filter( 'plugin_action_links_' . plugin_basename( AISMON_PLUGIN_FILE ), 'aismon_plugin_action_links' );
	add_filter( 'plugin_row_meta', 'aismon_plugin_row_meta', 10, 2 );
}

require_once AISMON_PLUGIN_DIR . 'includes/class-aismon-store.php';
require_once AISMON_PLUGIN_DIR . 'includes/class-aismon-attribution.php';
require_once AISMON_PLUGIN_DIR . 'includes/class-aismon-rates.php';
require_once AISMON_PLUGIN_DIR . 'includes/class-aismon-recorder.php';
require_once AISMON_PLUGIN_DIR . 'includes/class-aismon-dashboard.php';
require_once AISMON_PLUGIN_DIR . 'includes/class-aismon-export.php';
require_once AISMON_PLUGIN_DIR . 'includes/class-aismon-alerts.php';

/**
 * Initializes the plugin components.
 *
 * @since 1.0.0
 *
 * @return void
 */
function aismon_init() {
	Aismon_Store::instance()->maybe_upgrade();
	Aismon_Recorder::instance()->register();
	Aismon_Rates::register();
	Aismon_Alerts::register();

	if ( is_admin() ) {
		Aismon_Dashboard::instance()->register();
		Aismon_Export::register();
	}
}
add_action( 'plugins_loaded', 'aismon_init' );

/**
 * Plugin activation: creates the usage table and schedules pruning.
 *
 * @since 1.0.0
 *
 * @return void
 */
function aismon_activate() {
	Aismon_Store::instance()->install();

	if ( ! wp_next_scheduled( 'aismon_prune_event' ) ) {
		wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'aismon_prune_event' );
	}
}
register_activation_hook( __FILE__, 'aismon_activate' );

/**
 * Plugin deactivation: clears the scheduled pruning event.
 *
 * @since 1.0.0
 *
 * @return void
 */
function aismon_deactivate() {
	wp_clear_scheduled_hook( 'aismon_prune_event' );
}
register_deactivation_hook( __FILE__, 'aismon_deactivate' );

/**
 * Daily cron: prunes usage rows older than the retention window.
 *
 * @since 1.0.0
 *
 * @return void
 */
function aismon_prune() {
	Aismon_Store::instance()->prune();
}
add_action( 'aismon_prune_event', 'aismon_prune' );

/**
 * Shows an admin notice when the WordPress AI Client is not available.
 *
 * The plugin activates on older WordPress versions but cannot record
 * anything until the site runs WordPress 7.0 or newer.
 *
 * @since 1.0.0
 *
 * @return void
 */
function aismon_requirements_notice() {
	if ( function_exists( 'wp_ai_client_prompt' ) ) {
		return;
	}

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'AI Spend Monitor requires the WordPress AI Client, which ships with WordPress 7.0. The plugin is active but cannot record AI usage on this WordPress version.', 'axtolab-ai-spend-monitor' )
	);
}
add_action( 'admin_notices', 'aismon_requirements_notice' );
