<?php
/**
 * Gestione delle opzioni del plugin.
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha;

defined( 'ABSPATH' ) || exit;

class Options {

	const KEY = 'mm_recaptcha_settings';

	/**
	 * Cache di richiesta.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * Valori predefiniti completi.
	 */
	public static function defaults() {
		return array(
			'provider'             => 'math',
			'recaptcha_v2'         => array(
				'site_key'   => '',
				'secret_key' => '',
				'mode'       => 'checkbox',
				'theme'      => 'light',
				'size'       => 'normal',
				'badge'      => 'bottomright',
				'language'   => '',
			),
			'recaptcha_v3'         => array(
				'site_key'      => '',
				'secret_key'    => '',
				'score'         => 0.5,
				'action'        => 'mm_recaptcha',
				'verify_action' => 1,
				'hide_badge'    => 0,
			),
			'recaptcha_enterprise' => array(
				'site_key'   => '',
				'project_id' => '',
				'api_key'    => '',
				'score'      => 0.5,
				'mode'       => 'score',
				'action'     => 'mm_recaptcha',
				'hide_badge' => 0,
			),
			'hcaptcha'             => array(
				'site_key'   => '',
				'secret_key' => '',
				'mode'       => 'normal',
				'theme'      => 'light',
				'size'       => 'normal',
			),
			'turnstile'            => array(
				'site_key'   => '',
				'secret_key' => '',
				'appearance' => 'always',
				'theme'      => 'auto',
				'size'       => 'normal',
			),
			'math'                 => array(
				// Volutamente non tradotta: i default vengono letti prima del caricamento delle traduzioni.
				'challenge_text' => 'Quanto fa:',
				'ttl'            => 600,
			),
			'honeypot'             => 1,
			'time_trap'            => 3,
			'forms'                => array(
				'wp_login'         => 1,
				'wp_register'      => 1,
				'wp_lostpassword'  => 1,
				'wp_comments'      => 1,
				'woo_login'        => 0,
				'woo_register'     => 0,
				'woo_lostpassword' => 0,
				'woo_checkout'     => 0,
				'woo_pay'          => 0,
				'cf7'              => 0,
				'cf7_auto_inject'  => 0,
				'custom'           => 1,
			),
			'skip_logged_in'       => 1,
			'skip_roles'           => array(),
			'ip_allowlist'         => array(),
			'verify_hostname'      => 0,
			'fail_open'            => 0,
			'log_failures'         => 1,
			'log_max'              => 100,
			'uninstall_delete'     => 0,
			'keys_tested'          => '',
			'version'              => MM_RECAPTCHA_VERSION,
		);
	}

	/**
	 * Tutte le opzioni, unite ai default.
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( self::KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		self::$cache = self::merge( self::defaults(), $stored );

		return self::$cache;
	}

	/**
	 * Unione ricorsiva a un solo livello di profondita'.
	 */
	protected static function merge( $defaults, $stored ) {
		$out = $defaults;

		foreach ( $defaults as $key => $default ) {
			if ( ! array_key_exists( $key, $stored ) ) {
				continue;
			}
			if ( is_array( $default ) && is_array( $stored[ $key ] ) ) {
				$out[ $key ] = array_merge( $default, $stored[ $key ] );
			} else {
				$out[ $key ] = $stored[ $key ];
			}
		}

		return $out;
	}

	/**
	 * Legge un'opzione con notazione puntata: "recaptcha_v3.score".
	 *
	 * @param string $path    Percorso.
	 * @param mixed  $default Valore di ripiego.
	 * @return mixed
	 */
	public static function get( $path, $default = null ) {
		$options = self::all();
		$parts   = explode( '.', $path );
		$value   = $options;

		foreach ( $parts as $part ) {
			if ( ! is_array( $value ) || ! array_key_exists( $part, $value ) ) {
				return $default;
			}
			$value = $value[ $part ];
		}

		return $value;
	}

	/**
	 * Salva le opzioni (gia' sanificate).
	 */
	public static function update( array $options ) {
		self::$cache = null;
		return update_option( self::KEY, $options );
	}

	/**
	 * Svuota la cache di richiesta.
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Registrazione impostazioni.
	 */
	public static function register() {
		register_setting(
			self::KEY,
			self::KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Sanificazione completa: whitelist rigorosa, chiavi sconosciute scartate.
	 *
	 * @param mixed $input Dati in ingresso.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$current = self::all();
		$out     = $current;

		if ( ! is_array( $input ) ) {
			return $out;
		}

		// Provider attivo.
		if ( isset( $input['provider'] ) ) {
			$valid = array_keys( Provider_Registry::available() );
			if ( in_array( $input['provider'], $valid, true ) ) {
				$out['provider'] = $input['provider'];
			} else {
				add_settings_error( self::KEY, 'mm_rc_provider', __( 'Provider captcha non valido: selezione ignorata.', 'mm-recaptcha' ) );
			}
		}

		// reCAPTCHA v2.
		if ( isset( $input['recaptcha_v2'] ) && is_array( $input['recaptcha_v2'] ) ) {
			$v2                        = $input['recaptcha_v2'];
			$out['recaptcha_v2']['site_key']   = self::key_field( $v2, 'site_key' );
			$out['recaptcha_v2']['secret_key'] = self::key_field( $v2, 'secret_key' );
			$out['recaptcha_v2']['mode']       = self::whitelist( $v2, 'mode', array( 'checkbox', 'invisible' ), 'checkbox' );
			$out['recaptcha_v2']['theme']      = self::whitelist( $v2, 'theme', array( 'light', 'dark' ), 'light' );
			$out['recaptcha_v2']['size']       = self::whitelist( $v2, 'size', array( 'normal', 'compact' ), 'normal' );
			$out['recaptcha_v2']['badge']      = self::whitelist( $v2, 'badge', array( 'bottomright', 'bottomleft', 'inline' ), 'bottomright' );
			$out['recaptcha_v2']['language']   = isset( $v2['language'] ) ? preg_replace( '/[^a-zA-Z-]/', '', (string) $v2['language'] ) : '';
		}

		// reCAPTCHA v3.
		if ( isset( $input['recaptcha_v3'] ) && is_array( $input['recaptcha_v3'] ) ) {
			$v3                                 = $input['recaptcha_v3'];
			$out['recaptcha_v3']['site_key']      = self::key_field( $v3, 'site_key' );
			$out['recaptcha_v3']['secret_key']    = self::key_field( $v3, 'secret_key' );
			$out['recaptcha_v3']['score']         = self::score( $v3, 'score' );
			$out['recaptcha_v3']['action']        = self::action_name( $v3, 'action' );
			$out['recaptcha_v3']['verify_action'] = self::boolean( $v3, 'verify_action' );
			$out['recaptcha_v3']['hide_badge']    = self::boolean( $v3, 'hide_badge' );
		}

		// reCAPTCHA Enterprise.
		if ( isset( $input['recaptcha_enterprise'] ) && is_array( $input['recaptcha_enterprise'] ) ) {
			$ent                                       = $input['recaptcha_enterprise'];
			$out['recaptcha_enterprise']['site_key']   = self::key_field( $ent, 'site_key' );
			$out['recaptcha_enterprise']['project_id'] = isset( $ent['project_id'] ) ? preg_replace( '/[^a-zA-Z0-9-]/', '', (string) $ent['project_id'] ) : '';
			$out['recaptcha_enterprise']['api_key']    = self::key_field( $ent, 'api_key' );
			$out['recaptcha_enterprise']['score']      = self::score( $ent, 'score' );
			$out['recaptcha_enterprise']['mode']       = self::whitelist( $ent, 'mode', array( 'score', 'checkbox' ), 'score' );
			$out['recaptcha_enterprise']['action']     = self::action_name( $ent, 'action' );
			$out['recaptcha_enterprise']['hide_badge'] = self::boolean( $ent, 'hide_badge' );
		}

		// hCaptcha.
		if ( isset( $input['hcaptcha'] ) && is_array( $input['hcaptcha'] ) ) {
			$hc                            = $input['hcaptcha'];
			$out['hcaptcha']['site_key']   = self::key_field( $hc, 'site_key' );
			$out['hcaptcha']['secret_key'] = self::key_field( $hc, 'secret_key' );
			$out['hcaptcha']['mode']       = self::whitelist( $hc, 'mode', array( 'normal', 'invisible' ), 'normal' );
			$out['hcaptcha']['theme']      = self::whitelist( $hc, 'theme', array( 'light', 'dark' ), 'light' );
			$out['hcaptcha']['size']       = self::whitelist( $hc, 'size', array( 'normal', 'compact' ), 'normal' );
		}

		// Turnstile.
		if ( isset( $input['turnstile'] ) && is_array( $input['turnstile'] ) ) {
			$ts                             = $input['turnstile'];
			$out['turnstile']['site_key']   = self::key_field( $ts, 'site_key' );
			$out['turnstile']['secret_key'] = self::key_field( $ts, 'secret_key' );
			$out['turnstile']['appearance'] = self::whitelist( $ts, 'appearance', array( 'always', 'execute', 'interaction-only' ), 'always' );
			$out['turnstile']['theme']      = self::whitelist( $ts, 'theme', array( 'auto', 'light', 'dark' ), 'auto' );
			$out['turnstile']['size']       = self::whitelist( $ts, 'size', array( 'normal', 'flexible', 'compact' ), 'normal' );
		}

		// Captcha matematico.
		if ( isset( $input['math'] ) && is_array( $input['math'] ) ) {
			$math                           = $input['math'];
			$out['math']['challenge_text']  = isset( $math['challenge_text'] ) ? sanitize_text_field( $math['challenge_text'] ) : $current['math']['challenge_text'];
			$ttl                            = isset( $math['ttl'] ) ? absint( $math['ttl'] ) : 600;
			$out['math']['ttl']             = max( 60, min( 3600, $ttl ) );
		}

		$out['honeypot']  = self::boolean( $input, 'honeypot' );
		$out['time_trap'] = isset( $input['time_trap'] ) ? min( 60, absint( $input['time_trap'] ) ) : 0;

		// Form protetti.
		$out['forms'] = array();
		foreach ( array_keys( $current['forms'] ) as $form_key ) {
			$out['forms'][ $form_key ] = ( isset( $input['forms'][ $form_key ] ) && $input['forms'][ $form_key ] ) ? 1 : 0;
		}

		$out['skip_logged_in']   = self::boolean( $input, 'skip_logged_in' );
		$out['verify_hostname']  = self::boolean( $input, 'verify_hostname' );
		$out['fail_open']        = self::boolean( $input, 'fail_open' );
		$out['log_failures']     = self::boolean( $input, 'log_failures' );
		$out['uninstall_delete'] = self::boolean( $input, 'uninstall_delete' );
		$out['log_max']          = isset( $input['log_max'] ) ? max( 10, min( 1000, absint( $input['log_max'] ) ) ) : 100;

		// Ruoli esclusi.
		$out['skip_roles'] = array();
		if ( isset( $input['skip_roles'] ) && is_array( $input['skip_roles'] ) ) {
			$existing = array_keys( wp_roles()->roles );
			foreach ( $input['skip_roles'] as $role ) {
				$role = sanitize_key( $role );
				if ( in_array( $role, $existing, true ) ) {
					$out['skip_roles'][] = $role;
				}
			}
		}

		// Lista IP consentiti (una per riga oppure array).
		$out['ip_allowlist'] = array();
		$raw_ips             = isset( $input['ip_allowlist'] ) ? $input['ip_allowlist'] : array();
		if ( is_string( $raw_ips ) ) {
			$raw_ips = preg_split( '/[\r\n,]+/', $raw_ips );
		}
		if ( is_array( $raw_ips ) ) {
			$skipped = 0;
			foreach ( $raw_ips as $ip ) {
				$ip = trim( (string) $ip );
				if ( '' === $ip ) {
					continue;
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					$out['ip_allowlist'][] = $ip;
				} else {
					$skipped++;
				}
			}
			if ( $skipped > 0 ) {
				add_settings_error(
					self::KEY,
					'mm_rc_ips',
					sprintf(
						/* translators: %d: numero di indirizzi IP scartati. */
						_n( '%d indirizzo IP non valido è stato ignorato.', '%d indirizzi IP non validi sono stati ignorati.', $skipped, 'mm-recaptcha' ),
						$skipped
					)
				);
			}
		}

		$out['keys_tested'] = isset( $input['keys_tested'] ) ? sanitize_text_field( $input['keys_tested'] ) : $current['keys_tested'];
		$out['version']     = MM_RECAPTCHA_VERSION;

		// Avviso se il provider scelto non ha le chiavi necessarie.
		$provider = Provider_Registry::get( $out['provider'] );
		if ( $provider && ! $provider->is_configured( $out ) ) {
			add_settings_error(
				self::KEY,
				'mm_rc_unconfigured',
				sprintf(
					/* translators: %s: nome del provider captcha. */
					__( 'Attenzione: mancano una o più chiavi per %s. Il captcha resta inattivo finché non le inserisci.', 'mm-recaptcha' ),
					$provider->label()
				),
				'warning'
			);
		}

		self::$cache = null;

		return $out;
	}

	protected static function key_field( $arr, $key ) {
		return isset( $arr[ $key ] ) ? trim( sanitize_text_field( (string) $arr[ $key ] ) ) : '';
	}

	protected static function whitelist( $arr, $key, $allowed, $fallback ) {
		if ( isset( $arr[ $key ] ) && in_array( $arr[ $key ], $allowed, true ) ) {
			return $arr[ $key ];
		}
		return $fallback;
	}

	protected static function score( $arr, $key ) {
		$value = isset( $arr[ $key ] ) ? (float) $arr[ $key ] : 0.5;
		return max( 0.0, min( 1.0, round( $value, 2 ) ) );
	}

	protected static function action_name( $arr, $key ) {
		$value = isset( $arr[ $key ] ) ? preg_replace( '/[^a-zA-Z0-9_\/]/', '', (string) $arr[ $key ] ) : '';
		return '' === $value ? 'mm_recaptcha' : $value;
	}

	protected static function boolean( $arr, $key ) {
		return ( isset( $arr[ $key ] ) && $arr[ $key ] ) ? 1 : 0;
	}
}
