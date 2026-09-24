<?php
/**
 * Plugin Name:       MM reCAPTCHA
 * Plugin URI:        https://inalto.com/
 * Description:       Protezione captcha completa per WordPress, WooCommerce e Contact Form 7. Supporta Google reCAPTCHA v2 (checkbox e invisibile), reCAPTCHA v3, reCAPTCHA Enterprise, hCaptcha, Cloudflare Turnstile e un captcha matematico interno. Nessuna funzione a pagamento, nessuna limitazione.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Inalto
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       mm-recaptcha
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'MM_RECAPTCHA_VERSION', '1.0.0' );
define( 'MM_RECAPTCHA_FILE', __FILE__ );
define( 'MM_RECAPTCHA_DIR', plugin_dir_path( __FILE__ ) );
define( 'MM_RECAPTCHA_URL', plugin_dir_url( __FILE__ ) );
define( 'MM_RECAPTCHA_BASENAME', plugin_basename( __FILE__ ) );

require_once MM_RECAPTCHA_DIR . 'includes/class-options.php';
require_once MM_RECAPTCHA_DIR . 'includes/class-challenge-store.php';
require_once MM_RECAPTCHA_DIR . 'includes/class-log.php';
require_once MM_RECAPTCHA_DIR . 'includes/providers/abstract-provider.php';
require_once MM_RECAPTCHA_DIR . 'includes/providers/class-recaptcha-v2.php';
require_once MM_RECAPTCHA_DIR . 'includes/providers/class-recaptcha-v3.php';
require_once MM_RECAPTCHA_DIR . 'includes/providers/class-recaptcha-enterprise.php';
require_once MM_RECAPTCHA_DIR . 'includes/providers/class-hcaptcha.php';
require_once MM_RECAPTCHA_DIR . 'includes/providers/class-turnstile.php';
require_once MM_RECAPTCHA_DIR . 'includes/providers/class-math.php';
require_once MM_RECAPTCHA_DIR . 'includes/class-provider-registry.php';
require_once MM_RECAPTCHA_DIR . 'includes/class-renderer.php';
require_once MM_RECAPTCHA_DIR . 'includes/class-verifier.php';
require_once MM_RECAPTCHA_DIR . 'includes/class-migrator.php';
require_once MM_RECAPTCHA_DIR . 'includes/integrations/abstract-integration.php';
require_once MM_RECAPTCHA_DIR . 'includes/integrations/class-integration-wp.php';
require_once MM_RECAPTCHA_DIR . 'includes/integrations/class-integration-woocommerce.php';
require_once MM_RECAPTCHA_DIR . 'includes/integrations/class-integration-cf7.php';
require_once MM_RECAPTCHA_DIR . 'includes/api.php';
require_once MM_RECAPTCHA_DIR . 'includes/class-plugin.php';

if ( is_admin() ) {
	require_once MM_RECAPTCHA_DIR . 'admin/class-settings.php';
	require_once MM_RECAPTCHA_DIR . 'admin/class-ajax.php';
	require_once MM_RECAPTCHA_DIR . 'admin/class-admin.php';
}

register_activation_hook( __FILE__, array( 'MM_Recaptcha\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MM_Recaptcha\\Plugin', 'deactivate' ) );

MM_Recaptcha\Plugin::instance()->boot();
