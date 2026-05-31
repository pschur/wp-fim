<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class FIM_Repair {

	const CHECKSUMS_TRANSIENT  = 'fim_official_checksums';
	const REPAIR_LOG_TRANSIENT = 'fim_repair_log';

	/**
	 * Fetch official MD5 checksums from WordPress.org for the current WP version.
	 * Results are cached for 1 hour.
	 */
	public static function get_official_checksums(): array {
		$cached = get_transient( self::CHECKSUMS_TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$version  = get_bloginfo( 'version' );
		$locale   = get_locale();

		$response = wp_remote_get(
			add_query_arg(
				[ 'version' => $version, 'locale' => $locale ],
				'https://api.wordpress.org/core/checksums/1.0/'
			),
			[ 'timeout' => 15, 'sslverify' => true ]
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return [];
		}

		$body      = json_decode( wp_remote_retrieve_body( $response ), true );
		$checksums = $body['checksums'] ?? [];

		if ( ! empty( $checksums ) ) {
			set_transient( self::CHECKSUMS_TRANSIENT, $checksums, HOUR_IN_SECONDS );
		}

		return $checksums;
	}

	/**
	 * Download a core file from the official WordPress SVN, verify its MD5 against the
	 * official checksum, then atomically replace the local copy and update the baseline.
	 *
	 * Returns true on success, false if the file cannot be repaired (no checksum available,
	 * download failed, or integrity check of downloaded content fails).
	 */
	public static function repair_core_file( string $abs_path ): bool {
		$relative = self::get_relative_path( $abs_path );
		if ( empty( $relative ) ) {
			return false;
		}

		$checksums = self::get_official_checksums();
		if ( ! isset( $checksums[ $relative ] ) ) {
			self::append_repair_log( $abs_path, false, __( 'No official checksum available', 'file-integrity-monitor' ) );
			return false;
		}

		$expected_md5 = $checksums[ $relative ];
		$version      = get_bloginfo( 'version' );
		$url          = 'https://core.svn.wordpress.org/tags/' . rawurlencode( $version ) . '/' . $relative;

		$response = wp_remote_get( $url, [
			'timeout'   => 25,
			'sslverify' => true,
		] );

		if ( is_wp_error( $response ) ) {
			/* translators: %s: error message */
			self::append_repair_log( $abs_path, false, sprintf( __( 'Download error: %s', 'file-integrity-monitor' ), $response->get_error_message() ) );
			return false;
		}

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			/* translators: %d: HTTP status code */
			self::append_repair_log( $abs_path, false, sprintf( __( 'HTTP error %d', 'file-integrity-monitor' ), wp_remote_retrieve_response_code( $response ) ) );
			return false;
		}

		$content = wp_remote_retrieve_body( $response );

		if ( md5( $content ) !== $expected_md5 ) {
			self::append_repair_log( $abs_path, false, __( 'Integrity check of downloaded file failed', 'file-integrity-monitor' ) );
			return false;
		}

		$tmp = $abs_path . '.fim-repair.' . getmypid();
		if ( file_put_contents( $tmp, $content ) === false ) {
			self::append_repair_log( $abs_path, false, __( 'Write error (temp file)', 'file-integrity-monitor' ) );
			return false;
		}

		if ( ! rename( $tmp, $abs_path ) ) {
			@unlink( $tmp );
			self::append_repair_log( $abs_path, false, __( 'Rename failed', 'file-integrity-monitor' ) );
			return false;
		}

		// Update only this file's hash in the baseline so future checks pass
		$baseline = FIM_Scanner::load_baseline();
		if ( isset( $baseline['core']['files'][ $abs_path ] ) ) {
			$baseline['core']['files'][ $abs_path ] = $expected_md5;
			FIM_Scanner::save_baseline( $baseline );
		}

		self::append_repair_log( $abs_path, true, 'WP ' . $version );
		return true;
	}

	/**
	 * Returns the path relative to ABSPATH, using forward slashes (as used by WP.org checksums).
	 */
	public static function get_relative_path( string $abs_path ): string {
		$abspath = rtrim( (string) realpath( ABSPATH ), DIRECTORY_SEPARATOR );
		$real    = realpath( $abs_path );

		if ( ! $real || ! str_starts_with( $real, $abspath . DIRECTORY_SEPARATOR ) ) {
			return '';
		}

		return str_replace(
			DIRECTORY_SEPARATOR,
			'/',
			ltrim( substr( $real, strlen( $abspath ) ), DIRECTORY_SEPARATOR )
		);
	}

	private static function append_repair_log( string $path, bool $success, string $note ): void {
		$log = get_transient( self::REPAIR_LOG_TRANSIENT );
		if ( ! is_array( $log ) ) {
			$log = [];
		}

		array_unshift( $log, [
			'time'    => gmdate( 'c' ),
			'path'    => $path,
			'success' => $success,
			'note'    => $note,
		] );

		set_transient( self::REPAIR_LOG_TRANSIENT, array_slice( $log, 0, 50 ), WEEK_IN_SECONDS );
	}

	public static function get_repair_log(): array {
		$log = get_transient( self::REPAIR_LOG_TRANSIENT );
		return is_array( $log ) ? $log : [];
	}
}
