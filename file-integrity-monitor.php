<?php
/**
 * Plugin Name: File Integrity Monitor
 * Plugin URI: https://github.com/pschur/wp-fim
 * Description: Monitors WordPress core, plugins, and themes for unexpected file changes using MD5 hashes. Optionally blocks requests on core file tampering.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Paul Schur
 * License: Personal Use License
 * Text Domain: file-integrity-monitor
 * 
 * @package file-integrity-monitor
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'FIM_VERSION',     '1.0.0' );
define( 'FIM_PLUGIN_FILE', __FILE__ );
define( 'FIM_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'FIM_DATA_DIR',    FIM_PLUGIN_DIR . 'data' );

spl_autoload_register( function ( string $class ): void {
	if ( str_starts_with( $class, 'FIM_' ) ) {
		$file = FIM_PLUGIN_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
} );

add_action( 'init', function (): void {
	load_plugin_textdomain(
		'file-integrity-monitor',
		false,
		dirname( plugin_basename( FIM_PLUGIN_FILE ) ) . '/languages/'
	);
} );

// Real-time integrity checks on every request
add_action( 'plugins_loaded', [ 'FIM_Checker', 'check' ],        1 );
add_action( 'wp_loaded',      [ 'FIM_Checker', 'check_themes' ], 1 );

// Admin UI
if ( is_admin() ) {
	add_action( 'plugins_loaded', [ 'FIM_Admin', 'init' ] );
}

// Auto-update baseline after WP/plugin/theme updates
add_action( 'upgrader_process_complete', function ( $upgrader, array $options ): void {
	if ( $options['action'] === 'update' ) {
		if ( $options['type'] === 'core' ) {
			FIM_Scanner::scan_core();
		} elseif ( $options['type'] === 'plugin' && ! empty( $options['plugins'] ) ) {
			foreach ( $options['plugins'] as $plugin_file ) {
				$slug = dirname( $plugin_file );
				if ( $slug && $slug !== '.' ) {
					FIM_Scanner::scan_plugin( $slug );
				}
			}
		} elseif ( $options['type'] === 'theme' && ! empty( $options['themes'] ) ) {
			foreach ( $options['themes'] as $slug ) {
				FIM_Scanner::scan_theme( $slug );
			}
		}
	}
}, 10, 2 );

// Auto-update baseline after theme switch
add_action( 'switch_theme', function ( string $new_name, WP_Theme $new_theme ): void {
	FIM_Scanner::scan_theme( $new_theme->get_stylesheet() );
}, 10, 2 );

// Auto-update baseline after plugin activation
add_action( 'activated_plugin', function ( string $plugin_file ): void {
	$slug = dirname( $plugin_file );
	if ( $slug && $slug !== '.' ) {
		FIM_Scanner::scan_plugin( $slug );
	}
} );

// On plugin activation: create initial baseline for all areas
register_activation_hook( __FILE__, function (): void {
	FIM_Scanner::scan_all();
} );
