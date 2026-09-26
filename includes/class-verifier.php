<?php
/**
 * Verifica unificata del captcha.
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha;

use MM_Recaptcha\Providers\Provider;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class Verifier {

	/**
	 * Risultati già calcolati in questa richiesta, per evitare doppie verifiche
	 * (WooCommerce ripropone gli hook del core: senza memo il secondo controllo
	 * fallirebbe come "token già usato").
	 *
	 * @var array
	 */
	protected static $memo = array();

	/**
	 * Il captcha va applicato a questa superficie?
	 *
	 * @param string $surface Superficie.
	 * @return bool
	 */
	public static function should_protect( $surface ) {
		if ( ! Provider_Registry::is_ready() ) {
			return false;
		}

		$forms = (array) Options::get( 'forms', array() );
		$key   = self::form_key( $surface );

		if ( '' !== $key && empty( $forms[ $key ] ) ) {
			return false;
		}

		if ( self::is_skipped_actor( $surface ) ) {
			return false;
		}

		/**
		 * Consente di forzare o disattivare la protezione per superficie.
		 *
		 * @param bool   $protect Esito.
		 * @param string $surface Superficie.
		 */
		return (bool) apply_filters( 'mm_recaptcha_should_protect', true, $surface );
	}

	/**
	 * Mappa superficie -> chiave dell'opzione "forms".
	 *
	 * @param string $surface Superficie.
	 * @return string
	 */
	protected static function form_key( $surface ) {
		$map = array(
			'wp_login'          => 'wp_login',
			'wp_register'       => 'wp_register',
			'wp_lostpassword'   => 'wp_lostpassword',
			'wp_resetpassword'  => 'wp_lostpassword',
			'wp_comments'       => 'wp_comments',
			'wc_login'          => 'woo_login',
			'wc_register'       => 'woo_register',
			'wc_lostpassword'   => 'woo_lostpassword',
			'wc_resetpassword'  => 'woo_lostpassword',
			'wc_checkout'       => 'woo_checkout',
			'wc_pay'            => 'woo_pay',
			'cf7'               => 'cf7',
			'divi'              => 'divi',
			'custom'            => 'custom',
		);

		return isset( $map[ $surface ] ) ? $map[ $surface ] : '';
	}

	/**
	 * Contesti in cui il captcha non ha senso o creerebbe danni.
	 *
	 * @param string $surface Superficie da verificare.
	 * @return bool
	 */
	public static function is_skipped_actor( $surface = '' ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		if ( wp_doing_cron() ) {
			return true;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return true;
		}

		// Contact Form 7 invia i moduli tramite REST: qui la verifica deve girare.
		$rest_surfaces = array( 'cf7', 'custom' );

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! in_array( $surface, $rest_surfaces, true ) ) {
			return true;
		}

		if ( is_admin() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return true;
		}

		if ( is_user_logged_in() ) {
			if ( Options::get( 'skip_logged_in', 1 ) ) {
				return true;
			}

			$skip_roles = (array) Options::get( 'skip_roles', array() );

			if ( $skip_roles ) {
				$user = wp_get_current_user();

				if ( array_intersect( $skip_roles, (array) $user->roles ) ) {
					return true;
				}
			}
		}

		$allowlist = (array) Options::get( 'ip_allowlist', array() );

		if ( $allowlist ) {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

			if ( '' !== $ip && in_array( $ip, $allowlist, true ) ) {
				return true;
			}
		}

		/**
		 * Consente di escludere altri contesti dalla verifica.
		 *
		 * @param bool   $skip    Esito.
		 * @param string $surface Superficie.
		 */
		return (bool) apply_filters( 'mm_recaptcha_skip_actor', false, $surface );
	}

	/**
	 * Verifica la richiesta corrente.
	 *
	 * @param string     $surface Superficie.
	 * @param array|null $post    Dati POST alternativi.
	 * @return true|WP_Error
	 */
	public static function verify( $surface, $post = null ) {
		if ( ! self::should_protect( $surface ) ) {
			return true;
		}

		$post = is_array( $post ) ? $post : self::post_data();
		$memo = self::memo_key( $surface, $post );

		if ( isset( self::$memo[ $memo ] ) ) {
			return self::$memo[ $memo ];
		}

		$result = self::run( $surface, $post );

		self::$memo[ $memo ] = $result;

		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$detail = isset( $data['detail'] ) ? $data['detail'] : '';

			Log::add( $surface, $result->get_error_code(), $detail ? $detail : $result->get_error_message() );

			/**
			 * Notifica di verifica fallita.
			 *
			 * @param string   $surface Superficie.
			 * @param WP_Error $result  Errore.
			 */
			do_action( 'mm_recaptcha_verification_failed', $surface, $result );
		}

		/**
		 * Filtra il risultato finale della verifica.
		 *
		 * @param true|WP_Error $result  Esito.
		 * @param string        $surface Superficie.
		 */
		return apply_filters( 'mm_recaptcha_verify_result', $result, $surface );
	}

	/**
	 * Esegue la catena di controlli.
	 *
	 * @param string $surface Superficie.
	 * @param array  $post    Dati POST.
	 * @return true|WP_Error
	 */
	protected static function run( $surface, array $post ) {
		$extras = self::check_extras( $surface, $post );

		if ( is_wp_error( $extras ) ) {
			return $extras;
		}

		$provider = Provider_Registry::active();

		if ( ! $provider instanceof Provider ) {
			return true;
		}

		$token = self::token( $provider, $post );

		$result = $provider->verify(
			$token,
			array(
				'surface'  => $surface,
				'post'     => $post,
				'remoteip' => self::remote_ip(),
			)
		);

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();

			if ( ! empty( $data['transport'] ) && Options::get( 'fail_open', 0 ) ) {
				Log::add( $surface, 'mm_rc_fail_open', isset( $data['detail'] ) ? $data['detail'] : '' );

				return true;
			}

			return $result;
		}

		return true;
	}

	/**
	 * Controlli honeypot e trappola temporale.
	 *
	 * @param string $surface Superficie.
	 * @param array  $post    Dati POST.
	 * @return true|WP_Error
	 */
	protected static function check_extras( $surface, array $post ) {
		if ( Options::get( 'honeypot', 1 ) ) {
			$name = Renderer::honeypot_name();

			if ( isset( $post[ $name ] ) && '' !== trim( (string) ( is_array( $post[ $name ] ) ? '' : $post[ $name ] ) ) ) {
				return new WP_Error(
					'mm_rc_honeypot',
					__( 'Invio bloccato dal controllo antispam.', 'mm-recaptcha' ),
					array( 'detail' => 'honeypot compilato' )
				);
			}
		}

		$min = (int) Options::get( 'time_trap', 0 );

		if ( $min > 0 && isset( $post['mm_rc_ts'] ) && ! is_array( $post['mm_rc_ts'] ) ) {
			$age = Renderer::read_timestamp( (string) $post['mm_rc_ts'] );

			if ( false !== $age && $age < $min ) {
				return new WP_Error(
					'mm_rc_timetrap',
					__( 'Invio troppo rapido: attendi qualche secondo e riprova.', 'mm-recaptcha' ),
					array( 'detail' => sprintf( 'inviato dopo %ds (minimo %ds)', $age, $min ) )
				);
			}
		}

		return true;
	}

	/**
	 * Token inviato dal client.
	 *
	 * @param Provider $provider Provider.
	 * @param array    $post     Dati POST.
	 * @return string
	 */
	protected static function token( Provider $provider, array $post ) {
		$candidates = array( 'mm_rc_token', $provider->response_field() );

		foreach ( $candidates as $field ) {
			if ( isset( $post[ $field ] ) && ! is_array( $post[ $field ] ) && '' !== trim( (string) $post[ $field ] ) ) {
				return trim( (string) $post[ $field ] );
			}
		}

		return '';
	}

	/**
	 * Dati POST ripuliti dalle barre di WordPress.
	 *
	 * @return array
	 */
	public static function post_data() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- i dati captcha viaggiano nei moduli protetti da nonce propri.
		return is_array( $_POST ) ? wp_unslash( $_POST ) : array();
	}

	/**
	 * Chiave di memoizzazione basata sui dati del captcha.
	 *
	 * @param string $surface Superficie.
	 * @param array  $post    Dati POST.
	 * @return string
	 */
	protected static function memo_key( $surface, array $post ) {
		$parts = array();

		foreach ( array( 'mm_rc_token', 'mm_rc_answer', 'mm_rc_cid', 'g-recaptcha-response', 'h-captcha-response', 'cf-turnstile-response' ) as $field ) {
			$parts[] = isset( $post[ $field ] ) && ! is_array( $post[ $field ] ) ? (string) $post[ $field ] : '';
		}

		// Superfici distinte che condividono lo stesso invio devono condividere il risultato.
		return hash( 'sha256', implode( '|', $parts ) );
	}

	/**
	 * Superficie dichiarata dal modulo inviato.
	 *
	 * @return string
	 */
	public static function submitted_surface() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- semplice marcatore di provenienza.
		return isset( $_POST['mm_rc_surface'] ) && ! is_array( $_POST['mm_rc_surface'] )
			? sanitize_key( wp_unslash( $_POST['mm_rc_surface'] ) )
			: '';
	}

	/**
	 * IP del client per la verifica remota (solo REMOTE_ADDR, non falsificabile a valle).
	 *
	 * @return string
	 */
	public static function remote_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Aggiunge l'eventuale errore a un oggetto WP_Error esistente.
	 *
	 * @param WP_Error $errors  Contenitore errori.
	 * @param string   $surface Superficie.
	 * @return WP_Error
	 */
	public static function add_to_errors( $errors, $surface ) {
		$result = self::verify( $surface );

		if ( is_wp_error( $result ) ) {
			if ( ! is_wp_error( $errors ) ) {
				$errors = new WP_Error();
			}

			$errors->add( $result->get_error_code(), self::message( $result ) );
		}

		return $errors;
	}

	/**
	 * Messaggio da mostrare all'utente.
	 *
	 * @param WP_Error $error Errore.
	 * @return string
	 */
	public static function message( $error ) {
		$message = $error->get_error_message();

		/**
		 * Filtra il messaggio mostrato all'utente.
		 *
		 * @param string   $message Messaggio.
		 * @param WP_Error $error   Errore completo.
		 */
		return (string) apply_filters( 'mm_recaptcha_error_message', $message, $error );
	}
}
