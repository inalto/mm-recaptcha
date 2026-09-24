<?php
/**
 * Composizione dell'HTML del captcha e caricamento degli asset.
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha;

use MM_Recaptcha\Providers\Provider;

defined( 'ABSPATH' ) || exit;

class Renderer {

	/**
	 * Contatore delle istanze nella richiesta.
	 *
	 * @var int
	 */
	protected static $instances = 0;

	/**
	 * Ambiti aperti (usati per evitare doppi widget nei temi).
	 *
	 * @var array
	 */
	protected static $scopes = array();

	/**
	 * Superfici già disegnate nella richiesta.
	 *
	 * @var array
	 */
	protected static $rendered = array();

	/**
	 * Gli asset sono già stati richiesti?
	 *
	 * @var bool
	 */
	protected static $assets_queued = false;

	/**
	 * Apre un ambito (per esempio il modulo di registrazione WooCommerce).
	 *
	 * @param string $scope Nome.
	 */
	public static function enter_scope( $scope ) {
		self::$scopes[ $scope ] = true;
	}

	/**
	 * Chiude un ambito.
	 *
	 * @param string $scope Nome.
	 */
	public static function exit_scope( $scope ) {
		unset( self::$scopes[ $scope ] );
	}

	/**
	 * Siamo dentro un ambito?
	 *
	 * @param string $scope Nome.
	 * @return bool
	 */
	public static function in_scope( $scope ) {
		return ! empty( self::$scopes[ $scope ] );
	}

	/**
	 * Una superficie è già stata disegnata in questa richiesta?
	 *
	 * @param string $surface Superficie.
	 * @return bool
	 */
	public static function has_rendered( $surface ) {
		return ! empty( self::$rendered[ $surface ] );
	}

	/**
	 * Il captcha va mostrato per questa superficie?
	 *
	 * @param string $surface Superficie.
	 * @return bool
	 */
	public static function should_render( $surface ) {
		$should = Verifier::should_protect( $surface );

		/**
		 * Consente di forzare o sopprimere il render.
		 *
		 * @param bool   $should  Esito.
		 * @param string $surface Superficie.
		 */
		return (bool) apply_filters( 'mm_recaptcha_should_render', $should, $surface );
	}

	/**
	 * HTML completo del campo captcha.
	 *
	 * @param string $surface Superficie.
	 * @param array  $args    Argomenti opzionali.
	 * @return string
	 */
	public static function render( $surface, array $args = array() ) {
		if ( ! self::should_render( $surface ) ) {
			return '';
		}

		$provider = Provider_Registry::active();

		if ( ! $provider instanceof Provider ) {
			return '';
		}

		self::$instances++;
		self::$rendered[ $surface ] = true;

		$args = wp_parse_args(
			$args,
			array(
				'id'      => sprintf( 'mm-rc-%s-%d', sanitize_html_class( $surface ), self::$instances ),
				'surface' => $surface,
				'class'   => '',
			)
		);

		self::enqueue_assets( $provider );

		$html  = '<div class="mm-recaptcha-field mm-recaptcha-provider-' . esc_attr( $provider->id() ) . ' ' . esc_attr( $args['class'] ) . '" data-mm-surface="' . esc_attr( $surface ) . '">';
		$html .= $provider->render( $args );
		$html .= '<input type="hidden" name="mm_rc_token" class="mm-recaptcha-token-field" value="" autocomplete="off" />';
		$html .= '<input type="hidden" name="mm_rc_surface" value="' . esc_attr( $surface ) . '" />';
		$html .= self::extras_html( $surface );
		$html .= '</div>';

		/**
		 * Filtra l'HTML del captcha.
		 *
		 * @param string $html    HTML.
		 * @param string $surface Superficie.
		 * @param array  $args    Argomenti.
		 */
		return apply_filters( 'mm_recaptcha_field_html', $html, $surface, $args );
	}

	/**
	 * Stampa il campo captcha.
	 *
	 * @param string $surface Superficie.
	 * @param array  $args    Argomenti.
	 */
	public static function render_echo( $surface, array $args = array() ) {
		echo self::render( $surface, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML già validato e sfuggito nei provider.
	}

	/**
	 * Campi aggiuntivi: honeypot e trappola temporale.
	 *
	 * @param string $surface Superficie.
	 * @return string
	 */
	protected static function extras_html( $surface ) {
		$html = '';

		if ( Options::get( 'honeypot', 1 ) ) {
			$name  = self::honeypot_name();
			$html .= '<div class="mm-recaptcha-hp" aria-hidden="true">';
			$html .= '<label for="' . esc_attr( $name . '-' . self::$instances ) . '">' . esc_html__( 'Lascia vuoto questo campo', 'mm-recaptcha' ) . '</label>';
			$html .= '<input type="text" id="' . esc_attr( $name . '-' . self::$instances ) . '" name="' . esc_attr( $name ) . '" value="" tabindex="-1" autocomplete="off" />';
			$html .= '</div>';
		}

		if ( (int) Options::get( 'time_trap', 0 ) > 0 ) {
			$html .= '<input type="hidden" name="mm_rc_ts" value="' . esc_attr( self::sign_timestamp() ) . '" />';
		}

		return $html;
	}

	/**
	 * Nome del campo honeypot, stabile ma non indovinabile.
	 *
	 * @return string
	 */
	public static function honeypot_name() {
		return 'mm_' . substr( hash_hmac( 'sha256', 'honeypot', wp_salt( 'mm_recaptcha' ) ), 0, 10 );
	}

	/**
	 * Marca temporale firmata.
	 *
	 * @return string
	 */
	public static function sign_timestamp() {
		$time = time();

		return $time . '.' . substr( hash_hmac( 'sha256', (string) $time, wp_salt( 'mm_recaptcha' ) ), 0, 32 );
	}

	/**
	 * Verifica la marca temporale e restituisce l'età in secondi.
	 *
	 * @param string $value Valore inviato.
	 * @return int|false
	 */
	public static function read_timestamp( $value ) {
		if ( ! is_string( $value ) || false === strpos( $value, '.' ) ) {
			return false;
		}

		list( $time, $sig ) = explode( '.', $value, 2 );

		if ( ! ctype_digit( $time ) ) {
			return false;
		}

		$expected = substr( hash_hmac( 'sha256', $time, wp_salt( 'mm_recaptcha' ) ), 0, 32 );

		if ( ! hash_equals( $expected, (string) $sig ) ) {
			return false;
		}

		return max( 0, time() - (int) $time );
	}

	/**
	 * Registra e accoda gli asset necessari.
	 *
	 * @param Provider $provider Provider attivo.
	 */
	public static function enqueue_assets( Provider $provider ) {
		if ( self::$assets_queued ) {
			return;
		}

		self::$assets_queued = true;

		wp_register_style( 'mm-recaptcha', MM_RECAPTCHA_URL . 'assets/css/mm-recaptcha.css', array(), MM_RECAPTCHA_VERSION );
		wp_enqueue_style( 'mm-recaptcha' );

		if ( in_array( $provider->id(), array( 'recaptcha_v3', 'recaptcha_enterprise' ), true ) && $provider->config( 'hide_badge', 0 ) ) {
			wp_add_inline_style( 'mm-recaptcha', '.grecaptcha-badge{visibility:hidden!important}' );
		}

		wp_register_script( 'mm-recaptcha', MM_RECAPTCHA_URL . 'assets/js/mm-recaptcha.js', array(), MM_RECAPTCHA_VERSION, true );

		wp_localize_script(
			'mm-recaptcha',
			'mmRecaptchaSettings',
			array(
				'provider' => $provider->id(),
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'mm_recaptcha_public' ),
				'strings'  => array(
					'required' => __( 'Completa la verifica di sicurezza prima di inviare.', 'mm-recaptcha' ),
					'expired'  => __( 'La verifica è scaduta: ripetila.', 'mm-recaptcha' ),
					'error'    => __( 'Impossibile caricare la verifica di sicurezza. Ricarica la pagina.', 'mm-recaptcha' ),
				),
			)
		);

		wp_enqueue_script( 'mm-recaptcha' );

		$script_url = $provider->script_url();

		if ( '' !== $script_url ) {
			wp_register_script( 'mm-recaptcha-provider', $script_url, array( 'mm-recaptcha' ), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- versione gestita dal provider.
			wp_enqueue_script( 'mm-recaptcha-provider' );
			wp_script_add_data( 'mm-recaptcha-provider', 'strategy', 'defer' );
		}
	}

	/**
	 * Accoda gli asset in anticipo sulle schermate di wp-login.php,
	 * dove gli stili vanno dichiarati prima di login_head.
	 *
	 * @param string[] $surfaces Superfici della schermata.
	 */
	public static function preload_assets( array $surfaces ) {
		foreach ( $surfaces as $surface ) {
			if ( self::should_render( $surface ) ) {
				$provider = Provider_Registry::active();

				if ( $provider instanceof Provider ) {
					self::enqueue_assets( $provider );
				}

				return;
			}
		}
	}

	/**
	 * Conteggio delle istanze disegnate.
	 *
	 * @return int
	 */
	public static function instances() {
		return self::$instances;
	}
}
