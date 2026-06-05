<?php
defined( 'ABSPATH' ) || exit;

class Lookit_CF_Purge_Settings {

	const OPTION_KEY = 'lookit_cf_purge_settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_styles' ) );
	}

	public static function add_settings_page() {
		add_options_page(
			__( 'Lookit CF Purge', 'lookit-cf-purge' ),
			__( 'CF Purge Settings', 'lookit-cf-purge' ),
			'manage_options',
			'lookit-cf-purge',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			'lookit_cf_purge_group',
			self::OPTION_KEY,
			array( __CLASS__, 'sanitize_settings' )
		);

		add_settings_section(
			'lookit_cf_purge_main',
			__( 'Cloudflare API Credentials', 'lookit-cf-purge' ),
			array( __CLASS__, 'render_section_description' ),
			'lookit-cf-purge'
		);

		add_settings_field(
			'api_token',
			__( 'API Token', 'lookit-cf-purge' ),
			array( __CLASS__, 'render_api_token_field' ),
			'lookit-cf-purge',
			'lookit_cf_purge_main'
		);

		add_settings_field(
			'zone_id',
			__( 'Zone ID', 'lookit-cf-purge' ),
			array( __CLASS__, 'render_zone_id_field' ),
			'lookit-cf-purge',
			'lookit_cf_purge_main'
		);
	}

	public static function sanitize_settings( $input ) {
		$sanitized              = array();
		$sanitized['api_token'] = isset( $input['api_token'] ) ? sanitize_text_field( trim( $input['api_token'] ) ) : '';
		$sanitized['zone_id']   = isset( $input['zone_id'] ) ? sanitize_text_field( trim( $input['zone_id'] ) ) : '';
		return $sanitized;
	}

	public static function render_section_description() {
		?>
		<p>
			Enter your Cloudflare credentials below. Create a dedicated API token with
			<strong>Zone / Cache Purge / Purge</strong> and <strong>Zone / Zone / Read</strong>
			permissions, scoped to this site's zone only.
			<br>
			Find your Zone ID on the Cloudflare dashboard → your domain → Overview (right-hand sidebar).
		</p>
		<?php
	}

	public static function render_api_token_field() {
		$settings = self::get_settings();
		$token    = $settings['api_token'] ?? '';
		$masked   = $token ? str_repeat( '•', max( 0, strlen( $token ) - 6 ) ) . substr( $token, -6 ) : '';
		?>
		<input
			type="password"
			name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_token]"
			id="lookit_cf_api_token"
			value="<?php echo esc_attr( $token ); ?>"
			class="regular-text"
			autocomplete="off"
		>
		<?php if ( $token ) : ?>
			<p class="description">
				Currently set: <code><?php echo esc_html( $masked ); ?></code>
				&mdash; paste a new value to replace it.
			</p>
		<?php endif; ?>
		<?php
	}

	public static function render_zone_id_field() {
		$settings = self::get_settings();
		$zone_id  = $settings['zone_id'] ?? '';
		?>
		<input
			type="text"
			name="<?php echo esc_attr( self::OPTION_KEY ); ?>[zone_id]"
			id="lookit_cf_zone_id"
			value="<?php echo esc_attr( $zone_id ); ?>"
			class="regular-text"
			placeholder="e.g. 31d3f48f9bxxxxxxxxxxxxxx"
		>
		<?php
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Optionally test the connection
		$test_result = null;
		if ( isset( $_GET['lookit_cf_test'] ) && check_admin_referer( 'lookit_cf_test_connection' ) ) {
			$test_result = Lookit_CF_Purge_Cloudflare_Api::test_connection();
		}
		?>
		<div class="wrap lookit-cf-purge-settings">
			<h1>
				<span class="dashicons dashicons-cloud" style="font-size:28px;vertical-align:middle;margin-right:6px;color:#F6821F;"></span>
				<?php esc_html_e( 'Lookit CF Purge — Settings', 'lookit-cf-purge' ); ?>
			</h1>

			<?php if ( null !== $test_result ) : ?>
				<div class="notice notice-<?php echo $test_result['success'] ? 'success' : 'error'; ?> is-dismissible">
					<p><?php echo esc_html( $test_result['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'lookit_cf_purge_group' );
				do_settings_sections( 'lookit-cf-purge' );
				submit_button( __( 'Save Credentials', 'lookit-cf-purge' ) );
				?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Test Connection', 'lookit-cf-purge' ); ?></h2>
			<p><?php esc_html_e( 'Verify your credentials can reach Cloudflare before using the purge button.', 'lookit-cf-purge' ); ?></p>
			<?php
			$test_url = wp_nonce_url(
				add_query_arg( 'lookit_cf_test', '1', admin_url( 'options-general.php?page=lookit-cf-purge' ) ),
				'lookit_cf_test_connection'
			);
			?>
			<a href="<?php echo esc_url( $test_url ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Test Cloudflare Connection', 'lookit-cf-purge' ); ?>
			</a>

			<hr>
			<h2><?php esc_html_e( 'How It Works', 'lookit-cf-purge' ); ?></h2>
			<ul style="list-style:disc;margin-left:1.5em;line-height:1.8;">
				<li><?php esc_html_e( 'When viewing any post, page, or taxonomy archive in the frontend, a "Purge URL from Cloudflare" button appears in the WP Rocket admin bar menu.', 'lookit-cf-purge' ); ?></li>
				<li><?php esc_html_e( 'Clicking it sends only the current page URL to the Cloudflare API — a surgical single-URL purge.', 'lookit-cf-purge' ); ?></li>
				<li><?php esc_html_e( 'Your WP Rocket rules, other cached URLs, and site settings are completely unaffected.', 'lookit-cf-purge' ); ?></li>
				<li><?php esc_html_e( 'This plugin does NOT replace WP Rocket\'s Cloudflare integration — it adds to it.', 'lookit-cf-purge' ); ?></li>
			</ul>
		</div>
		<?php
	}

	public static function enqueue_admin_styles( $hook ) {
		if ( 'settings_page_lookit-cf-purge' !== $hook ) {
			return;
		}
	}

	/**
	 * Get saved settings array.
	 */
	public static function get_settings() {
		return get_option( self::OPTION_KEY, array() );
	}

	public static function get_api_token() {
		$settings = self::get_settings();
		return $settings['api_token'] ?? '';
	}

	public static function get_zone_id() {
		$settings = self::get_settings();
		return $settings['zone_id'] ?? '';
	}

	public static function is_configured() {
		return ! empty( self::get_api_token() ) && ! empty( self::get_zone_id() );
	}
}
