<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class FIM_Admin {

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'register_menu' ] );
		add_action( 'admin_init', [ self::class, 'register_settings' ] );
		add_action( 'admin_notices', [ 'FIM_Alerts', 'show_admin_notices' ] );
		add_action( 'wp_ajax_fim_scan_area', [ self::class, 'ajax_scan_area' ] );
		add_action( 'wp_ajax_fim_scan_all', [ self::class, 'ajax_scan_all' ] );
		add_action( 'wp_ajax_fim_clear_violations', [ self::class, 'ajax_clear_violations' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}

	public static function register_menu(): void {
		add_management_page(
			__( 'File Integrity Monitor', 'file-integrity-monitor' ),
			__( 'File Integrity', 'file-integrity-monitor' ),
			'manage_options',
			'file-integrity',
			[ self::class, 'render_page' ]
		);
	}

	public static function register_settings(): void {
		register_setting( 'fim_settings_group', 'fim_settings', [
			'sanitize_callback' => [ self::class, 'sanitize_settings' ],
		] );
	}

	public static function sanitize_settings( array $input ): array {
		$valid_plugins = FIM_Scanner::get_plugin_slugs();
		$valid_themes  = FIM_Scanner::get_theme_slugs();

		$raw_excluded_plugins = array_map( 'sanitize_text_field', (array) ( $input['excluded_plugins'] ?? [] ) );
		$raw_excluded_themes  = array_map( 'sanitize_text_field', (array) ( $input['excluded_themes'] ?? [] ) );

		return [
			'email_enabled'    => ! empty( $input['email_enabled'] ) ? 1 : 0,
			'email_address'    => sanitize_email( $input['email_address'] ?? '' ),
			'auto_repair'      => ! empty( $input['auto_repair'] ) ? 1 : 0,
			'block_requests'   => ! empty( $input['block_requests'] ) ? 1 : 0,
			'excluded_plugins' => array_values( array_intersect( $raw_excluded_plugins, $valid_plugins ) ),
			'excluded_themes'  => array_values( array_intersect( $raw_excluded_themes, $valid_themes ) ),
		];
	}

	public static function enqueue_assets( string $hook ): void {
		if ( $hook !== 'tools_page_file-integrity' ) {
			return;
		}

		wp_enqueue_style(
			'fim-admin',
			plugins_url( 'admin/assets/admin.css', FIM_PLUGIN_FILE ),
			[],
			FIM_VERSION
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'file-integrity-monitor' ) );
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';

		echo '<div class="wrap fim-wrap">';
		echo '<h1>' . esc_html__( 'File Integrity Monitor', 'file-integrity-monitor' ) . '</h1>';

		echo '<nav class="nav-tab-wrapper">';
		self::tab_link( 'dashboard', __( 'Dashboard', 'file-integrity-monitor' ), $active_tab );
		self::tab_link( 'settings', __( 'Settings', 'file-integrity-monitor' ), $active_tab );
		echo '</nav>';

		echo '<div class="fim-tab-content">';

		if ( $active_tab === 'settings' ) {
			include FIM_PLUGIN_DIR . 'admin/views/page-settings.php';
		} else {
			include FIM_PLUGIN_DIR . 'admin/views/page-dashboard.php';
		}

		echo '</div></div>';
	}

	private static function tab_link( string $tab, string $label, string $active ): void {
		$class = $tab === $active ? 'nav-tab nav-tab-active' : 'nav-tab';
		$url   = admin_url( 'tools.php?page=file-integrity&tab=' . $tab );
		printf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( $class ), esc_html( $label ) );
	}

	public static function ajax_scan_area(): void {
		check_ajax_referer( 'fim_scan_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'file-integrity-monitor' ) );
		}

		$area = sanitize_text_field( $_POST['area'] ?? '' );

		if ( ! preg_match( '/^(core|mu-plugins|plugin:[a-z0-9_-]+|theme:[a-z0-9_-]+)$/', $area ) ) {
			wp_send_json_error( __( 'Invalid area.', 'file-integrity-monitor' ) );
		}

		FIM_Scanner::scan_area( $area );

		wp_send_json_success( [
			'message' => __( 'Scan completed.', 'file-integrity-monitor' ),
			'area'    => $area,
		] );
	}

	public static function ajax_scan_all(): void {
		check_ajax_referer( 'fim_scan_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'file-integrity-monitor' ) );
		}

		FIM_Scanner::scan_all();

		wp_send_json_success( [ 'message' => __( 'Full scan completed.', 'file-integrity-monitor' ) ] );
	}

	public static function ajax_clear_violations(): void {
		check_ajax_referer( 'fim_scan_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'file-integrity-monitor' ) );
		}

		FIM_Alerts::clear_violations();
		wp_send_json_success( [ 'message' => __( 'Alerts cleared.', 'file-integrity-monitor' ) ] );
	}
}
