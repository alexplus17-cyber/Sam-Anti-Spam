<div class="wrap sam-admin-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php settings_errors( 'sam_antispam_settings' ); ?>

	<?php
	$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
	?>

	<h2 class="nav-tab-wrapper">
		<a href="?page=sam-anti-spam&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">General</a>
		<a href="?page=sam-anti-spam&tab=integrations" class="nav-tab <?php echo $active_tab == 'integrations' ? 'nav-tab-active' : ''; ?>">Integrations</a>
		<a href="?page=sam-anti-spam&tab=traffic" class="nav-tab <?php echo $active_tab == 'traffic' ? 'nav-tab-active' : ''; ?>">Traffic Control</a>
		<a href="?page=sam-anti-spam&tab=lists" class="nav-tab <?php echo $active_tab == 'lists' ? 'nav-tab-active' : ''; ?>">Whitelist/Blacklist</a>
		<a href="?page=sam-anti-spam&tab=log" class="nav-tab <?php echo $active_tab == 'log' ? 'nav-tab-active' : ''; ?>">Spam Log</a>
	</h2>

	<?php if ( $active_tab === 'log' ) : ?>
		<?php
			$spam_table = new \SamAntiSpam\Admin\SpamLogTable();
			$spam_table->prepare_items();
			$spam_table->display();
		?>
	<?php else : ?>
		<form action="options.php" method="post">
			<input type="hidden" name="sam_active_tab" value="<?php echo esc_attr( $active_tab ); ?>" />
			<?php
			settings_fields( 'sam_antispam_settings' );
			do_settings_sections( 'sam-anti-spam-' . $active_tab );
			submit_button( 'Save Settings' );
			?>
		</form>

		<?php if ( $active_tab === 'general' ) : ?>

			<script type="text/javascript">
			document.addEventListener('DOMContentLoaded', function() {
				var statusDiv = document.getElementById('sam-api-status');
				var spinner = document.getElementById('sam-connect-spinner');

				// Ensure nonce exists, falling back to a dummy string if not injected for some reason
				// A real implementation would localize this properly, but we can grab it from WP's _wpnonce
				var nonce = '<?php echo esc_js( wp_create_nonce( 'sam_admin_settings' ) ); ?>';

				// Event delegation for dynamically added buttons
				statusDiv.addEventListener('click', function(e) {
					if (e.target && e.target.id === 'sam-connect-btn') {
						e.preventDefault();
						var connectBtn = e.target;

						var siteUrl = document.getElementById('sam-site-url').innerText;
						var adminEmail = document.getElementById('sam-admin-email').innerText;

						spinner.classList.add('is-active');
						connectBtn.disabled = true;

						var data = new URLSearchParams();
						data.append('action', 'sam_register_cloud');
						data.append('security', nonce);
						data.append('site_url', siteUrl);
						data.append('admin_email', adminEmail);

						fetch(ajaxurl, {
							method: 'POST',
							body: data
						})
						.then(response => response.json())
						.then(data => {
							spinner.classList.remove('is-active');
							if (data.success && data.data && data.data.api_key) {
								// Mask the returned key for display
								var rawKey = data.data.api_key;
								var maskedKey = rawKey.substring(0, 3) + '*'.repeat(20) + rawKey.substring(rawKey.length - 3);

								// Dynamically update UI
								statusDiv.innerHTML = '<p style="color: green; font-weight: bold;">Status: Cloud Connected</p>' +
													  '<p><strong>API Key:</strong> ' + maskedKey + '</p>' +
													  '<button type="button" class="button button-secondary" id="sam-disconnect-btn">Disconnect</button>';
								statusDiv.appendChild(spinner);
							} else {
								alert(data.data.message || 'Connection failed.');
								connectBtn.disabled = false;
							}
						})
						.catch(error => {
							spinner.classList.remove('is-active');
							alert('An error occurred.');
							connectBtn.disabled = false;
						});
					}

					if (e.target && e.target.id === 'sam-disconnect-btn') {
						e.preventDefault();
						var disconnectBtn = e.target;
						if(!confirm('Are you sure you want to disconnect from the Cloud API?')) return;

						spinner.classList.add('is-active');
						disconnectBtn.disabled = true;

						var data = new URLSearchParams();
						data.append('action', 'sam_disconnect_cloud');
						data.append('security', nonce);

						fetch(ajaxurl, {
							method: 'POST',
							body: data
						})
						.then(response => response.json())
						.then(data => {
							spinner.classList.remove('is-active');
							if (data.success) {
								var siteUrl = '<?php echo esc_js( get_site_url() ); ?>';
								var adminEmail = '<?php echo esc_js( get_option( 'admin_email' ) ); ?>';

								// Dynamically update UI back to disconnected state
								statusDiv.innerHTML = '<p><strong>Site URL:</strong> <span id="sam-site-url">' + siteUrl + '</span></p>' +
													  '<p><strong>Admin Email:</strong> <span id="sam-admin-email">' + adminEmail + '</span></p>' +
													  '<p style="color: red;">Status: Not Connected</p>' +
													  '<button type="button" class="button button-primary" id="sam-connect-btn">Connect to Sam Anti Spam Cloud</button>';
								statusDiv.appendChild(spinner);
							} else {
								alert(data.data.message || 'Disconnect failed.');
								disconnectBtn.disabled = false;
							}
						});
					}
				});
			});
			</script>

			<hr />
			<h3>Troubleshooting</h3>
			<p>If enabling the Spam FireWall broke your site, use this button to restore your .htaccess file to its previous state.</p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="sam_restore_htaccess">
				<?php wp_nonce_field( 'sam_restore_htaccess' ); ?>
				<?php submit_button( 'Restore .htaccess', 'secondary', 'submit', false, array( 'onclick' => 'return confirm("Are you sure you want to restore the .htaccess file?");' ) ); ?>
			</form>
		<?php endif; ?>

	<?php endif; ?>
</div>
