<?php
/**
 * Spend notification for AI Spend Monitor.
 *
 * @package Axtolab_AI_Spend_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends a single monthly email when sitewide estimated AI spend passes a
 * user-configured dollar amount.
 *
 * This is a notification only — nothing is blocked.
 *
 * @since 1.0.0
 */
class Aismon_Alerts {

	/**
	 * Option name for notification settings.
	 */
	const OPTION = 'aismon_alert';

	/**
	 * Registers hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'aismon_usage_recorded', array( __CLASS__, 'maybe_notify' ) );
		add_action( 'admin_post_aismon_save_alert', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * Returns notification settings merged with defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return array{monthly_usd: float, email: string}
	 */
	public static function settings() {
		$saved = get_option( self::OPTION, array() );

		return wp_parse_args(
			is_array( $saved ) ? $saved : array(),
			array(
				'monthly_usd' => 0.0,
				'email'       => get_option( 'admin_email' ),
			)
		);
	}

	/**
	 * Saves notification settings from the dashboard form.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'axtolab-ai-spend-monitor' ) );
		}

		check_admin_referer( 'aismon_save_alert' );

		update_option(
			self::OPTION,
			array(
				'monthly_usd' => isset( $_POST['aismon_alert_usd'] ) ? max( 0, (float) $_POST['aismon_alert_usd'] ) : 0,
				'email'       => isset( $_POST['aismon_alert_email'] ) ? sanitize_email( wp_unslash( $_POST['aismon_alert_email'] ) ) : get_option( 'admin_email' ),
			),
			false
		);

		wp_safe_redirect( admin_url( 'tools.php?page=aismon&aismon_saved=1' ) );
		exit;
	}

	/**
	 * Checks month-to-date spend after a recorded call and notifies once
	 * per calendar month when the configured amount is passed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function maybe_notify() {
		$settings = self::settings();
		$limit    = (float) $settings['monthly_usd'];

		if ( $limit <= 0 ) {
			return;
		}

		$transient = 'aismon_alert_sent_' . gmdate( 'Y-m' );
		if ( get_transient( $transient ) ) {
			return;
		}

		$totals = Aismon_Store::instance()->totals( gmdate( 'Y-m-01 00:00:00' ) );
		$spend  = null === $totals['est_cost_usd'] ? 0.0 : (float) $totals['est_cost_usd'];

		if ( $spend < $limit ) {
			return;
		}

		// Expire at the start of next month so the notification resets monthly.
		$expires = strtotime( gmdate( 'Y-m-01 00:00:00', strtotime( 'first day of next month' ) ) ) - time();
		set_transient( $transient, 1, max( HOUR_IN_SECONDS, $expires ) );

		$to = is_email( $settings['email'] ) ? $settings['email'] : get_option( 'admin_email' );

		wp_mail(
			$to,
			sprintf(
				/* translators: %s: site name. */
				__( '[%s] AI spend notification', 'axtolab-ai-spend-monitor' ),
				wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
			),
			sprintf(
				/* translators: 1: estimated spend, 2: configured amount, 3: dashboard URL. */
				__( 'Estimated AI spend on this site has passed $%1$s this month (your notification amount is $%2$s). This is a notification only — nothing has been blocked. Review usage by plugin: %3$s', 'axtolab-ai-spend-monitor' ),
				number_format_i18n( $spend, 2 ),
				number_format_i18n( $limit, 2 ),
				admin_url( 'tools.php?page=aismon' )
			)
		);
	}
}
