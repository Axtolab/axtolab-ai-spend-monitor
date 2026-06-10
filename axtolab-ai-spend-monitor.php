<?php
/**
 * Plugin Name: AI Spend Monitor by Axtolab
 * Plugin URI: https://axtolab.com/ai-spend-monitor/
 * Description: AI usage and cost tracking for the WordPress AI Client. See which plugins make AI calls, how many tokens they use, and what it costs — per plugin, per model, per day.
 * Version: 1.0.0
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

define( 'AISMON_VERSION', '1.0.0' );
define( 'AISMON_PLUGIN_FILE', __FILE__ );
define( 'AISMON_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AISMON_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once AISMON_PLUGIN_DIR . 'includes/class-aismon-store.php';
require_once AISMON_PLUGIN_DIR . 'includes/class-aismon-attribution.php';
require_once AISMON_PLUGIN_DIR . 'includes/class-aismon-rates.php';
require_once AISMON_PLUGIN_DIR . 'includes/class-aismon-recorder.php';
require_once AISMON_PLUGIN_DIR . 'includes/class-aismon-dashboard.php';

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

	if ( is_admin() ) {
		Aismon_Dashboard::instance()->register();
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
