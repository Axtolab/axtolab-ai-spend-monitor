<?php
/**
 * AI Client event recorder for AI Spend Monitor.
 *
 * @package Axtolab_AI_Spend_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Listens to WordPress AI Client lifecycle hooks and records usage.
 *
 * @since 1.0.0
 */
class Aismon_Recorder {

	/**
	 * Singleton instance.
	 *
	 * @var Aismon_Recorder|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Aismon_Recorder
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers hook listeners.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ai_client_after_generate_result', array( $this, 'on_after_generate_result' ) );
	}

	/**
	 * Records a completed AI Client generation.
	 *
	 * @since 1.0.0
	 *
	 * @param object $event AfterGenerateResultEvent instance from the AI Client.
	 * @return void
	 */
	public function on_after_generate_result( $event ) {
		if ( ! is_object( $event ) || ! method_exists( $event, 'getResult' ) ) {
			return;
		}

		try {
			$result = $event->getResult();

			$prompt_tokens     = 0;
			$completion_tokens = 0;
			$total_tokens      = 0;
			$provider          = '';
			$model             = '';
			$capability        = '';

			if ( is_object( $result ) && method_exists( $result, 'getTokenUsage' ) ) {
				$usage             = $result->getTokenUsage();
				$prompt_tokens     = (int) $usage->getPromptTokens();
				$completion_tokens = (int) $usage->getCompletionTokens();
				$total_tokens      = (int) $usage->getTotalTokens();
			}

			if ( is_object( $result ) && method_exists( $result, 'getProviderMetadata' ) ) {
				$provider = (string) $result->getProviderMetadata()->getId();
			}

			if ( is_object( $result ) && method_exists( $result, 'getModelMetadata' ) ) {
				$model = (string) $result->getModelMetadata()->getId();
			}

			if ( method_exists( $event, 'getCapability' ) ) {
				$cap        = $event->getCapability();
				$capability = null === $cap ? '' : (string) $cap;
			}

			$source = Aismon_Attribution::resolve();

			Aismon_Store::instance()->record(
				array(
					'status'            => 'completed',
					'source_type'       => $source['type'],
					'source_slug'       => $source['slug'],
					'source_name'       => $source['name'],
					'provider'          => $provider,
					'model'             => $model,
					'capability'        => $capability,
					'prompt_tokens'     => $prompt_tokens,
					'completion_tokens' => $completion_tokens,
					'total_tokens'      => $total_tokens,
					'est_cost_usd'      => Aismon_Rates::estimate( $model, $prompt_tokens, $completion_tokens ),
				)
			);
		} catch ( Throwable $t ) {
			// Recording must never break the calling plugin's AI request.
			unset( $t );
		}
	}
}
