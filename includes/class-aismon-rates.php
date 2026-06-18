<?php
/**
 * Cost estimation for AI Spend Monitor.
 *
 * @package Axtolab_AI_Spend_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Estimates USD cost for a model call from token counts.
 *
 * Rates are bundled defaults (USD per 1 million tokens, standard tier,
 * last reviewed June 2026) and are estimates only — providers change
 * pricing, and caching/batch discounts are not modeled.
 *
 * Site owners can override any default rate from the dashboard's "Cost rates"
 * tab; overrides are stored in the {@see self::OPTION} option and merged over
 * the defaults via the `aismon_cost_rates` filter. Developers can layer further
 * changes on the same filter.
 *
 * @since 1.0.0
 */
class Aismon_Rates {

	/**
	 * Option name for site-owner rate overrides.
	 *
	 * Stored as a map of lowercase model prefix => [ input, output ] USD per 1M.
	 *
	 * @since 1.0.1
	 */
	const OPTION = 'aismon_rate_overrides';

	/**
	 * Registers the override filter and the dashboard save handler.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function register() {
		// Priority 5 so site-owner overrides apply before (and can still be
		// superseded by) developer callbacks on the default priority of 10.
		add_filter( 'aismon_cost_rates', array( __CLASS__, 'apply_overrides' ), 5 );
		add_action( 'admin_post_aismon_save_rates', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * Returns the bundled default rate table.
	 *
	 * Keys are lowercase model id prefixes; the longest matching prefix
	 * wins. Values are arrays: [ input USD per 1M, output USD per 1M ].
	 *
	 * @since 1.0.1
	 *
	 * @return array<string,array{0:float,1:float}>
	 */
	public static function defaults() {
		return array(
			// OpenAI.
			'gpt-5.5'          => array( 5.00, 30.00 ),
			'gpt-5.4'          => array( 2.50, 15.00 ),
			'gpt-5.2-codex'    => array( 1.75, 14.00 ),
			'gpt-5-mini'       => array( 0.25, 2.00 ),
			'gpt-5-nano'       => array( 0.05, 0.40 ),
			'gpt-5'            => array( 1.25, 10.00 ),
			'gpt-4.1-nano'     => array( 0.10, 0.40 ),
			'gpt-4.1-mini'     => array( 0.40, 1.60 ),
			'gpt-4.1'          => array( 2.00, 8.00 ),
			'gpt-4o-mini'      => array( 0.15, 0.60 ),
			'gpt-4o'           => array( 2.50, 10.00 ),
			// Anthropic.
			'claude-opus-4-8'  => array( 5.00, 25.00 ),
			'claude-opus-4'    => array( 15.00, 75.00 ),
			'claude-sonnet-4'  => array( 3.00, 15.00 ),
			'claude-haiku-4'   => array( 1.00, 5.00 ),
			'claude-3-5-haiku' => array( 0.80, 4.00 ),
			'claude'           => array( 3.00, 15.00 ),
			// Google.
			'gemini-3.5-flash' => array( 1.50, 9.00 ),
			'gemini-3.1-pro'   => array( 2.00, 12.00 ),
			'gemini-2.5-pro'   => array( 1.25, 10.00 ),
			'gemini-2.5-flash' => array( 0.30, 2.50 ),
			'gemini-2.0-flash' => array( 0.10, 0.40 ),
		);
	}

	/**
	 * Returns the effective rate table (defaults + overrides + filters).
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,array{0:float,1:float}>
	 */
	public static function rates() {
		/**
		 * Filters the model rate table used for cost estimates.
		 *
		 * @since 1.0.0
		 *
		 * @param array $rates Map of lowercase model id prefix => [input, output] USD per 1M tokens.
		 */
		return (array) apply_filters( 'aismon_cost_rates', self::defaults() );
	}

