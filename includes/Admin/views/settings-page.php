<div class="wrap sam-admin-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php settings_errors(); ?>

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
	<?php endif; ?>
</div>
