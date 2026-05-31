<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class FIM_Scanner {

	private static ?array $baseline_cache = null;

	public static function get_baseline_path(): string {
		return FIM_DATA_DIR . '/hashes.json';
	}

	public static function load_baseline(): array {
		if ( self::$baseline_cache !== null ) {
			return self::$baseline_cache;
		}

		$path = self::get_baseline_path();
		if ( ! file_exists( $path ) ) {
			self::$baseline_cache = [];
			return [];
		}

		$json = file_get_contents( $path );
		$data = json_decode( $json, true );
		self::$baseline_cache = is_array( $data ) ? $data : [];
		return self::$baseline_cache;
	}

	public static function save_baseline( array $data ): bool {
		$path = self::get_baseline_path();
		$tmp  = $path . '.tmp.' . getmypid();
		$json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( file_put_contents( $tmp, $json ) === false ) {
			return false;
		}

		if ( ! rename( $tmp, $path ) ) {
			@unlink( $tmp );
			return false;
		}

		self::$baseline_cache = $data;
		return true;
	}

	public static function scan_all(): void {
		$options          = get_option( 'fim_settings', [] );
		$excluded_plugins = $options['excluded_plugins'] ?? [];
		$excluded_themes  = $options['excluded_themes'] ?? [];

		self::scan_area( 'core' );
		self::scan_area( 'mu-plugins' );

		foreach ( self::get_plugin_slugs() as $slug ) {
			if ( ! in_array( $slug, $excluded_plugins, true ) ) {
				self::scan_plugin( $slug );
			}
		}

		foreach ( self::get_theme_slugs() as $slug ) {
			if ( ! in_array( $slug, $excluded_themes, true ) ) {
				self::scan_theme( $slug );
			}
		}
	}

	public static function scan_area( string $area ): void {
		if ( $area === 'core' ) {
			self::scan_core();
		} elseif ( $area === 'mu-plugins' ) {
			self::scan_mu_plugins();
		} elseif ( str_starts_with( $area, 'plugin:' ) ) {
			self::scan_plugin( substr( $area, 7 ) );
		} elseif ( str_starts_with( $area, 'theme:' ) ) {
			self::scan_theme( substr( $area, 6 ) );
		}
	}

	public static function scan_core(): void {
		$baseline = self::load_baseline();
		$files    = [];

		self::scan_directory( ABSPATH . 'wp-includes', $files );
		self::scan_directory( ABSPATH . 'wp-admin', $files );

		// wp-login.php and wp-cron.php in root
		foreach ( glob( ABSPATH . '*.php' ) as $f ) {
			$files[ $f ] = md5_file( $f );
		}

		$baseline['core'] = [
			'last_scanned' => gmdate( 'c' ),
			'files'        => $files,
		];

		self::save_baseline( $baseline );
	}

	public static function scan_plugin( string $slug ): void {
		$baseline = self::load_baseline();
		$dir      = WP_PLUGIN_DIR . '/' . $slug;

		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = [];
		self::scan_directory( $dir, $files );

		if ( ! isset( $baseline['plugins'] ) ) {
			$baseline['plugins'] = [];
		}

		$baseline['plugins'][ $slug ] = [
			'last_scanned' => gmdate( 'c' ),
			'files'        => $files,
		];

		self::save_baseline( $baseline );
	}

	public static function scan_theme( string $slug ): void {
		$baseline = self::load_baseline();
		$dir      = get_theme_root() . '/' . $slug;

		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = [];
		self::scan_directory( $dir, $files );

		if ( ! isset( $baseline['themes'] ) ) {
			$baseline['themes'] = [];
		}

		$baseline['themes'][ $slug ] = [
			'last_scanned' => gmdate( 'c' ),
			'files'        => $files,
		];

		self::save_baseline( $baseline );
	}

	public static function scan_mu_plugins(): void {
		$baseline = self::load_baseline();
		$files    = [];

		self::scan_directory( WPMU_PLUGIN_DIR, $files );

		$baseline['mu-plugins'] = [
			'last_scanned' => gmdate( 'c' ),
			'files'        => $files,
		];

		self::save_baseline( $baseline );
	}

	private static function scan_directory( string $dir, array &$files ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && $file->getExtension() === 'php' ) {
				$path          = $file->getRealPath();
				$files[ $path ] = md5_file( $path );
			}
		}
	}

	public static function get_plugin_slugs(): array {
		$slugs = [];
		foreach ( glob( WP_PLUGIN_DIR . '/*', GLOB_ONLYDIR ) as $dir ) {
			$slugs[] = basename( $dir );
		}
		return $slugs;
	}

	public static function get_theme_slugs(): array {
		$slugs = [];
		foreach ( glob( get_theme_root() . '/*', GLOB_ONLYDIR ) as $dir ) {
			$slugs[] = basename( $dir );
		}
		return $slugs;
	}

	public static function get_area_status(): array {
		$baseline         = self::load_baseline();
		$options          = get_option( 'fim_settings', [] );
		$excluded_plugins = $options['excluded_plugins'] ?? [];
		$excluded_themes  = $options['excluded_themes'] ?? [];
		$status           = [];

		$areas = [
			'core'       => [ 'data' => $baseline['core'] ?? null,        'excluded' => false ],
			'mu-plugins' => [ 'data' => $baseline['mu-plugins'] ?? null,  'excluded' => false ],
		];

		foreach ( self::get_plugin_slugs() as $slug ) {
			$areas[ 'plugin:' . $slug ] = [
				'data'     => $baseline['plugins'][ $slug ] ?? null,
				'excluded' => in_array( $slug, $excluded_plugins, true ),
			];
		}

		foreach ( self::get_theme_slugs() as $slug ) {
			$areas[ 'theme:' . $slug ] = [
				'data'     => $baseline['themes'][ $slug ] ?? null,
				'excluded' => in_array( $slug, $excluded_themes, true ),
			];
		}

		foreach ( $areas as $key => $entry ) {
			$data            = $entry['data'];
			$status[ $key ] = [
				'scanned'      => $data !== null,
				'last_scanned' => $data['last_scanned'] ?? null,
				'file_count'   => $data ? count( $data['files'] ) : 0,
				'excluded'     => $entry['excluded'],
			];
		}

		return $status;
	}
}