	/**
	 * Returns the stored site-owner overrides, validated against defaults.
	 *
	 * Only keys present in {@see self::defaults()} are kept, so a stale option
	 * cannot inject pricing for models the plugin no longer knows about.
	 *
	 * @since 1.0.1
	 *
	 * @return array<string,array{0:float,1:float}>
	 */
	public static function overrides() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			return array();
		}

		$defaults = self::defaults();
		$clean    = array();

		foreach ( $saved as $prefix => $pair ) {
			if ( ! isset( $defaults[ $prefix ] ) || ! is_array( $pair ) || ! isset( $pair[0], $pair[1] ) ) {
				continue;
			}
			$clean[ $prefix ] = array( max( 0, (float) $pair[0] ), max( 0, (float) $pair[1] ) );
		}

		return $clean;
	}

	/**
	 * Merges site-owner overrides over the default rates.
	 *
	 * Hooked to `aismon_cost_rates`.
	 *
	 * @since 1.0.1
	 *
	 * @param array $rates Incoming rate table.
	 * @return array Rate table with overrides applied.
	 */
	public static function apply_overrides( $rates ) {
		$rates = is_array( $rates ) ? $rates : array();
		foreach ( self::overrides() as $prefix => $pair ) {
			$rates[ $prefix ] = $pair;
		}
		return $rates;
	}

	/**
	 * Resolves the rate pair for a model id using longest-prefix matching.
	 *
	 * @since 1.0.1
	 *
	 * @param string $model Model id.
	 * @return array{0:float,1:float}|null Rate pair, or null when unknown.
	 */
	public static function rate_for( $model ) {
		$model = strtolower( trim( (string) $model ) );
		if ( '' === $model ) {
			return null;
		}

		$best     = null;
		$best_len = 0;

		foreach ( self::rates() as $prefix => $pair ) {
			$prefix = strtolower( (string) $prefix );
			if ( 0 === strpos( $model, $prefix ) && strlen( $prefix ) > $best_len && isset( $pair[0], $pair[1] ) ) {
				$best     = array( (float) $pair[0], (float) $pair[1] );
				$best_len = strlen( $prefix );
			}
		}

		return $best;
	}

	/**
	 * Estimates the USD cost of a call.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model             Model id.
	 * @param int    $prompt_tokens     Prompt token count.
	 * @param int    $completion_tokens Completion token count.
	 * @return float|null Estimated cost, or null when the model is unknown.
	 */
	public static function estimate( $model, $prompt_tokens, $completion_tokens ) {
		$pair = self::rate_for( $model );
		if ( null === $pair ) {
			return null;
		}

		return ( ( (int) $prompt_tokens * $pair[0] ) + ( (int) $completion_tokens * $pair[1] ) ) / 1000000;
	}

	/**
	 * Saves rate overrides from the dashboard "Cost rates" form.
	 *
	 * Only values that differ from the bundled default are stored, so future
	 * default-price updates keep flowing through for untouched models. After
	 * saving, previously recorded calls are re-estimated so the dashboard
	 * reflects the corrected rates.
	 *
	 * @since 1.0.1
	 *
	 * @return void
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'axtolab-ai-spend-monitor' ) );
		}

		check_admin_referer( 'aismon_save_rates' );

		$defaults = self::defaults();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Each numeric field is read individually, cast to float, and clamped below.
		$submitted = isset( $_POST['aismon_rate'] ) && is_array( $_POST['aismon_rate'] ) ? (array) $_POST['aismon_rate'] : array();
		$overrides = array();

		foreach ( $defaults as $prefix => $default_pair ) {
			if ( ! isset( $submitted[ $prefix ] ) || ! is_array( $submitted[ $prefix ] ) ) {
				continue;
			}

			$in_raw  = isset( $submitted[ $prefix ]['in'] ) ? trim( (string) $submitted[ $prefix ]['in'] ) : '';
			$out_raw = isset( $submitted[ $prefix ]['out'] ) ? trim( (string) $submitted[ $prefix ]['out'] ) : '';

			// Empty fields mean "use the default".
			$in  = '' === $in_raw ? (float) $default_pair[0] : max( 0, (float) $in_raw );
			$out = '' === $out_raw ? (float) $default_pair[1] : max( 0, (float) $out_raw );

			// Only persist a real change; otherwise let the default stay live.
			if ( abs( $in - (float) $default_pair[0] ) > 0.0000001 || abs( $out - (float) $default_pair[1] ) > 0.0000001 ) {
				$overrides[ $prefix ] = array( $in, $out );
			}
		}

		if ( empty( $overrides ) ) {
			delete_option( self::OPTION );
		} else {
			update_option( self::OPTION, $overrides, false );
		}

		// Re-estimate stored costs so the dashboard reflects the new rates.
		Aismon_Store::instance()->recompute_costs();

		wp_safe_redirect( admin_url( 'admin.php?page=aismon&tab=rates&aismon_rates_saved=1' ) );
		exit;
	}
}
