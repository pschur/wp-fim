<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<?php
$options          = get_option( 'fim_settings', [] );
$email_enabled    = ! empty( $options['email_enabled'] );
$email_address    = $options['email_address'] ?? get_option( 'admin_email' );
$auto_repair      = ! empty( $options['auto_repair'] );
$block_requests   = isset( $options['block_requests'] ) ? (bool) $options['block_requests'] : true;
$excluded_plugins = $options['excluded_plugins'] ?? [];
$excluded_themes  = $options['excluded_themes'] ?? [];

$all_plugins  = get_plugins();
$plugin_names = [];
foreach ( $all_plugins as $plugin_file => $data ) {
	$slug                  = dirname( $plugin_file );
	$slug                  = ( $slug === '.' ) ? basename( $plugin_file, '.php' ) : $slug;
	$plugin_names[ $slug ] = $data['Name'];
}

$all_themes  = wp_get_themes();
$theme_names = [];
foreach ( $all_themes as $slug => $theme ) {
	$theme_names[ $slug ] = $theme->get( 'Name' );
}
?>

<form method="post" action="options.php">
	<?php settings_fields( 'fim_settings_group' ); ?>

	<h2><?php esc_html_e( 'Notifications', 'file-integrity-monitor' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Email Notifications', 'file-integrity-monitor' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="fim_settings[email_enabled]" value="1" <?php checked( $email_enabled ); ?> />
					<?php esc_html_e( 'Send an email when a file change is detected', 'file-integrity-monitor' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fim_email"><?php esc_html_e( 'Email Address', 'file-integrity-monitor' ); ?></label></th>
			<td>
				<input
					type="email"
					id="fim_email"
					name="fim_settings[email_address]"
					value="<?php echo esc_attr( $email_address ); ?>"
					class="regular-text" />
				<p class="description"><?php esc_html_e( 'Leave empty to use the admin email address.', 'file-integrity-monitor' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Detection Behavior', 'file-integrity-monitor' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Block Requests', 'file-integrity-monitor' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="fim_settings[block_requests]" value="1" <?php checked( $block_requests ); ?> />
					<?php esc_html_e( 'Abort the HTTP request when a core file has been modified and repair is disabled or fails', 'file-integrity-monitor' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When disabled, changes are only logged and reported — the request continues normally. Recommended: enabled, unless you are actively testing or rely on auto-repair.', 'file-integrity-monitor' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Auto-Repair', 'file-integrity-monitor' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Automatically Repair Core Files', 'file-integrity-monitor' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="fim_settings[auto_repair]" value="1" <?php checked( $auto_repair ); ?> />
					<?php esc_html_e( 'Automatically restore modified WordPress core files from WordPress.org', 'file-integrity-monitor' ); ?>
				</label>
				<p class="description">
					<?php
					printf(
						/* translators: 1: core.svn.wordpress.org, 2: api.wordpress.org */
						esc_html__( 'When enabled, a hash mismatch on a core file triggers an automatic download of the official version from %1$s. The downloaded file\'s MD5 is verified against the official %2$s checksum before replacing the local copy.', 'file-integrity-monitor' ),
						'<code>core.svn.wordpress.org</code>',
						'<code>api.wordpress.org</code>'
					);
					?>
					<br />
					<strong><?php esc_html_e( 'Requirement:', 'file-integrity-monitor' ); ?></strong>
					<?php
					printf(
						/* translators: 1: api.wordpress.org, 2: core.svn.wordpress.org */
						esc_html__( 'The web server needs outbound HTTPS access to %1$s and %2$s.', 'file-integrity-monitor' ),
						'<code>api.wordpress.org</code>',
						'<code>core.svn.wordpress.org</code>'
					);
					?>
					<br />
					<?php esc_html_e( 'On success, the current request continues normally. On failure, the request is still blocked.', 'file-integrity-monitor' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'WordPress Version', 'file-integrity-monitor' ); ?></th>
			<td>
				<code><?php echo esc_html( get_bloginfo( 'version' ) ); ?></code>
				&nbsp;—&nbsp;
				<?php
				$checksums = FIM_Repair::get_official_checksums();
				if ( ! empty( $checksums ) ) :
					printf(
						'<span style="color:#155724;">&#10003; %s</span>',
						sprintf(
							/* translators: %d: number of files */
							esc_html__( 'Official checksums available (%d files)', 'file-integrity-monitor' ),
							count( $checksums )
						)
					);
				else :
					printf(
						'<span style="color:#856404;">&#9888; %s</span>',
						esc_html__( 'Could not retrieve official checksums for this version', 'file-integrity-monitor' )
					);
				endif;
				?>
				<p class="description"><?php esc_html_e( 'Checksums are fetched from WordPress.org once per hour and cached.', 'file-integrity-monitor' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Exclusions', 'file-integrity-monitor' ); ?></h2>
	<p><?php esc_html_e( 'Excluded areas are skipped during "Scan All" and are not checked at runtime. A manual scan from the Dashboard remains possible at any time.', 'file-integrity-monitor' ); ?></p>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Exclude Plugins', 'file-integrity-monitor' ); ?></th>
			<td>
				<?php
				$plugin_slugs = FIM_Scanner::get_plugin_slugs();
				if ( empty( $plugin_slugs ) ) :
					echo '<p><em>' . esc_html__( 'No plugins installed.', 'file-integrity-monitor' ) . '</em></p>';
				else :
				?>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Exclude Plugins', 'file-integrity-monitor' ); ?></legend>
					<?php foreach ( $plugin_slugs as $slug ) :
						$name = $plugin_names[ $slug ] ?? $slug;
					?>
					<label style="display:block; margin-bottom:5px;">
						<input
							type="checkbox"
							name="fim_settings[excluded_plugins][]"
							value="<?php echo esc_attr( $slug ); ?>"
							<?php checked( in_array( $slug, $excluded_plugins, true ) ); ?> />
						<?php echo esc_html( $name ); ?>
						<span style="color:#888; font-size:12px;">(<?php echo esc_html( $slug ); ?>)</span>
					</label>
					<?php endforeach; ?>
				</fieldset>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Exclude Themes', 'file-integrity-monitor' ); ?></th>
			<td>
				<?php
				$theme_slugs = FIM_Scanner::get_theme_slugs();
				if ( empty( $theme_slugs ) ) :
					echo '<p><em>' . esc_html__( 'No themes installed.', 'file-integrity-monitor' ) . '</em></p>';
				else :
				?>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Exclude Themes', 'file-integrity-monitor' ); ?></legend>
					<?php foreach ( $theme_slugs as $slug ) :
						$name = $theme_names[ $slug ] ?? $slug;
					?>
					<label style="display:block; margin-bottom:5px;">
						<input
							type="checkbox"
							name="fim_settings[excluded_themes][]"
							value="<?php echo esc_attr( $slug ); ?>"
							<?php checked( in_array( $slug, $excluded_themes, true ) ); ?> />
						<?php echo esc_html( $name ); ?>
						<span style="color:#888; font-size:12px;">(<?php echo esc_html( $slug ); ?>)</span>
					</label>
					<?php endforeach; ?>
				</fieldset>
				<?php endif; ?>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Settings', 'file-integrity-monitor' ) ); ?>
</form>

<hr />

<h2><?php esc_html_e( 'Log File', 'file-integrity-monitor' ); ?></h2>
<p>
	<?php
	printf(
		/* translators: %s: absolute path to the log file */
		esc_html__( 'All detected changes are written to %s.', 'file-integrity-monitor' ),
		'<code>' . esc_html( WP_CONTENT_DIR . '/fim-integrity.log' ) . '</code>'
	);
	?>
</p>

<?php
$log_file = WP_CONTENT_DIR . '/fim-integrity.log';
if ( file_exists( $log_file ) ) :
	$size  = size_format( filesize( $log_file ) );
	$lines = count( file( $log_file ) );
?>
	<p>
		<?php
		printf(
			/* translators: 1: file size, 2: number of log entries */
			esc_html__( 'Size: %1$s | Entries: %2$s', 'file-integrity-monitor' ),
			'<strong>' . esc_html( $size ) . '</strong>',
			'<strong>' . esc_html( $lines ) . '</strong>'
		);
		?>
	</p>
<?php else : ?>
	<p><?php esc_html_e( 'No log file exists yet.', 'file-integrity-monitor' ); ?></p>
<?php endif; ?>
