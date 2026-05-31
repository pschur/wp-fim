<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class FIM_Alerts {

	const VIOLATIONS_TRANSIENT = 'fim_violations';
	const LOG_FILE             = WP_CONTENT_DIR . '/fim-integrity.log';
	const MAX_VIOLATIONS       = 100;
	const EMAIL_COOLDOWN       = 900;

	public static function trigger( string $path, string $expected, string $actual, bool $is_core = false ): void {
		$now = gmdate( 'c' );

		$violation = [
			'time'     => $now,
			'path'     => $path,
			'expected' => $expected,
			'actual'   => $actual,
			'is_core'  => $is_core,
		];

		self::store_violation( $violation );
		self::write_log( $violation );
		self::maybe_send_email( $violation );
	}

	private static function store_violation( array $violation ): void {
		$violations = get_transient( self::VIOLATIONS_TRANSIENT );

		if ( ! is_array( $violations ) ) {
			$violations = [];
		}

		// Migrate from old list-indexed format (pre-dedup) to path-keyed format
		if ( ! empty( $violations ) && array_is_list( $violations ) ) {
			$migrated = [];
			foreach ( $violations as $v ) {
				$p = $v['path'];
				if ( ! isset( $migrated[ $p ] ) ) {
					$migrated[ $p ] = [
						'path'       => $p,
						'expected'   => $v['expected'],
						'actual'     => $v['actual'],
						'is_core'    => $v['is_core'],
						'count'      => 1,
						'first_seen' => $v['time'],
						'last_seen'  => $v['time'],
					];
				} else {
					$migrated[ $p ]['count']++;
					if ( $v['time'] > $migrated[ $p ]['last_seen'] ) {
						$migrated[ $p ]['last_seen'] = $v['time'];
					}
				}
			}
			$violations = $migrated;
		}

		$path = $violation['path'];
		$now  = $violation['time'];

		if ( isset( $violations[ $path ] ) ) {
			$violations[ $path ]['count']++;
			$violations[ $path ]['last_seen'] = $now;
			$violations[ $path ]['actual']    = $violation['actual'];
		} else {
			// Enforce max unique files
			if ( count( $violations ) >= self::MAX_VIOLATIONS ) {
				// Drop the entry that was last seen longest ago
				uasort( $violations, fn( $a, $b ) => strcmp( $a['last_seen'], $b['last_seen'] ) );
				reset( $violations );
				unset( $violations[ key( $violations ) ] );
			}

			$violations[ $path ] = [
				'path'       => $path,
				'expected'   => $violation['expected'],
				'actual'     => $violation['actual'],
				'is_core'    => $violation['is_core'],
				'count'      => 1,
				'first_seen' => $now,
				'last_seen'  => $now,
			];
		}

		// Sort by last_seen descending so newest appear first
		uasort( $violations, fn( $a, $b ) => strcmp( $b['last_seen'], $a['last_seen'] ) );

		set_transient( self::VIOLATIONS_TRANSIENT, $violations, DAY_IN_SECONDS );
	}

	private static function write_log( array $v ): void {
		$line = sprintf(
			"[%s] %s CHANGED — expected: %s actual: %s\n",
			$v['time'],
			$v['path'],
			$v['expected'],
			$v['actual']
		);

		file_put_contents( self::LOG_FILE, $line, FILE_APPEND | LOCK_EX );
	}

	private static function maybe_send_email( array $v ): void {
		$options = get_option( 'fim_settings', [] );

		if ( empty( $options['email_enabled'] ) ) {
			return;
		}

		$cooldown_key = 'fim_email_cooldown_' . md5( $v['path'] );
		if ( get_transient( $cooldown_key ) ) {
			return;
		}

		set_transient( $cooldown_key, 1, self::EMAIL_COOLDOWN );

		$to      = ! empty( $options['email_address'] ) ? $options['email_address'] : get_option( 'admin_email' );
		/* translators: 1: site name, 2: file basename */
		$subject = sprintf( __( '[%1$s] File Integrity Alert: %2$s', 'file-integrity-monitor' ), get_bloginfo( 'name' ), basename( $v['path'] ) );
		$message = sprintf(
			/* translators: 1: file path, 2: expected hash, 3: actual hash, 4: timestamp, 5: optional critical note, 6: admin URL */
			__( "A file modification was detected on your WordPress installation.\n\nFile:     %1\$s\nExpected: %2\$s\nActual:   %3\$s\nTime:     %4\$s\n\n%5\$sPlease review your installation immediately.\n%6\$s", 'file-integrity-monitor' ),
			$v['path'],
			$v['expected'],
			$v['actual'],
			$v['time'],
			$v['is_core'] ? __( 'CRITICAL: WordPress core file affected.', 'file-integrity-monitor' ) . "\n\n" : "\n",
			admin_url( 'tools.php?page=file-integrity' )
		);

		wp_mail( $to, $subject, $message );
	}

	public static function get_violations(): array {
		$violations = get_transient( self::VIOLATIONS_TRANSIENT );
		return is_array( $violations ) ? $violations : [];
	}

	public static function clear_violations(): void {
		delete_transient( self::VIOLATIONS_TRANSIENT );
	}

	public static function show_admin_notices(): void {
		$violations = self::get_violations();
		if ( empty( $violations ) ) {
			return;
		}

		$unique   = count( $violations );
		$total    = array_sum( array_column( $violations, 'count' ) );
		$has_core = (bool) array_filter( $violations, fn( $v ) => $v['is_core'] );

		$class   = $has_core ? 'notice-error' : 'notice-warning';
		$message = $has_core
			/* translators: 1: unique file count, 2: total detection count */
			? sprintf( __( 'File Integrity Monitor: <strong>%1$d critical file(s)</strong> modified (%2$d detections)', 'file-integrity-monitor' ), $unique, $total )
			/* translators: 1: unique file count, 2: total detection count */
			: sprintf( __( 'File Integrity Monitor: <strong>%1$d file(s)</strong> modified (%2$d detections)', 'file-integrity-monitor' ), $unique, $total );

		printf(
			'<div class="notice %s"><p>%s — <a href="%s">%s</a></p></div>',
			esc_attr( $class ),
			$message,
			esc_url( admin_url( 'tools.php?page=file-integrity' ) ),
			esc_html__( 'View details', 'file-integrity-monitor' )
		);
	}
}
