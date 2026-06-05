<?php
defined( 'ABSPATH' ) || exit;

class Lookit_CF_Purge_Admin_Bar {

	public static function init() {
		add_action( 'admin_bar_menu',        array( __CLASS__, 'add_purge_button' ), 999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_enqueue_scripts',    array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function add_purge_button( WP_Admin_Bar $wp_admin_bar ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_url = self::get_current_url();

		$wp_admin_bar->add_node( array(
			'id'    => 'lookit-cf-purge-group',
			'title' => '☁ CF Purge',
			'href'  => false,
			'meta'  => array( 'class' => 'lookit-cf-purge-top-level' ),
		) );

		if ( $current_url ) {
			$wp_admin_bar->add_node( array(
				'id'     => 'lookit-cf-purge-url',
				'parent' => 'lookit-cf-purge-group',
				'title'  => 'Purge This URL',
				'href'   => 'javascript:void(0)',
				'meta'   => array( 'title' => $current_url ),
			) );
		}

		$wp_admin_bar->add_node( array(
			'id'     => 'lookit-cf-purge-all',
			'parent' => 'lookit-cf-purge-group',
			'title'  => 'Purge Entire Site',
			'href'   => 'javascript:void(0)',
		) );

		$wp_admin_bar->add_node( array(
			'id'     => 'lookit-cf-purge-manual',
			'parent' => 'lookit-cf-purge-group',
			'title'  => self::manual_input_html(),
			'href'   => false,
			'meta'   => array( 'class' => 'lookit-cf-manual-wrap' ),
		) );

		$hook = is_admin() ? 'admin_footer' : 'wp_footer';
		add_action( $hook, function() use ( $current_url ) {
			self::print_inline_data( $current_url );
		} );
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

		if ( is_singular() )        return (string) get_permalink();
		if ( is_tax() || is_category() || is_tag() ) return (string) get_term_link( get_queried_object() );
		if ( is_post_type_archive() ) return (string) get_post_type_archive_link( get_queried_object()->name );
		if ( is_author() )          return (string) get_author_posts_url( get_queried_object_id() );
		if ( is_home() || is_front_page() ) return (string) home_url( '/' );

		global $wp;
		return home_url( $wp->request ? '/' . $wp->request . '/' : '/' );
	}

	private static function manual_input_html(): string {
		return '
			<div class="lookit-cf-manual-input" onclick="event.stopPropagation();">
				<span class="lookit-cf-manual-label">Or enter any URL:</span>
				<input
					type="text"
					id="lookit-cf-manual-url"
					placeholder="https://example.com/events/"
					autocomplete="off"
					spellcheck="false"
				>
				<button id="lookit-cf-manual-btn" type="button">Purge</button>
				<div id="lookit-cf-manual-result"></div>
			</div>
		';
	}

	public static function enqueue_assets() {

		if ( ! is_admin_bar_showing() ) return;
		if ( ! current_user_can( 'manage_options' ) ) return;

		$css = '
			#wp-admin-bar-lookit-cf-purge-group > .ab-item {
				color: #fff !important;
			}
			#wp-admin-bar-lookit-cf-purge-group:hover > .ab-item,
			#wp-admin-bar-lookit-cf-purge-group.hover > .ab-item {
				color: #fff !important;
			}
			#wp-admin-bar-lookit-cf-purge-group .ab-sub-wrapper {
				min-width: 480px;
			}
			#wp-admin-bar-lookit-cf-purge-url > .ab-item,
			#wp-admin-bar-lookit-cf-purge-all > .ab-item {
				color: #F6821F !important;
				padding: 8px 16px !important;
			}
			#wp-admin-bar-lookit-cf-purge-url > .ab-item:hover,
			#wp-admin-bar-lookit-cf-purge-all > .ab-item:hover {
				background: #F6821F !important;
				color: #fff !important;
			}
			#wp-admin-bar-lookit-cf-purge-url.lookit-cf-purging > .ab-item,
			#wp-admin-bar-lookit-cf-purge-all.lookit-cf-purging > .ab-item {
				opacity: 0.6;
				cursor: wait;
			}
			#wp-admin-bar-lookit-cf-purge-url.lookit-cf-success > .ab-item,
			#wp-admin-bar-lookit-cf-purge-all.lookit-cf-success > .ab-item {
				background: #46b450 !important;
				color: #fff !important;
			}
			#wp-admin-bar-lookit-cf-purge-url.lookit-cf-error > .ab-item,
			#wp-admin-bar-lookit-cf-purge-all.lookit-cf-error > .ab-item {
				background: #dc3232 !important;
				color: #fff !important;
			}
			#wp-admin-bar-lookit-cf-purge-manual {
				height: auto !important;
			}
			#wp-admin-bar-lookit-cf-purge-manual > .ab-item {
				height: auto !important;
				padding: 0 !important;
				background: transparent !important;
				cursor: default !important;
				display: block !important;
			}
			#wp-admin-bar-lookit-cf-purge-manual .lookit-cf-manual-input {
				padding: 10px 16px 14px !important;
				display: flex !important;
				flex-direction: column !important;
				gap: 7px !important;
				min-width: 480px !important;
				box-sizing: border-box !important;
			}
			#wp-admin-bar-lookit-cf-purge-manual .lookit-cf-manual-label {
				font-size: 11px !important;
				color: #aaa !important;
				text-transform: none !important;
				letter-spacing: 0.05em !important;
				margin: 0 !important;
				padding: 4px 0 0 0 !important;
				display: block !important;
			}
			#lookit-cf-manual-url {
				width: 100% !important;
				padding: 6px 10px !important;
				font-size: 12px !important;
				border: 1px solid #444 !important;
				border-radius: 3px !important;
				background: #1d2327 !important;
				color: #fff !important;
				box-sizing: border-box !important;
				outline: none !important;
				margin: 0 !important;
				display: block !important;
			}
			#lookit-cf-manual-url:focus {
				border-color: #F6821F !important;
			}
			#lookit-cf-manual-btn {
				display: inline-block !important;
				width: auto !important;
				align-self: flex-start !important;
				padding: 5px 14px !important;
				font-size: 12px !important;
				font-weight: 600 !important;
				background: #F6821F !important;
				color: #fff !important;
				border: none !important;
				border-radius: 3px !important;
				cursor: pointer !important;
				line-height: 1.5 !important;
				margin: 0 !important;
				height: auto !important;
			}
			#lookit-cf-manual-btn:hover {
				background: #d46e1a !important;
			}
			#lookit-cf-manual-btn:disabled {
				opacity: 0.6 !important;
				cursor: wait !important;
			}
			#lookit-cf-manual-result {
				font-size: 11px !important;
				min-height: 0 !important;
				line-height: 1.4 !important;
				margin: 0 !important;
			}
			#lookit-cf-manual-result.success { color: #46b450 !important; }
			#lookit-cf-manual-result.error   { color: #dc3232 !important; }

			/* Toast */
			#lookit-cf-toast {
				position: fixed !important;
				bottom: 32px !important;
				left: 50% !important;
				transform: translateX(-50%) translateY(20px) !important;
				background: #1d2327 !important;
				color: #fff !important;
				padding: 12px 24px !important;
				border-radius: 4px !important;
				font-size: 13px !important;
				font-family: Arial, sans-serif !important;
				font-weight: 500 !important;
				box-shadow: 0 4px 12px rgba(0,0,0,0.4) !important;
				z-index: 999999 !important;
				opacity: 0 !important;
				transition: opacity 0.25s ease, transform 0.25s ease !important;
				pointer-events: none !important;
				white-space: nowrap !important;
			}
			#lookit-cf-toast.lookit-cf-toast-show {
				opacity: 1 !important;
				transform: translateX(-50%) translateY(0) !important;
			}
			#lookit-cf-toast.success {
				border-left: 4px solid #46b450 !important;
			}
			#lookit-cf-toast.error {
				border-left: 4px solid #dc3232 !important;
			}
		';
		wp_add_inline_style( 'admin-bar', $css );
	}

	private static function print_inline_data( string $current_url ) {

		$nonce = wp_create_nonce( 'lookit_cf_purge_url' );
		?>
		<script id="lookit-cf-purge-data">
		document.addEventListener('DOMContentLoaded', function() {

			var LOOKIT_CF = {
				ajaxUrl:    <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
				nonce:      <?php echo wp_json_encode( $nonce ); ?>,
				currentUrl: <?php echo wp_json_encode( $current_url ); ?>
			};

			// Toast
			var toastEl = document.createElement('div');
			toastEl.id = 'lookit-cf-toast';
			document.body.appendChild(toastEl);
			var toastTimer = null;

			function showToast( msg, type ) {
				if ( toastTimer ) clearTimeout( toastTimer );
				toastEl.textContent = msg;
				toastEl.className = type;
				void toastEl.offsetWidth;
				toastEl.classList.add('lookit-cf-toast-show');
				toastTimer = setTimeout(function() {
					toastEl.classList.remove('lookit-cf-toast-show');
				}, 4000);
			}

			// ── Purge This URL ─────────────────────────────────────────────────
			var urlNode = document.getElementById('wp-admin-bar-lookit-cf-purge-url');
			if ( urlNode ) {
				var urlLink = urlNode.querySelector('a') || urlNode;
				urlLink.addEventListener('click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					if ( urlNode.classList.contains('lookit-cf-purging') ) return;
					if ( ! LOOKIT_CF.currentUrl ) return;
					if ( ! confirm('Purge this URL from Cloudflare edge cache?\n\n' + LOOKIT_CF.currentUrl) ) return;
					doPurge( 'lookit_cf_purge_url', LOOKIT_CF.currentUrl, urlNode, urlLink, 'Purge This URL' );
				});
			}

			// ── Purge Entire Site ──────────────────────────────────────────────
			var allNode = document.getElementById('wp-admin-bar-lookit-cf-purge-all');
			if ( allNode ) {
				var allLink = allNode.querySelector('a') || allNode;
				allLink.addEventListener('click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					if ( allNode.classList.contains('lookit-cf-purging') ) return;
					if ( ! confirm('Purge the ENTIRE Cloudflare cache for this site?\n\nThis will temporarily slow the site as pages re-cache.') ) return;
					doPurge( 'lookit_cf_purge_all', null, allNode, allLink, 'Purge Entire Site' );
				});
			}

			// ── Manual URL ─────────────────────────────────────────────────────
			var manualInput  = document.getElementById('lookit-cf-manual-url');
			var manualBtn    = document.getElementById('lookit-cf-manual-btn');
			var manualResult = document.getElementById('lookit-cf-manual-result');

			if ( manualBtn && manualInput ) {
				manualInput.addEventListener('click', function(e) { e.stopPropagation(); });
				manualInput.addEventListener('keydown', function(e) {
					if ( e.key === 'Enter' ) { e.preventDefault(); manualBtn.click(); }
				});
				manualBtn.addEventListener('click', function(e) {
					e.stopPropagation();
					var url = manualInput.value.trim();
					if ( ! url ) { manualInput.focus(); return; }
					if ( url.indexOf('http') !== 0 ) { url = 'https://' + url; manualInput.value = url; }
					manualBtn.disabled    = true;
					manualBtn.textContent = 'Purging…';
					manualResult.className = '';
					manualResult.textContent = '';
					doAjax( 'lookit_cf_purge_url', url, function( ok, msg ) {
						manualBtn.disabled    = false;
						manualBtn.textContent = 'Purge';
						if ( ok ) {
							manualInput.value = '';
							showToast( '✅ URL purged from Cloudflare cache', 'success' );
						} else {
							manualResult.className   = 'error';
							manualResult.textContent = '❌ ' + msg;
							showToast( '❌ Purge failed: ' + msg, 'error' );
						}
					});
				});
			}

			// ── Helpers ────────────────────────────────────────────────────────
			function doPurge( action, url, node, btn, resetLabel ) {
				node.classList.add('lookit-cf-purging');
				if ( btn ) btn.textContent = 'Purging…';
				doAjax( action, url, function( ok, msg ) {
					node.classList.remove('lookit-cf-purging');
					if ( ok ) {
						node.classList.add('lookit-cf-success');
						if ( btn ) btn.textContent = '✅ Done';
						showToast(
							action === 'lookit_cf_purge_all'
								? '✅ Entire site purged from Cloudflare cache'
								: '✅ URL purged from Cloudflare cache',
							'success'
						);
					} else {
						node.classList.add('lookit-cf-error');
						if ( btn ) btn.textContent = '❌ Failed';
						showToast( '❌ Cloudflare purge failed: ' + msg, 'error' );
					}
					setTimeout(function() {
						node.classList.remove('lookit-cf-success','lookit-cf-error');
						if ( btn ) btn.textContent = resetLabel;
					}, 4000);
				});
			}

			function doAjax( action, url, callback ) {
				var xhr = new XMLHttpRequest();
				xhr.open('POST', LOOKIT_CF.ajaxUrl, true);
				xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
				xhr.onreadystatechange = function() {
					if ( xhr.readyState !== 4 ) return;
					try {
						var res = JSON.parse(xhr.responseText);
						callback( res.success, res.data || '' );
					} catch(err) {
						callback( false, 'Unexpected response' );
					}
				};
				var body = 'action=' + encodeURIComponent(action) +
				           '&nonce=' + encodeURIComponent(LOOKIT_CF.nonce);
				if ( url ) body += '&url=' + encodeURIComponent(url);
				xhr.send(body);
			}

		});
		</script>
		<?php
	}
}
