<?php
/**
 * Disinstallazione.
 *
 * @package MM_Recaptcha
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$mm_rc_options = get_option( 'mm_recaptcha_settings', array() );

if ( ! is_array( $mm_rc_options ) || empty( $mm_rc_options['uninstall_delete'] ) ) {
	return;
}

delete_option( 'mm_recaptcha_settings' );
delete_option( 'mm_recaptcha_log' );
delete_option( 'mm_recaptcha_migrated' );
delete_option( 'mm_recaptcha_migration_notice' );
delete_option( 'mm_recaptcha_legacy_backup' );

global $wpdb;

// Sfide e token temporanei.
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_mm\_rc\_%' OR option_name LIKE '\_transient\_timeout\_mm\_rc\_%'"
);
