<?php
defined( 'ABSPATH' ) || exit;

class Lookit_CF_Purge_Admin_Bar {

	public static function init() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'add_purge_button' ), 999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function add_purge_button( WP_Admin_Bar $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'lookit-cf-purge-group',
				'title' => '☁ CF Purge',
				'href'  => false,
			)
		);
		$wp_admin_bar->add_node(
			array(
				'id'     => 'lookit-cf-purge-open',
				'parent' => 'lookit-cf-purge-group',
				'title'  => __( 'Clear cache…', 'lookit-cf-purge' ),
				'href'   => '#lookit-cf-dialog',
			)
		);
		add_action( is_admin() ? 'admin_footer' : 'wp_footer', array( __CLASS__, 'render_dialog' ) );
	}

	private static function get_current_url(): string {

		if ( is_admin() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only use of post ID from URL, no form processing
			$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
			if ( $post_id ) {
				return (string) get_permalink( $post_id );
			}
			return '';
		}

		if ( is_singular() ) {
			return (string) get_permalink();
		}
		if ( is_tax() || is_category() || is_tag() ) {
			return (string) get_term_link( get_queried_object() );
		}
		if ( is_post_type_archive() ) {
			return (string) get_post_type_archive_link( get_queried_object()->name );
		}
		if ( is_author() ) {
			return (string) get_author_posts_url( get_queried_object_id() );
		}
		if ( is_home() || is_front_page() ) {
			return (string) home_url( '/' );
		}

		global $wp;
		return home_url( $wp->request ? '/' . $wp->request . '/' : '/' );
	}

	public static function enqueue_assets() {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_style( 'lookit-cf-purge', LOOKIT_CF_PURGE_URL . 'assets/admin.css', array(), LOOKIT_CF_PURGE_VERSION );
		wp_enqueue_script( 'lookit-cf-purge', LOOKIT_CF_PURGE_URL . 'assets/admin.js', array( 'wp-i18n' ), LOOKIT_CF_PURGE_VERSION, true );
		wp_add_inline_script(
			'lookit-cf-purge',
			'window.lookitCfPurge = ' . wp_json_encode(
				array(
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'nonce'      => wp_create_nonce( 'lookit_cf_purge_url' ),
					'currentUrl' => self::get_current_url(),
					'homeUrl'    => home_url( '/' ),
				),
				JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
			) . ';',
			'before'
		);
	}

	public static function render_dialog() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<dialog id="lookit-cf-dialog" aria-labelledby="lookit-cf-title">
			<form id="lookit-cf-form">
				<h2 id="lookit-cf-title"><?php esc_html_e( 'Clear Cloudflare cache', 'lookit-cf-purge' ); ?></h2>
				<fieldset id="lookit-cf-controls">
					<fieldset id="lookit-cf-page">
						<label for="lookit-cf-address"><?php esc_html_e( 'Page address', 'lookit-cf-purge' ); ?></label>
						<input id="lookit-cf-address" type="url" required autocomplete="off" spellcheck="false">
						<fieldset class="lookit-cf-choices">
							<legend><?php esc_html_e( 'What should we clear?', 'lookit-cf-purge' ); ?></legend>
							<label class="lookit-cf-choice">
								<input type="radio" name="scope" value="url" checked>
								<span><strong><?php esc_html_e( 'This URL', 'lookit-cf-purge' ); ?></strong><small><?php esc_html_e( 'Clears its desktop, mobile, and tablet copies. Other custom cache variants may remain.', 'lookit-cf-purge' ); ?></small></span>
							</label>
							<label class="lookit-cf-choice">
								<input type="radio" name="scope" value="prefix">
								<span><strong><?php esc_html_e( 'All URLs starting with this address', 'lookit-cf-purge' ); ?></strong><small><?php esc_html_e( 'Clears all device, country, language, cookie, and header variants, including matching pages and query strings.', 'lookit-cf-purge' ); ?></small></span>
							</label>
						</fieldset>
						<p id="lookit-cf-preview" class="lookit-cf-notice" aria-live="polite" hidden></p>
					</fieldset>
					<p id="lookit-cf-entire-warning" class="lookit-cf-notice" hidden><?php esc_html_e( 'This clears the entire Cloudflare zone, including any other hostnames in that zone. Pages may load more slowly while their caches rebuild.', 'lookit-cf-purge' ); ?></p>
					<div class="lookit-cf-actions">
						<button id="lookit-cf-back" type="button" hidden><?php esc_html_e( 'Go back', 'lookit-cf-purge' ); ?></button>
						<button id="lookit-cf-cancel" type="button"><?php esc_html_e( 'Cancel', 'lookit-cf-purge' ); ?></button>
						<button id="lookit-cf-submit" type="submit" class="lookit-cf-primary"></button>
					</div>
					<div id="lookit-cf-footer"><button id="lookit-cf-entire" type="button"><?php esc_html_e( 'Clear the entire site instead…', 'lookit-cf-purge' ); ?></button></div>
				</fieldset>
				<p id="lookit-cf-result" role="status" aria-live="polite" tabindex="-1" hidden></p>
			</form>
		</dialog>
		<?php
	}
}
