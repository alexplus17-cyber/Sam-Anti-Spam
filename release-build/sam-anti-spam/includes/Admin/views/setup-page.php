<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php settings_errors( 'sam_setup' ); ?>

	<!-- Stepper -->
	<ol class="sam-wizard-steps">
		<li class="<?php echo ( 1 >= $step ) ? 'is-active' : ''; ?>">1. Welcome</li>
		<li class="<?php echo ( 2 === $step ) ? 'is-active' : ''; ?>">2. Requirements</li>
		<li class="<?php echo ( 3 === $step ) ? 'is-active' : ''; ?>">3. Database</li>
		<li class="<?php echo ( 4 === $step ) ? 'is-active' : ''; ?>">4. Connect</li>
	</ol>

	<div class="sam-wizard-panel">
	<?php if ( 1 === $step ) : ?>
		<!-- STEP 1: Welcome -->
		<h2>Welcome to Sam Anti Spam Setup</h2>
		<p>This wizard will prepare the Cloud Backend that powers spam detection. It will:</p>
		<ul>
			<li>Check that your server meets the requirements.</li>
			<li>Create the Cloud database and import its schema.</li>
			<li>Write <code>backend/config.php</code> with your database credentials.</li>
			<li>Connect WordPress to the Cloud to issue an API key.</li>
		</ul>
		<?php if ( $configured ) : ?>
			<div class="notice notice-success inline"><p><strong>The Cloud database is already configured.</strong> You can review or re-run the setup, or jump to the final step to connect.</p></div>
		<?php endif; ?>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'options-general.php?page=sam-anti-spam-setup&step=2' ) ); ?>">Start Setup</a>
			<?php if ( $configured ) : ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'options-general.php?page=sam-anti-spam-setup&step=4' ) ); ?>">Skip to Connect</a>
			<?php endif; ?>
		</p>

	<?php elseif ( 2 === $step ) : ?>
		<!-- STEP 2: Requirements -->
		<h2>System Requirements</h2>
		<table class="widefat striped" style="max-width: 640px;">
			<tbody>
				<?php foreach ( $requirements as $r ) : ?>
					<tr>
						<td><?php echo esc_html( $r['label'] ); ?></td>
						<td style="width: 80px; text-align: right; font-weight: bold; color: <?php echo $r['ok'] ? 'green' : 'red'; ?>;">
							<?php echo $r['ok'] ? 'PASS' : 'FAIL'; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php $all_ok = ! in_array( false, array_column( $requirements, 'ok' ), true ); ?>
		<p>
			<?php if ( $all_ok ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'options-general.php?page=sam-anti-spam-setup&step=3' ) ); ?>">Continue</a>
			<?php else : ?>
				<div class="notice notice-error inline"><p>Please resolve the failing requirements before continuing.</p></div>
				<a class="button" href="<?php echo esc_url( admin_url( 'options-general.php?page=sam-anti-spam-setup&step=1' ) ); ?>">Back</a>
			<?php endif; ?>
		</p>

	<?php elseif ( 3 === $step ) : ?>
		<!-- STEP 3: Database -->
		<h2>Database Setup</h2>
		<p>Enter your MySQL credentials. The database and tables will be created automatically and <code>backend/config.php</code> will be written for you.</p>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<?php wp_nonce_field( 'sam_setup_cloud_db' ); ?>
			<input type="hidden" name="action" value="sam_setup_cloud_db" />

			<table class="form-table">
				<tr>
					<th><label for="db_host">DB Host</label></th>
					<td><input id="db_host" name="db_host" type="text" class="regular-text" value="127.0.0.1" /></td>
				</tr>
				<tr>
					<th><label for="db_name">Database Name</label></th>
					<td><input id="db_name" name="db_name" type="text" class="regular-text" value="sam_anti_spam_db" /></td>
				</tr>
				<tr>
					<th><label for="db_user">DB User</label></th>
					<td><input id="db_user" name="db_user" type="text" class="regular-text" value="" /></td>
				</tr>
				<tr>
					<th><label for="db_pass">DB Password</label></th>
					<td><input id="db_pass" name="db_pass" type="password" class="regular-text" value="" /></td>
				</tr>
			</table>

			<?php submit_button( 'Create Database & Continue' ); ?>
		</form>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'options-general.php?page=sam-anti-spam-setup&step=2' ) ); ?>">Back</a></p>

	<?php else : ?>
		<!-- STEP 4: Connect -->
		<h2>Connect to the Cloud</h2>
		<p>Your Cloud database is ready. Connect WordPress to issue an API key.</p>
		<?php
			$settings = new \SamAntiSpam\Admin\Settings();
			$settings->render_api_connection_ui();
		?>
		<hr />
		<p>
			<a class="button" href="<?php echo esc_url( admin_url( 'options-general.php?page=sam-anti-spam' ) ); ?>">Go to Sam Anti Spam Settings</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'options-general.php?page=sam-anti-spam-setup&step=3' ) ); ?>">Re-run Database Setup</a>
		</p>
	<?php endif; ?>
	</div>
</div>
