<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$nonce  = wp_create_nonce( 'fim_scan_nonce' );
$status = FIM_Scanner::get_area_status();
?>

<div class="fim-dashboard">

	<div class="fim-section">
		<h2>
			<?php esc_html_e( 'Baseline Status', 'file-integrity-monitor' ); ?>
			<button
				class="button button-primary fim-scan-all-btn"
				data-nonce="<?php echo esc_attr( $nonce ); ?>"
				style="margin-left:12px; font-size:13px;">
				&#8635; <?php esc_html_e( 'Scan All Areas', 'file-integrity-monitor' ); ?>
			</button>
		</h2>
		<p><?php printf(
			/* translators: %s: "Scan All" label wrapped in <em> */
			esc_html__( 'Click "Scan" to create a new baseline fingerprint for a single area. Excluded areas are skipped when using %s.', 'file-integrity-monitor' ),
			'<em>' . esc_html__( 'Scan All', 'file-integrity-monitor' ) . '</em>'
		); ?></p>

		<table class="wp-list-table widefat fixed striped fim-status-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Area', 'file-integrity-monitor' ); ?></th>
					<th><?php esc_html_e( 'Status', 'file-integrity-monitor' ); ?></th>
					<th><?php esc_html_e( 'Last Scan', 'file-integrity-monitor' ); ?></th>
					<th><?php esc_html_e( 'Files', 'file-integrity-monitor' ); ?></th>
					<th><?php esc_html_e( 'Action', 'file-integrity-monitor' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
			$area_labels = [
				'core'       => __( 'WordPress Core', 'file-integrity-monitor' ),
				'mu-plugins' => __( 'Must-Use Plugins', 'file-integrity-monitor' ),
			];

			foreach ( $status as $area => $info ) :
				$label = $area_labels[ $area ] ?? $area;

				if ( str_starts_with( $area, 'plugin:' ) ) {
					/* translators: %s: plugin slug */
					$label = sprintf( __( 'Plugin: %s', 'file-integrity-monitor' ), substr( $area, 7 ) );
				} elseif ( str_starts_with( $area, 'theme:' ) ) {
					/* translators: %s: theme slug */
					$label = sprintf( __( 'Theme: %s', 'file-integrity-monitor' ), substr( $area, 6 ) );
				}

				if ( $info['excluded'] ) {
					$badge = '<span class="fim-badge fim-badge--excluded">' . esc_html__( 'Excluded', 'file-integrity-monitor' ) . '</span>';
				} elseif ( $info['scanned'] ) {
					$badge = '<span class="fim-badge fim-badge--ok">' . esc_html__( 'Active', 'file-integrity-monitor' ) . '</span>';
				} else {
					$badge = '<span class="fim-badge fim-badge--warn">' . esc_html__( 'Not scanned', 'file-integrity-monitor' ) . '</span>';
				}

				$last = $info['last_scanned']
					? esc_html( date_i18n( 'd.m.Y H:i', strtotime( $info['last_scanned'] ) ) )
					: '—';
			?>
				<tr>
					<td><strong><?php echo esc_html( $label ); ?></strong></td>
					<td><?php echo $badge; ?></td>
					<td><?php echo $last; ?></td>
					<td><?php echo esc_html( $info['file_count'] ); ?></td>
					<td>
						<button
							class="button fim-scan-btn<?php echo $info['excluded'] ? ' button-secondary' : ''; ?>"
							data-area="<?php echo esc_attr( $area ); ?>"
							data-nonce="<?php echo esc_attr( $nonce ); ?>"
							title="<?php echo $info['excluded'] ? esc_attr__( 'This area is skipped when using "Scan All"', 'file-integrity-monitor' ) : ''; ?>">
							<?php echo $info['excluded']
								? esc_html__( 'Manual scan', 'file-integrity-monitor' )
								: esc_html__( 'Scan', 'file-integrity-monitor' ); ?>
						</button>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php
	$repair_log  = FIM_Repair::get_repair_log();
	$options     = get_option( 'fim_settings', [] );
	$auto_repair = ! empty( $options['auto_repair'] );
	?>

	<div class="fim-section">
		<h2>
			<?php esc_html_e( 'Auto-Repair', 'file-integrity-monitor' ); ?>
			<?php if ( $auto_repair ) : ?>
				<span class="fim-badge fim-badge--ok" style="font-size:13px;"><?php esc_html_e( 'Active', 'file-integrity-monitor' ); ?></span>
			<?php else : ?>
				<span class="fim-badge fim-badge--warn" style="font-size:13px;"><?php esc_html_e( 'Disabled', 'file-integrity-monitor' ); ?></span>
			<?php endif; ?>
		</h2>

		<?php if ( ! $auto_repair ) : ?>
			<p>
				<?php esc_html_e( 'Auto-repair is disabled.', 'file-integrity-monitor' ); ?>
				<a href="<?php echo esc_url( admin_url( 'tools.php?page=file-integrity&tab=settings' ) ); ?>">
					<?php esc_html_e( 'Enable in Settings →', 'file-integrity-monitor' ); ?>
				</a>
			</p>
		<?php endif; ?>

		<?php if ( empty( $repair_log ) ) : ?>
			<p style="color:#555;"><?php esc_html_e( 'No repairs performed yet.', 'file-integrity-monitor' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:160px;"><?php esc_html_e( 'Time', 'file-integrity-monitor' ); ?></th>
						<th style="width:90px;"><?php esc_html_e( 'Status', 'file-integrity-monitor' ); ?></th>
						<th><?php esc_html_e( 'File', 'file-integrity-monitor' ); ?></th>
						<th><?php esc_html_e( 'Note', 'file-integrity-monitor' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $repair_log as $entry ) :
					$badge = $entry['success']
						? '<span class="fim-badge fim-badge--ok">' . esc_html__( 'Repaired', 'file-integrity-monitor' ) . '</span>'
						: '<span class="fim-badge fim-badge--critical">' . esc_html__( 'Failed', 'file-integrity-monitor' ) . '</span>';
				?>
					<tr>
						<td><?php echo esc_html( date_i18n( 'd.m.Y H:i:s', strtotime( $entry['time'] ) ) ); ?></td>
						<td><?php echo $badge; ?></td>
						<td><code><?php echo esc_html( $entry['path'] ); ?></code></td>
						<td><?php echo esc_html( $entry['note'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<?php $violations = FIM_Alerts::get_violations(); ?>

	<div class="fim-section">
		<h2>
			<?php esc_html_e( 'Detected Changes', 'file-integrity-monitor' ); ?>
			<?php if ( ! empty( $violations ) ) : ?>
				<span class="fim-count"><?php echo count( $violations ); ?></span>
				<button
					class="button button-secondary fim-clear-btn"
					data-nonce="<?php echo esc_attr( $nonce ); ?>"
					style="margin-left: 12px;">
					<?php esc_html_e( 'Clear alerts', 'file-integrity-monitor' ); ?>
				</button>
			<?php endif; ?>
		</h2>

		<?php if ( empty( $violations ) ) : ?>
			<p class="fim-ok-msg"><?php esc_html_e( 'No changes detected. Everything is fine.', 'file-integrity-monitor' ); ?></p>
		<?php else : ?>
			<?php $total_hits = array_sum( array_column( $violations, 'count' ) ); ?>
			<p style="color:#555; margin-top:0;"><?php
				printf(
					/* translators: 1: unique file count, 2: total detection count */
					esc_html__( '%1$d unique file(s) — %2$d detections total', 'file-integrity-monitor' ),
					count( $violations ),
					$total_hits
				);
			?></p>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:90px;"><?php esc_html_e( 'Severity', 'file-integrity-monitor' ); ?></th>
						<th><?php esc_html_e( 'File', 'file-integrity-monitor' ); ?></th>
						<th style="width:80px; text-align:center;"><?php esc_html_e( 'Frequency', 'file-integrity-monitor' ); ?></th>
						<th style="width:145px;"><?php esc_html_e( 'First Seen', 'file-integrity-monitor' ); ?></th>
						<th style="width:145px;"><?php esc_html_e( 'Last Seen', 'file-integrity-monitor' ); ?></th>
						<th style="width:110px;"><?php esc_html_e( 'Expected (MD5)', 'file-integrity-monitor' ); ?></th>
						<th style="width:110px;"><?php esc_html_e( 'Current (MD5)', 'file-integrity-monitor' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $violations as $v ) :
					$severity = $v['is_core']
						? '<span class="fim-badge fim-badge--critical">' . esc_html__( 'Critical', 'file-integrity-monitor' ) . '</span>'
						: '<span class="fim-badge fim-badge--warn">' . esc_html__( 'Warning', 'file-integrity-monitor' ) . '</span>';
					$count_badge = $v['count'] > 1
						? '<span class="fim-hit-count">' . esc_html( $v['count'] ) . '</span>'
						: '1';
				?>
					<tr>
						<td><?php echo $severity; ?></td>
						<td><code class="fim-filepath"><?php echo esc_html( $v['path'] ); ?></code></td>
						<td style="text-align:center;"><?php echo $count_badge; ?></td>
						<td><?php echo esc_html( date_i18n( 'd.m.Y H:i:s', strtotime( $v['first_seen'] ) ) ); ?></td>
						<td><?php echo esc_html( date_i18n( 'd.m.Y H:i:s', strtotime( $v['last_seen'] ) ) ); ?></td>
						<td><code><?php echo esc_html( substr( $v['expected'], 0, 8 ) ); ?>…</code></td>
						<td><code><?php echo esc_html( substr( $v['actual'], 0, 8 ) ); ?>…</code></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<script>
(function() {
	var l10n = {
		loading:  '<?php echo esc_js( __( 'Loading…', 'file-integrity-monitor' ) ); ?>',
		scan:     '<?php echo esc_js( __( 'Scan', 'file-integrity-monitor' ) ); ?>',
		scanning: '<?php echo esc_js( __( 'Scanning…', 'file-integrity-monitor' ) ); ?>',
		scanAll:  '<?php echo esc_js( __( 'Scan All Areas', 'file-integrity-monitor' ) ); ?>',
		error:    '<?php echo esc_js( __( 'Error: ', 'file-integrity-monitor' ) ); ?>'
	};

	document.querySelectorAll('.fim-scan-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var area  = this.dataset.area;
			var nonce = this.dataset.nonce;
			var btn   = this;
			btn.disabled = true;
			btn.textContent = l10n.loading;
			fetch(ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: 'action=fim_scan_area&area=' + encodeURIComponent(area) + '&nonce=' + encodeURIComponent(nonce),
			})
			.then(function(r) { return r.json(); })
			.then(function(data) {
				if (data.success) {
					location.reload();
				} else {
					alert(l10n.error + (data.data || ''));
					btn.disabled = false;
					btn.textContent = l10n.scan;
				}
			});
		});
	});

	var scanAllBtn = document.querySelector('.fim-scan-all-btn');
	if (scanAllBtn) {
		scanAllBtn.addEventListener('click', function() {
			var nonce = this.dataset.nonce;
			this.disabled = true;
			this.textContent = l10n.scanning;
			fetch(ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: 'action=fim_scan_all&nonce=' + encodeURIComponent(nonce),
			})
			.then(function(r) { return r.json(); })
			.then(function(data) {
				if (data.success) {
					location.reload();
				} else {
					alert(l10n.error + (data.data || ''));
					scanAllBtn.disabled = false;
					scanAllBtn.textContent = '蘵 ' + l10n.scanAll;
				}
			});
		});
	}

	var clearBtn = document.querySelector('.fim-clear-btn');
	if (clearBtn) {
		clearBtn.addEventListener('click', function() {
			var nonce = this.dataset.nonce;
			fetch(ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: 'action=fim_clear_violations&nonce=' + encodeURIComponent(nonce),
			})
			.then(function(r) { return r.json(); })
			.then(function(data) {
				if (data.success) location.reload();
			});
		});
	}
})();
</script>
