<?php
/**
 * Admin dashboard for AI Spend Monitor.
 *
 * @package Axtolab_AI_Spend_Monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Tools > AI Spend Monitor screen.
 *
 * @since 1.0.0
 */
class Aismon_Dashboard {

	/**
	 * Shared Axtolab top-level menu slug (same across all Axtolab plugins).
	 */
	const PARENT_MENU_SLUG = 'axtolab';

	/**
	 * Singleton instance.
	 *
	 * @var Aismon_Dashboard|null
	 */
	private static $instance = null;

	/**
	 * Hook suffix returned when the dashboard page is registered.
	 *
	 * @var string|false
	 */
	private $hook_suffix = false;

	/**
	 * Returns the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Aismon_Dashboard
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers admin hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register() {
		// Priority 20: after the Axtolab AI Connector (priority 10) registers
		// the shared 'axtolab' parent menu, when it is installed.
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Adds the dashboard under the shared Axtolab menu, creating the
	 * top-level menu when no other Axtolab plugin has registered it.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_menu() {
		global $admin_page_hooks;

		$parent_exists = ! empty( $admin_page_hooks[ self::PARENT_MENU_SLUG ] );

		if ( ! $parent_exists ) {
			add_menu_page(
				__( 'Axtolab', 'axtolab-ai-spend-monitor' ),
				__( 'Axtolab', 'axtolab-ai-spend-monitor' ),
				'manage_options',
				self::PARENT_MENU_SLUG,
				array( $this, 'render' ),
				self::menu_icon(),
				80
			);
		}

		$this->hook_suffix = add_submenu_page(
			self::PARENT_MENU_SLUG,
			__( 'AI Spend Monitor', 'axtolab-ai-spend-monitor' ),
			__( 'AI Spend Monitor', 'axtolab-ai-spend-monitor' ),
			'manage_options',
			'aismon',
			array( $this, 'render' )
		);

		// When this plugin created the parent, drop the duplicate auto-submenu
		// entry so the sidebar shows a single "AI Spend Monitor" item.
		if ( ! $parent_exists ) {
			remove_submenu_page( self::PARENT_MENU_SLUG, self::PARENT_MENU_SLUG );
		}
	}

	/**
	 * Returns the shared Axtolab brand icon as a data URI, with a dashicon
	 * fallback when the bundled SVG is unavailable.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private static function menu_icon() {
		static $cached = null;
		if ( null !== $cached ) {
			return $cached;
		}

		$svg_path = AISMON_PLUGIN_DIR . 'assets/axtolab-mark.svg';
		if ( ! is_readable( $svg_path ) ) {
			$cached = 'dashicons-chart-area';
			return $cached;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a bundled plugin SVG asset from an internal constant path; WP_Filesystem adds overhead with no benefit for a static plugin-owned file.
		$svg = file_get_contents( $svg_path );
		if ( false === $svg ) {
			$cached = 'dashicons-chart-area';
			return $cached;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Standard data: URI encoding of the admin-menu SVG icon (RFC 2397), not obfuscation.
		$cached = 'data:image/svg+xml;base64,' . base64_encode( $svg );
		return $cached;
	}

	/**
	 * Enqueues the dashboard stylesheet on the plugin screen only.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue( $hook_suffix ) {
		if ( false === $this->hook_suffix || $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'aismon-admin',
			AISMON_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			AISMON_VERSION
		);
	}

	/**
	 * Returns the selected reporting period.
	 *
	 * @since 1.0.0
	 *
	 * @return array { @type string $key Period key. @type string $since UTC datetime. @type string $label Label. }
	 */
	private function period() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view filter; no state change.
		$key = isset( $_GET['aismon_period'] ) ? sanitize_key( wp_unslash( $_GET['aismon_period'] ) ) : 'month';

