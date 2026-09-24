<?php
/**
 * Avvio del plugin.
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha;

use MM_Recaptcha\Integrations\Integration_WP;
use MM_Recaptcha\Integrations\Integration_WooCommerce;
use MM_Recaptcha\Integrations\Integration_CF7;
use MM_Recaptcha\Providers\Math;

defined( 'ABSPATH' ) || exit;

class Plugin {

	/**
	 * Istanza singola.
	 *
	 * @var Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Istanza.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registra gli hook.
	 */
	public function boot() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 5 );
		add_action( 'init', array( $this, 'load_integrations' ), 5 );

		add_action( 'wp_ajax_mm_recaptcha_refresh', array( $this, 'ajax_refresh' ) );
		add_action( 'wp_ajax_nopriv_mm_recaptcha_refresh', array( $this, 'ajax_refresh' ) );

		if ( is_admin() ) {
			Admin::init();
		}

		Shortcode::init();
	}

	/**
	 * Traduzioni.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'mm-recaptcha', false, dirname( MM_RECAPTCHA_BASENAME ) . '/languages' );
	}

	/**
	 * Integrazioni di front-end.
	 */
	public function load_integrations() {
		if ( wp_installing() ) {
			return;
		}

		Integration_WP::instance()->init();

		if ( class_exists( 'WooCommerce' ) ) {
			Integration_WooCommerce::instance()->init();
		}

		if ( defined( 'WPCF7_VERSION' ) ) {
			Integration_CF7::instance()->init();
		}
	}

	/**
	 * Rigenera la sfida del captcha matematico (necessario con la cache a pagina intera).
	 */
	public function ajax_refresh() {
		check_ajax_referer( 'mm_recaptcha_public', 'nonce' );

		$provider = Provider_Registry::active();

		if ( ! $provider instanceof Math ) {
			wp_send_json_error( array( 'message' => __( 'Provider non compatibile.', 'mm-recaptcha' ) ), 400 );
		}

		wp_send_json_success( $provider->fresh_challenge() );
	}

	/**
	 * Attivazione.
	 */
	public static function activate() {
		$options = get_option( Options::KEY, false );

		if ( false === $options ) {
			add_option( Options::KEY, Options::defaults(), '', 'yes' );
		}

		if ( Migrator::available() ) {
			update_option( 'mm_recaptcha_migration_notice', 1, false );
		}
	}

	/**
	 * Disattivazione: nessun dato rimosso, solo pulizia dei transient.
	 */
	public static function deactivate() {
		delete_option( 'mm_recaptcha_migration_notice' );
	}
}
