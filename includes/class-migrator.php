<?php
/**
 * Importazione delle impostazioni dal plugin Advanced Google reCAPTCHA (WP Captcha).
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha;

defined( 'ABSPATH' ) || exit;

class Migrator {

	const LEGACY_PLUGIN = 'advanced-google-recaptcha/advanced-google-recaptcha.php';

	/**
	 * Opzioni del vecchio plugin, se presenti.
	 *
	 * @return array
	 */
	public static function legacy_options() {
		$out = array();

		$wpcaptcha = get_option( 'wpcaptcha_options', false );

		if ( is_array( $wpcaptcha ) && isset( $wpcaptcha['captcha'] ) ) {
			$out['wpcaptcha_options'] = $wpcaptcha;
		}

		$agr = get_option( 'agr_options', false );

		if ( is_array( $agr ) && isset( $agr['captcha_type'] ) ) {
			$out['agr_options'] = $agr;
		}

		return $out;
	}

	/**
	 * C'è qualcosa da importare?
	 *
	 * @return bool
	 */
	public static function available() {
		if ( get_option( 'mm_recaptcha_migrated', false ) ) {
			return false;
		}

		return (bool) self::legacy_options();
	}

	/**
	 * Il vecchio plugin è ancora attivo?
	 *
	 * @return bool
	 */
	public static function legacy_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( self::LEGACY_PLUGIN );
	}

	/**
	 * Anteprima leggibile di cosa verrà importato.
	 *
	 * @return array
	 */
	public static function preview() {
		$legacy = self::legacy_options();

		if ( ! $legacy ) {
			return array();
		}

		$mapped = self::map( $legacy );
		$rows   = array();

		$provider = Provider_Registry::get( $mapped['options']['provider'] );

		$rows[] = array(
			'label' => __( 'Provider', 'mm-recaptcha' ),
			'value' => $provider ? $provider->label() : $mapped['options']['provider'],
		);

		$pid = $mapped['options']['provider'];

		if ( isset( $mapped['options'][ $pid ]['site_key'] ) && '' !== $mapped['options'][ $pid ]['site_key'] ) {
			$rows[] = array(
				'label' => __( 'Site key', 'mm-recaptcha' ),
				'value' => self::mask( $mapped['options'][ $pid ]['site_key'] ),
			);
		}

		if ( isset( $mapped['options'][ $pid ]['secret_key'] ) && '' !== $mapped['options'][ $pid ]['secret_key'] ) {
			$rows[] = array(
				'label' => __( 'Secret key', 'mm-recaptcha' ),
				'value' => self::mask( $mapped['options'][ $pid ]['secret_key'] ),
			);
		}

		$active_forms = array();

		foreach ( $mapped['options']['forms'] as $key => $value ) {
			if ( $value ) {
				$active_forms[] = $key;
			}
		}

		$rows[] = array(
			'label' => __( 'Moduli protetti', 'mm-recaptcha' ),
			'value' => $active_forms ? implode( ', ', $active_forms ) : __( 'nessuno', 'mm-recaptcha' ),
		);

		return array(
			'rows'  => $rows,
			'notes' => $mapped['notes'],
		);
	}

	/**
	 * Esegue l'importazione.
	 *
	 * @return array Riepilogo.
	 */
	public static function import() {
		$legacy = self::legacy_options();

		if ( ! $legacy ) {
			return array(
				'imported' => false,
				'notes'    => array( __( 'Nessuna impostazione da importare.', 'mm-recaptcha' ) ),
			);
		}

		$mapped = self::map( $legacy );

		Options::update( $mapped['options'] );

		update_option( 'mm_recaptcha_migrated', current_time( 'mysql', true ), false );
		update_option( 'mm_recaptcha_legacy_backup', $legacy, false );
		delete_option( 'mm_recaptcha_migration_notice' );

		return array(
			'imported' => true,
			'notes'    => $mapped['notes'],
		);
	}

	/**
	 * Converte le vecchie opzioni nel nuovo schema.
	 *
	 * @param array $legacy Opzioni originali.
	 * @return array
	 */
	protected static function map( array $legacy ) {
		$options = Options::all();
		$notes   = array();

		$source = isset( $legacy['wpcaptcha_options'] ) ? $legacy['wpcaptcha_options'] : array();
		$old    = isset( $legacy['agr_options'] ) ? $legacy['agr_options'] : array();

		// Provider.
		$provider   = 'math';
		$site_key   = '';
		$secret_key = '';

		if ( $source ) {
			$map = array(
				'recaptchav2' => 'recaptcha_v2',
				'recaptchav3' => 'recaptcha_v3',
				'builtin'     => 'math',
				'icons'       => 'math',
				'hcaptcha'    => 'hcaptcha',
				'cloudflare'  => 'turnstile',
				'disabled'    => 'math',
			);

			$legacy_provider = isset( $source['captcha'] ) ? $source['captcha'] : 'disabled';
			$provider        = isset( $map[ $legacy_provider ] ) ? $map[ $legacy_provider ] : 'math';
			$site_key        = isset( $source['captcha_site_key'] ) ? (string) $source['captcha_site_key'] : '';
			$secret_key      = isset( $source['captcha_secret_key'] ) ? (string) $source['captcha_secret_key'] : '';

			if ( 'disabled' === $legacy_provider ) {
				$notes[] = __( 'Nel vecchio plugin il captcha era disattivato: qui è stato preselezionato il captcha matematico, che non richiede chiavi.', 'mm-recaptcha' );
			}

			if ( 'icons' === $legacy_provider ) {
				$notes[] = __( 'Il captcha a icone non esiste in questo plugin: è stato sostituito con il captcha matematico.', 'mm-recaptcha' );
			}

			if ( in_array( $legacy_provider, array( 'hcaptcha', 'cloudflare' ), true ) && ( '' === $site_key || '' === $secret_key ) ) {
				$notes[] = __( 'Il vecchio plugin non implementava davvero hCaptcha e Turnstile (erano funzioni a pagamento): inserisci le chiavi per attivarli.', 'mm-recaptcha' );
			}

			if ( isset( $source['captcha_challenge_text'] ) && '' !== $source['captcha_challenge_text'] ) {
				$options['math']['challenge_text'] = sanitize_text_field( $source['captcha_challenge_text'] );
			}

			$forms = array(
				'wp_login'         => ! empty( $source['captcha_show_login'] ) ? 1 : 0,
				'wp_register'      => ! empty( $source['captcha_show_wp_registration'] ) ? 1 : 0,
				'wp_lostpassword'  => ! empty( $source['captcha_show_wp_lost_password'] ) ? 1 : 0,
				'wp_comments'      => ! empty( $source['captcha_show_wp_comment'] ) ? 1 : 0,
				'woo_login'        => ! empty( $source['captcha_show_login'] ) ? 1 : 0,
				'woo_register'     => ! empty( $source['captcha_show_woo_registration'] ) ? 1 : 0,
				'woo_lostpassword' => ! empty( $source['captcha_show_wp_lost_password'] ) ? 1 : 0,
				'woo_checkout'     => ! empty( $source['captcha_show_woo_checkout'] ) ? 1 : 0,
				'woo_pay'          => ! empty( $source['captcha_show_woo_checkout'] ) ? 1 : 0,
			);

			$options['forms'] = array_merge( $options['forms'], $forms );

			if ( ! empty( $source['captcha_show_edd_registration'] ) || ! empty( $source['captcha_show_bp_registration'] ) ) {
				$notes[] = __( 'Le impostazioni per Easy Digital Downloads e BuddyPress non sono state importate: quei plugin non sono installati su questo sito.', 'mm-recaptcha' );
			}
		} elseif ( $old ) {
			$provider   = ( isset( $old['captcha_type'] ) && 'v3' === $old['captcha_type'] ) ? 'recaptcha_v3' : 'recaptcha_v2';
			$site_key   = isset( $old['site_key'] ) ? (string) $old['site_key'] : '';
			$secret_key = isset( $old['secret_key'] ) ? (string) $old['secret_key'] : '';

			$options['forms'] = array_merge(
				$options['forms'],
				array(
					'wp_login'        => ! empty( $old['enable_login'] ) ? 1 : 0,
					'wp_register'     => ! empty( $old['enable_register'] ) ? 1 : 0,
					'wp_lostpassword' => ! empty( $old['enable_lost_password'] ) ? 1 : 0,
					'wp_comments'     => ! empty( $old['enable_comment_form'] ) ? 1 : 0,
					'woo_register'    => ! empty( $old['enable_woo_register'] ) ? 1 : 0,
					'woo_checkout'    => ! empty( $old['enable_woo_checkout'] ) ? 1 : 0,
				)
			);
		}

		$options['provider'] = $provider;

		if ( isset( $options[ $provider ] ) && is_array( $options[ $provider ] ) ) {
			if ( array_key_exists( 'site_key', $options[ $provider ] ) && '' !== $site_key ) {
				$options[ $provider ]['site_key'] = sanitize_text_field( $site_key );
			}

			if ( array_key_exists( 'secret_key', $options[ $provider ] ) && '' !== $secret_key ) {
				$options[ $provider ]['secret_key'] = sanitize_text_field( $secret_key );
			}
		}

		if ( 'recaptcha_v3' === $provider ) {
			$notes[] = __( 'Il vecchio plugin usava una soglia fissa di 0.5 per reCAPTCHA v3: ora è configurabile.', 'mm-recaptcha' );
		}

		$notes[] = __( 'Honeypot e trappola temporale sono stati attivati: sono controlli aggiuntivi che il vecchio plugin non aveva.', 'mm-recaptcha' );
		$notes[] = __( 'Le impostazioni di firewall, blocco per paese e protezione accessi del vecchio plugin non rientrano in questo plugin e non sono state importate.', 'mm-recaptcha' );

		return array(
			'options' => $options,
			'notes'   => $notes,
		);
	}

	/**
	 * Oscura parzialmente una chiave.
	 *
	 * @param string $value Valore.
	 * @return string
	 */
	protected static function mask( $value ) {
		$value = (string) $value;

		if ( strlen( $value ) <= 8 ) {
			return str_repeat( '*', strlen( $value ) );
		}

		return substr( $value, 0, 4 ) . str_repeat( '*', 6 ) . substr( $value, -4 );
	}
}