		switch ( $key ) {
			case '7d':
				return array(
					'key'   => '7d',
					'since' => gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ),
					'label' => __( 'Last 7 days', 'axtolab-ai-spend-monitor' ),
				);
			case '30d':
				return array(
					'key'   => '30d',
					'since' => gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ),
					'label' => __( 'Last 30 days', 'axtolab-ai-spend-monitor' ),
				);
			default:
				return array(
					'key'   => 'month',
					'since' => gmdate( 'Y-m-01 00:00:00' ),
					'label' => __( 'This month', 'axtolab-ai-spend-monitor' ),
				);
		}
	}

	/**
	 * Formats a cost value for display.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $cost Cost value or null.
	 * @return string
	 */
	private function format_cost( $cost ) {
		if ( null === $cost || '' === $cost ) {
			return '—';
		}
		return '$' . number_format_i18n( (float) $cost, 4 );
	}

	/**
	 * Renders the dashboard page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$store   = Aismon_Store::instance();
		$period  = $this->period();
		$totals  = $store->totals( $period['since'] );
		$sources = $store->summary_by_source( $period['since'] );
		$series  = $store->daily_series( gmdate( 'Y-m-d 00:00:00', time() - 30 * DAY_IN_SECONDS ) );
		$recent  = $store->recent( 50 );

		$base_url = admin_url( 'admin.php?page=aismon' );
		$periods  = array(
			'month' => __( 'This month', 'axtolab-ai-spend-monitor' ),
			'30d'   => __( 'Last 30 days', 'axtolab-ai-spend-monitor' ),
			'7d'    => __( 'Last 7 days', 'axtolab-ai-spend-monitor' ),
		);
		?>
		<div class="wrap aismon-wrap">
			<h1><?php esc_html_e( 'AI Spend Monitor', 'axtolab-ai-spend-monitor' ); ?></h1>

			<?php if ( ! function_exists( 'wp_ai_client_prompt' ) ) : ?>
				<div class="notice notice-warning inline"><p>
					<?php esc_html_e( 'The WordPress AI Client (WordPress 7.0+) is not available on this site, so no usage can be recorded yet.', 'axtolab-ai-spend-monitor' ); ?>
				</p></div>
			<?php endif; ?>

			<ul class="subsubsub aismon-periods">
				<?php
				$links = array();
				foreach ( $periods as $key => $label ) {
					$links[] = sprintf(
						'<li><a href="%s" class="%s">%s</a></li>',
						esc_url( add_query_arg( 'aismon_period', $key, $base_url ) ),
						$period['key'] === $key ? 'current' : '',
						esc_html( $label )
					);
				}
				echo wp_kses_post( implode( ' | ', $links ) );
				?>
			</ul>
			<a
				href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aismon_export&aismon_period=' . $period['key'] ), 'aismon_export' ) ); ?>"
				class="button aismon-export"
			>
				<?php esc_html_e( 'Export CSV', 'axtolab-ai-spend-monitor' ); ?>
			</a>
			<div class="clear"></div>

			<div class="aismon-cards">
				<div class="aismon-card">
					<span class="aismon-card-label"><?php echo esc_html( $period['label'] ); ?> — <?php esc_html_e( 'AI calls', 'axtolab-ai-spend-monitor' ); ?></span>
					<span class="aismon-card-value"><?php echo esc_html( number_format_i18n( (int) $totals['calls'] ) ); ?></span>
				</div>
				<div class="aismon-card">
					<span class="aismon-card-label"><?php echo esc_html( $period['label'] ); ?> — <?php esc_html_e( 'Tokens', 'axtolab-ai-spend-monitor' ); ?></span>
					<span class="aismon-card-value"><?php echo esc_html( number_format_i18n( (int) $totals['total_tokens'] ) ); ?></span>
				</div>
				<div class="aismon-card">
					<span class="aismon-card-label"><?php echo esc_html( $period['label'] ); ?> — <?php esc_html_e( 'Estimated cost', 'axtolab-ai-spend-monitor' ); ?></span>
					<span class="aismon-card-value"><?php echo esc_html( $this->format_cost( $totals['est_cost_usd'] ) ); ?></span>
				</div>
				<div class="aismon-card">
					<span class="aismon-card-label"><?php echo esc_html( $period['label'] ); ?> — <?php esc_html_e( 'Blocked calls', 'axtolab-ai-spend-monitor' ); ?></span>
					<span class="aismon-card-value"><?php echo esc_html( number_format_i18n( (int) $totals['blocked_calls'] ) ); ?></span>
				</div>
			</div>

			<?php
			/**
			 * Fires after the summary cards on the AI Spend Monitor screen.
			 *
			 * Extensions can use this to render additional panels.
			 *
			 * @since 1.0.0
			 *
			 * @param array $totals Totals for the selected period.
			 * @param array $period Selected period descriptor.
			 */
			do_action( 'aismon_dashboard_after_summary', $totals, $period );
			?>

			<?php $alert = Aismon_Alerts::settings(); ?>
			<h2><?php esc_html_e( 'Spend notification', 'axtolab-ai-spend-monitor' ); ?></h2>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only confirmation flag. ?>
			<?php if ( isset( $_GET['aismon_saved'] ) ) : ?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'Notification settings saved.', 'axtolab-ai-spend-monitor' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aismon-alert-form">
				<?php wp_nonce_field( 'aismon_save_alert' ); ?>
				<input type="hidden" name="action" value="aismon_save_alert" />
				<p>
					<label>
						<?php esc_html_e( 'Email me when estimated spend this month passes $', 'axtolab-ai-spend-monitor' ); ?>
						<input type="number" step="0.01" min="0" name="aismon_alert_usd" value="<?php echo esc_attr( $alert['monthly_usd'] > 0 ? $alert['monthly_usd'] : '' ); ?>" class="small-text" />
					</label>
					<label>
						<?php esc_html_e( 'to', 'axtolab-ai-spend-monitor' ); ?>
						<input type="email" name="aismon_alert_email" value="<?php echo esc_attr( $alert['email'] ); ?>" class="regular-text" />
					</label>
					<?php submit_button( __( 'Save', 'axtolab-ai-spend-monitor' ), 'secondary', 'submit', false ); ?>
				</p>
				<p class="description"><?php esc_html_e( 'One email per month. Leave the amount empty or 0 to disable. This is a notification only — nothing is blocked.', 'axtolab-ai-spend-monitor' ); ?></p>
			</form>

			<h2><?php esc_html_e( 'Daily estimated cost (last 30 days)', 'axtolab-ai-spend-monitor' ); ?></h2>
			<?php $this->render_chart( $series ); ?>

			<h2><?php esc_html_e( 'Usage by source', 'axtolab-ai-spend-monitor' ); ?></h2>
			<table class="widefat striped aismon-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Source', 'axtolab-ai-spend-monitor' ); ?></th>
						<th><?php esc_html_e( 'Type', 'axtolab-ai-spend-monitor' ); ?></th>
						<th class="aismon-num"><?php esc_html_e( 'Calls', 'axtolab-ai-spend-monitor' ); ?></th>
						<th class="aismon-num"><?php esc_html_e( 'Prompt tokens', 'axtolab-ai-spend-monitor' ); ?></th>
						<th class="aismon-num"><?php esc_html_e( 'Completion tokens', 'axtolab-ai-spend-monitor' ); ?></th>
						<th class="aismon-num"><?php esc_html_e( 'Estimated cost', 'axtolab-ai-spend-monitor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $sources ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No AI usage recorded in this period yet.', 'axtolab-ai-spend-monitor' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $sources as $row ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $row['source_name'] ? $row['source_name'] : $row['source_slug'] ); ?></strong></td>
								<td><?php echo esc_html( $row['source_type'] ); ?></td>
								<td class="aismon-num"><?php echo esc_html( number_format_i18n( (int) $row['calls'] ) ); ?></td>
								<td class="aismon-num"><?php echo esc_html( number_format_i18n( (int) $row['prompt_tokens'] ) ); ?></td>
								<td class="aismon-num"><?php echo esc_html( number_format_i18n( (int) $row['completion_tokens'] ) ); ?></td>
								<td class="aismon-num"><?php echo esc_html( $this->format_cost( $row['est_cost_usd'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Recent AI calls', 'axtolab-ai-spend-monitor' ); ?></h2>
			<table class="widefat striped aismon-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time (UTC)', 'axtolab-ai-spend-monitor' ); ?></th>
						<th><?php esc_html_e( 'Source', 'axtolab-ai-spend-monitor' ); ?></th>
						<th><?php esc_html_e( 'Provider', 'axtolab-ai-spend-monitor' ); ?></th>
						<th><?php esc_html_e( 'Model', 'axtolab-ai-spend-monitor' ); ?></th>
						<th><?php esc_html_e( 'Status', 'axtolab-ai-spend-monitor' ); ?></th>
						<th class="aismon-num"><?php esc_html_e( 'Tokens', 'axtolab-ai-spend-monitor' ); ?></th>
						<th class="aismon-num"><?php esc_html_e( 'Estimated cost', 'axtolab-ai-spend-monitor' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $recent ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No AI calls recorded yet.', 'axtolab-ai-spend-monitor' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $recent as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['created_at'] ); ?></td>
								<td><?php echo esc_html( $row['source_name'] ? $row['source_name'] : $row['source_slug'] ); ?></td>
								<td><?php echo esc_html( $row['provider'] ); ?></td>
								<td><?php echo esc_html( $row['model'] ); ?></td>
								<td><?php echo esc_html( $row['status'] ); ?></td>
								<td class="aismon-num"><?php echo esc_html( number_format_i18n( (int) $row['total_tokens'] ) ); ?></td>
								<td class="aismon-num"><?php echo esc_html( $this->format_cost( $row['est_cost_usd'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<p class="description aismon-footnote">
				<?php esc_html_e( 'Costs are estimates based on published list prices per model (standard tier, no caching or batch discounts) and may differ from your provider invoice. Data is stored locally on this site; nothing is sent to any external service.', 'axtolab-ai-spend-monitor' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Renders the daily cost bar chart as plain markup.
	 *
	 * @since 1.0.0
	 *
	 * @param array[] $series Daily aggregate rows.
	 * @return void
	 */
	private function render_chart( $series ) {
		if ( empty( $series ) ) {
			echo '<p class="description">' . esc_html__( 'No data yet.', 'axtolab-ai-spend-monitor' ) . '</p>';
			return;
		}

		$by_day = array();
		$max    = 0.0;
		foreach ( $series as $row ) {
			$cost                  = null === $row['est_cost_usd'] ? 0.0 : (float) $row['est_cost_usd'];
			$by_day[ $row['day'] ] = array(
				'cost'  => $cost,
				'calls' => (int) $row['calls'],
			);
			$max                   = max( $max, $cost );
		}

		echo '<div class="aismon-chart" role="img" aria-label="' . esc_attr__( 'Daily estimated AI cost for the last 30 days', 'axtolab-ai-spend-monitor' ) . '">';
		for ( $i = 29; $i >= 0; $i-- ) {
			$day    = gmdate( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
			$cost   = isset( $by_day[ $day ] ) ? $by_day[ $day ]['cost'] : 0.0;
			$calls  = isset( $by_day[ $day ] ) ? $by_day[ $day ]['calls'] : 0;
			$height = $max > 0 ? max( 2, (int) round( ( $cost / $max ) * 100 ) ) : 2;
			$title  = sprintf(
				/* translators: 1: date, 2: estimated cost, 3: number of calls. */
				__( '%1$s — $%2$s (%3$d calls)', 'axtolab-ai-spend-monitor' ),
				$day,
				number_format_i18n( $cost, 4 ),
				$calls
			);
			printf(
				'<span class="aismon-bar" style="height:%d%%" title="%s"></span>',
				(int) $height,
				esc_attr( $title )
			);
		}
		echo '</div>';
	}
}
