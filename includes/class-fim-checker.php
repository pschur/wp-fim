<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class FIM_Checker {

	private static array $checked = [];

	public static function check(): void {
		$baseline = FIM_Scanner::load_baseline();

		if ( empty( $baseline ) ) {
			return;
		}

		$options          = get_option( 'fim_settings', [] );
		$excluded_plugins = $options['excluded_plugins'] ?? [];

		$core_files   = $baseline['core']['files'] ?? [];
		$mu_files     = $baseline['mu-plugins']['files'] ?? [];
		$plugin_files = [];

		foreach ( $baseline['plugins'] ?? [] as $slug => $data ) {
			if ( ! in_array( $slug, $excluded_plugins, true ) ) {
				$plugin_files = array_merge( $plugin_files, $data['files'] ?? [] );
			}
		}

		$all_baseline = array_merge( $core_files, $mu_files, $plugin_files );

		if ( empty( $all_baseline ) ) {
			return;
		}

		$core_dir  = realpath( ABSPATH . 'wp-includes' );
		$admin_dir = realpath( ABSPATH . 'wp-admin' );
		$abspath   = realpath( ABSPATH );

		foreach ( get_included_files() as $file ) {
			$real = realpath( $file );
			if ( ! $real || isset( self::$checked[ $real ] ) ) {
				continue;
			}

			if ( ! array_key_exists( $real, $all_baseline ) ) {
				self::$checked[ $real ] = true;
				continue;
			}

			self::$checked[ $real ] = true;
			$expected = $all_baseline[ $real ];
			$actual   = md5_file( $real );

			if ( $actual === $expected ) {
				continue;
			}

			$is_core = (
				str_starts_with( $real, $core_dir )  ||
				str_starts_with( $real, $admin_dir ) ||
				( str_starts_with( $real, $abspath ) && substr_count( $real, DIRECTORY_SEPARATOR ) === substr_count( $abspath, DIRECTORY_SEPARATOR ) + 1 )
			);

			FIM_Alerts::trigger( $real, $expected, $actual, $is_core );

			if ( $is_core ) {
				$repaired = false;

				if ( ! empty( $options['auto_repair'] ) ) {
					$repaired = FIM_Repair::repair_core_file( $real );
				}

				$should_block = isset( $options['block_requests'] ) ? (bool) $options['block_requests'] : true;

				if ( ! $repaired && $should_block ) {
					self::block_request( $real );
				}
			}
		}
	}

	public static function check_themes(): void {
		$baseline = FIM_Scanner::load_baseline();

		if ( empty( $baseline['themes'] ) ) {
			return;
		}

		$options         = get_option( 'fim_settings', [] );
		$excluded_themes = $options['excluded_themes'] ?? [];

		$theme_files = [];
		foreach ( $baseline['themes'] as $slug => $data ) {
			if ( ! in_array( $slug, $excluded_themes, true ) ) {
				$theme_files = array_merge( $theme_files, $data['files'] ?? [] );
			}
		}

		if ( empty( $theme_files ) ) {
			return;
		}

		foreach ( get_included_files() as $file ) {
			$real = realpath( $file );
			if ( ! $real || isset( self::$checked[ $real ] ) ) {
				continue;
			}

			if ( ! array_key_exists( $real, $theme_files ) ) {
				self::$checked[ $real ] = true;
				continue;
			}

			self::$checked[ $real ] = true;
			$expected = $theme_files[ $real ];
			$actual   = md5_file( $real );

			if ( $actual !== $expected ) {
				FIM_Alerts::trigger( $real, $expected, $actual, false );
			}
		}
	}

	private static function block_request( string $path ): void {
		wp_die(
			__( '<h1>Security Error</h1><p>A critical system file has been unexpectedly modified. Access has been blocked for security reasons.</p><p>Please contact your administrator.</p>', 'file-integrity-monitor' ),
			__( 'Integrity Error — Access Denied', 'file-integrity-monitor' ),
			[ 'response' => 500, 'back_link' => false ]
		);
	}
}
