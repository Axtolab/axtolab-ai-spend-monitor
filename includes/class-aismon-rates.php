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
 * pricing, and caching/batch discounts are not modeled. Rates are
 * filterable via `aismon_cost_rates`.
 *
 * @since 1.0.0
 */
class Aismon_Rates {

	/**
	 * Returns the rate table.
	 *
	 * Keys are lowercase model id prefixes; the longest matching prefix
	 * wins. Values are arrays: [ input USD per 1M, output USD per 1M ].
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,array{0:float,1:float}>
	 */
	public static function rates() {
		$rates = array(
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

		/**
		 * Filters the model rate table used for cost estimates.
		 *
		 * @since 1.0.0
		 *
		 * @param array $rates Map of lowercase model id prefix => [input, output] USD per 1M tokens.
		 */
		return (array) apply_filters( 'aismon_cost_rates', $rates );
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
		$model = strtolower( trim( (string) $model ) );
		if ( '' === $model ) {
			return null;
		}

		$best     = null;
		$best_len = 0;

		foreach ( self::rates() as $prefix => $pair ) {
			$prefix = strtolower( (string) $prefix );
			if ( 0 === strpos( $model, $prefix ) && strlen( $prefix ) > $best_len ) {
				$best     = $pair;
				$best_len = strlen( $prefix );
			}
		}

		if ( null === $best || ! isset( $best[0], $best[1] ) ) {
			return null;
		}

		return ( ( (int) $prompt_tokens * (float) $best[0] ) + ( (int) $completion_tokens * (float) $best[1] ) ) / 1000000;
	}
}
