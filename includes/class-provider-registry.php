<?php
/**
 * Registro dei provider captcha.
 *
 * @package MM_Recaptcha
 */

namespace MM_Recaptcha;

use MM_Recaptcha\Providers\Provider;
use MM_Recaptcha\Providers\Math;
use MM_Recaptcha\Providers\Recaptcha_V2;
use MM_Recaptcha\Providers\Recaptcha_V3;
use MM_Recaptcha\Providers\Recaptcha_Enterprise;
use MM_Recaptcha\Providers\HCaptcha;
use MM_Recaptcha\Providers\Turnstile;

defined( 'ABSPATH' ) || exit;

class Provider_Registry {

	/**
	 * Istanze dei provider.
	 *
	 * @var Provider[]|null
	 */
	protected static $providers = null;

	/**
	 * Tutti i provider disponibili.
	 *
	 * @return Provider[]
	 */
	public static function available() {
		if ( null !== self::$providers ) {
			return self::$providers;
		}

		$providers = array();

		foreach ( array( new Math(), new Recaptcha_V2(), new Recaptcha_V3(), new Recaptcha_Enterprise(), new HCaptcha(), new Turnstile() ) as $provider ) {
			$providers[ $provider->id() ] = $provider;
		}

		/**
		 * Permette di registrare provider aggiuntivi.
		 *
		 * @param Provider[] $providers Provider disponibili.
		 */
		$providers = apply_filters( 'mm_recaptcha_providers', $providers );

		self::$providers = $providers;

		return self::$providers;
	}

	/**
	 * Un provider per id.
	 *
	 * @param string $id Identificativo.
	 * @return Provider|null
	 */
	public static function get( $id ) {
		$providers = self::available();

		return isset( $providers[ $id ] ) ? $providers[ $id ] : null;
	}

	/**
	 * Provider attivo secondo le impostazioni.
	 *
	 * @return Provider|null
	 */
	public static function active() {
		$id = (string) Options::get( 'provider', 'math' );

		/**
		 * Consente di cambiare provider al volo (per esempio per superficie).
		 *
		 * @param string $id Identificativo del provider.
		 */
		$id = apply_filters( 'mm_recaptcha_active_provider', $id );

		return self::get( $id );
	}

	/**
	 * Il captcha è operativo (provider esistente e configurato)?
	 *
	 * @return bool
	 */
	public static function is_ready() {
		$provider = self::active();

		return $provider instanceof Provider && $provider->is_configured();
	}
}
