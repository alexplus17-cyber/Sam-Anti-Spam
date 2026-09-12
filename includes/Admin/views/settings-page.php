<?php
/**
 * Sam Anti Spam — Security Control Center
 *
 * Lives inside .sam-anti-spam-wrap. Every identifier, form, nonce and AJAX
 * contract from the original page is preserved verbatim.
 *
 * @var string $active_tab Validated current tab.
 */

global $wpdb;

$sam_options = get_option( 'sam_antispam_settings', array() );
if ( ! is_array( $sam_options ) ) {
	$sam_options = array();
}

$sam_active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
$sam_valid_tabs = array( 'general', 'integrations', 'traffic', 'lists', 'log' );
if ( ! in_array( $sam_active_tab, $sam_valid_tabs, true ) ) {
	$sam_active_tab = 'general';
}

$sam_active_tab = apply_filters( 'sam_antispam_active_tab', $sam_active_tab );

// Real data only — used by the hero overview cards.
$sam_sfw_enabled     = ! empty( $sam_options['enable_sfw'] );
$sam_cloud_connected = ! empty( $sam_options['api_key'] );

if ( $sam_cloud_connected ) {
	$sam_raw_key = sanitize_text_field( $sam_options['api_key'] );
	$sam_masked  = strlen( $sam_raw_key ) > 8
		? substr( $sam_raw_key, 0, 4 ) . '••••••••••••••••••••' . substr( $sam_raw_key, -4 )
		: substr( $sam_raw_key, 0, 2 ) . '****';
} else {
	$sam_masked = '';
}

$sam_cloud_url = \SamAntiSpam\Admin\Settings::get_cloud_base_url();
$sam_log_total = 0;
$sam_log_table = $wpdb->prefix . 'sam_spam_log';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sam_log_table ) ) ) {
	$sam_log_total = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$sam_log_table}" );
}

$sam_active_protections = 0;
$sam_protection_keys    = array( 'enable_comments', 'enable_registrations', 'enable_cf7', 'enable_woo', 'enable_support_tickets' );
foreach ( $sam_protection_keys as $sam_opt ) {
	if ( ! empty( $sam_options[ $sam_opt ] ) ) {
		++$sam_active_protections;
	}
}

$sam_tab_url = function ( $tab ) {
	return esc_url(
		add_query_arg(
			array(
				'page' => 'sam-anti-spam',
				'tab'  => $tab,
			),
			admin_url( 'options-general.php' )
		)
	);
};

$sam_tab_meta = array(
	'general'      => array( 'General', 'Core engine, Cloud connection and firewall settings.' ),
	'integrations' => array( 'Integrations', 'Protect built-in and third-party contact points.' ),
	'traffic'      => array( 'Traffic Control', 'Rate limiting and abuse protection for your frontend.' ),
	'lists'        => array( 'Whitelist & Blacklist', 'Trusted senders and known offenders.' ),
	'log'          => array( 'Spam Log', 'Review every blocked attempt and take action.' ),
);

$sam_content_title = isset( $sam_tab_meta[ $sam_active_tab ] ) ? $sam_tab_meta[ $sam_active_tab ][0] : 'General';
$sam_content_desc  = isset( $sam_tab_meta[ $sam_active_tab ] ) ? $sam_tab_meta[ $sam_active_tab ][1] : '';
?>
<div class="wrap sam-anti-spam-wrap">
	<?php settings_errors( 'sam_antispam_settings' ); ?>

	<!-- ==================== Header ==================== -->
	<header class="sam-head">
		<div class="sam-logo" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M12 2 4 5.5v5.2c0 5 3.2 9.4 8 11.3 4.8-1.9 8-6.3 8-11.3V5.5L12 2Z" fill="#fff" fill-opacity="0.16"/>
				<path d="M8.8 12 11 14.2l4.2-4.3" stroke="#fff" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
			</svg>
		</div>
		<div class="sam-head__text">
			<h1 class="sam-head__title">
				Sam Anti Spam
				<span class="sam-head__badge">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 5.5v5.2c0 5 3.2 9.4 8 11.3 4.8-1.9 8-6.3 8-11.3V5.5L12 2Z"/></svg>
					Security Control Center
				</span>
			</h1>
			<p class="sam-head__subtitle">Real-time protection for comments, registrations, forms and traffic.</p>
		</div>
		<div class="sam-head__actions">
			<a class="button sam-dark-btn" href="<?php echo esc_url( admin_url( 'options-general.php?page=sam-anti-spam-setup' ) ); ?>">Setup Wizard</a>
			<a class="button sam-dark-btn" href="https://github.com/alexplus17-cyber/Sam-Anti-Spam" target="_blank" rel="noopener noreferrer">Docs</a>
			<span class="sam-head__version">v<?php echo esc_html( SAM_ANTI_SPAM_VERSION ); ?></span>
		</div>
	</header>

	<!-- ==================== Hero overview (real data) ==================== -->
	<section class="sam-hero" aria-label="Security overview">
		<article class="sam-hero-card">
			<div class="sam-hero-card__row">
				<div>
					<p class="sam-eyebrow">Protection</p>
					<p class="sam-hero-card__title">Protection Status</p>
					<p class="sam-hero-card__value"><?php echo esc_html( $sam_active_protections ); ?><small>/5</small></p>
					<p class="sam-hero-card__meta">form types protected</p>
				</div>
				<div class="sam-hero-card__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M12 2 4 5.5v5.2c0 5 3.2 9.4 8 11.3 4.8-1.9 8-6.3 8-11.3V5.5L12 2Z" stroke="#1b6df5" stroke-width="1.6" stroke-linejoin="round"/>
						<path d="M8.8 12.2l2.2 2.2 4.3-4.4" stroke="#1b6df5" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
			</div>
			<p class="sam-hero-card__meta" style="margin-top:10px;">
				<span class="sam-pill <?php echo $sam_sfw_enabled ? 'is-accent' : ''; ?>"><span class="dot" aria-hidden="true"></span>Spam FireWall <?php echo $sam_sfw_enabled ? 'Active' : 'Off'; ?></span>
			</p>
		</article>

		<article class="sam-hero-card">
			<div class="sam-hero-card__row">
				<div>
					<p class="sam-eyebrow">Network</p>
					<p class="sam-hero-card__title">Cloud Connection</p>
					<p class="sam-hero-card__value <?php echo $sam_cloud_connected ? 'is-success' : 'is-warning'; ?>"><?php echo $sam_cloud_connected ? 'Connected' : 'Offline'; ?></p>
					<p class="sam-hero-card__meta"><?php echo $sam_cloud_connected ? esc_html( $sam_masked ) : 'Link in the General tab under Cloud Connection.'; ?></p>
				</div>
				<div class="sam-hero-card__icon <?php echo $sam_cloud_connected ? 'is-success' : 'is-warning'; ?>" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M7 18a4 4 0 0 1-.5-7.97A5 5 0 0 1 16.9 8.5 3.5 3.5 0 0 1 17 18H7Z" stroke="#0f172a" stroke-width="1.6" stroke-linejoin="round"/>
					</svg>
				</div>
			</div>
		</article>

		<article class="sam-hero-card">
			<div class="sam-hero-card__row">
				<div>
					<p class="sam-eyebrow">Traffic</p>
					<p class="sam-hero-card__title">Blocked Attempts</p>
					<p class="sam-hero-card__value"><?php echo esc_html( number_format_i18n( $sam_log_total ) ); ?></p>
					<p class="sam-hero-card__meta">logged for review in Spam Log</p>
				</div>
				<div class="sam-hero-card__icon is-accent" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M3 13h4l2.5-6 4 10L16 13h5" stroke="#0f172a" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</div>
			</div>
			<p class="sam-hero-card__meta" style="margin-top:10px;">
				<span class="sam-pill"><span class="dot" aria-hidden="true"></span>Rate limit 60 req / min / IP</span>
			</p>
		</article>
	</section>

	<!-- ==================== Layout: sidebar + content ==================== -->
	<div class="sam-layout">
		<aside class="sam-sidebar" aria-label="Settings navigation">
			<nav>
				<ul class="sam-nav">
					<li class="sam-nav__meta">Control Center</li>
					<li class="sam-nav__item <?php echo 'general' === $sam_active_tab ? 'is-active' : ''; ?>">
						<a class="sam-nav__link" href="<?php echo $sam_tab_url( 'general' ); ?>" <?php echo 'general' === $sam_active_tab ? 'aria-current="page"' : ''; ?>>
							<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="12" cy="12" r="3.2" stroke="currentColor" stroke-width="1.6"/><path d="M12 2.8v3M12 18.2v3M2.8 12h3M18.2 12h3M5.5 5.5l2.1 2.1M16.4 16.4l2.1 2.1M18.5 5.5l-2.1 2.1M7.6 16.4l-2.1 2.1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
							General
						</a>
					</li>
					<li class="sam-nav__item <?php echo 'integrations' === $sam_active_tab ? 'is-active' : ''; ?>">
						<a class="sam-nav__link" href="<?php echo $sam_tab_url( 'integrations' ); ?>" <?php echo 'integrations' === $sam_active_tab ? 'aria-current="page"' : ''; ?>>
							<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="3.5" y="3.5" width="7" height="7" rx="1.6" stroke="currentColor" stroke-width="1.6"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.6" stroke="currentColor" stroke-width="1.6"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.6" stroke="currentColor" stroke-width="1.6"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.6" stroke="currentColor" stroke-width="1.6"/></svg>
							Integrations
						</a>
					</li>
					<li class="sam-nav__item <?php echo 'traffic' === $sam_active_tab ? 'is-active' : ''; ?>">
						<a class="sam-nav__link" href="<?php echo $sam_tab_url( 'traffic' ); ?>" <?php echo 'traffic' === $sam_active_tab ? 'aria-current="page"' : ''; ?>>
							<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M3 12h3.5l2-4 3 8 2-4H21" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
							Traffic Control
						</a>
					</li>
					<li class="sam-nav__item <?php echo 'lists' === $sam_active_tab ? 'is-active' : ''; ?>">
						<a class="sam-nav__link" href="<?php echo $sam_tab_url( 'lists' ); ?>" <?php echo 'lists' === $sam_active_tab ? 'aria-current="page"' : ''; ?>>
							<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M4 6h16M4 12h10M4 18h7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="19.5" cy="14.5" r="2.6" stroke="#0891b2" stroke-width="1.6"/><circle cx="19.5" cy="18.5" r="0.6" fill="#0891b2"/></svg>
							Whitelist &amp; Blacklist
						</a>
					</li>
					<li class="sam-nav__item <?php echo 'log' === $sam_active_tab ? 'is-active' : ''; ?>">
						<a class="sam-nav__link" href="<?php echo $sam_tab_url( 'log' ); ?>" <?php echo 'log' === $sam_active_tab ? 'aria-current="page"' : ''; ?>>
							<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect x="3.5" y="4" width="17" height="16" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M7 9h10M7 13h10M7 17h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
							Spam Log
						</a>
					</li>
					<li class="sam-nav__meta">System</li>
					<li class="sam-nav__item">
						<a class="sam-nav__link" href="<?php echo esc_url( admin_url( 'options-general.php?page=sam-anti-spam-setup' ) ); ?>">
							<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 3.5 4.5 7v4.6c0 4.6 3 8.7 7.5 10.4 4.5-1.7 7.5-5.8 7.5-10.4V7L12 3.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9.5 12l2 2 3.5-3.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
							Setup Wizard
						</a>
					</li>
				</ul>
			</nav>
		</aside>

		<main class="sam-content">
			<?php if ( 'log' === $sam_active_tab ) : ?>

				<!-- ==================== Spam Log ==================== -->
				<section class="sam-card">
					<header class="sam-card__head">
						<div>
							<h2 class="sam-card__title">Spam Log</h2>
							<p class="sam-card__desc"><?php echo esc_html( $sam_content_desc ); ?></p>
						</div>
						<span class="sam-pill is-primary"><span class="dot" aria-hidden="true"></span><?php echo esc_html( number_format_i18n( $sam_log_total ) ); ?> blocked</span>
					</header>
					<div class="sam-card__body">
						<form id="sam-spam-log-form" method="get">
							<input type="hidden" name="page" value="sam-anti-spam" />
							<input type="hidden" name="tab" value="log" />
							<?php wp_nonce_field( 'bulk-spam_logs' ); ?>
							<?php
								$spam_table = new \SamAntiSpam\Admin\SpamLogTable();
								$spam_table->prepare_items();
								$spam_table->display();
							?>
						</form>
					</div>
				</section>

			<?php else : ?>

				<!-- ==================== Settings card (single Settings API form) ==================== -->
				<form action="options.php" method="post">
					<input type="hidden" name="sam_active_tab" value="<?php echo esc_attr( $sam_active_tab ); ?>" />
					<?php settings_fields( 'sam_antispam_settings' ); ?>
					<section class="sam-card">
						<header class="sam-card__head">
							<div>
								<h2 class="sam-card__title"><?php echo esc_html( $sam_content_title ); ?></h2>
								<p class="sam-card__desc"><?php echo esc_html( $sam_content_desc ); ?></p>
							</div>
							<?php if ( 'general' === $sam_active_tab ) : ?>
								<span class="sam-pill <?php echo $sam_sfw_enabled ? 'is-accent' : ''; ?>"><span class="dot" aria-hidden="true"></span>SFW <?php echo $sam_sfw_enabled ? 'Active' : 'Disabled'; ?></span>
							<?php elseif ( 'integrations' === $sam_active_tab ) : ?>
								<span class="sam-pill is-primary"><span class="dot" aria-hidden="true"></span><?php echo esc_html( $sam_active_protections ); ?>/<?php echo esc_html( count( $sam_protection_keys ) ); ?> protected</span>
							<?php endif; ?>
						</header>
						<div class="sam-card__body">
							<?php if ( 'general' === $sam_active_tab && ! $sam_cloud_connected ) : ?>
								<div class="sam-alert is-info" style="margin-bottom:20px;">
									<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6"/><path d="M12 8v4.4M12 15.8v.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
									<span>Cloud detection is not active for this site. Connect to the Sam Anti Spam Cloud to enable the shared bad-IP database and advanced heuristics — or keep the bundled local backend below.</span>
								</div>
							<?php endif; ?>

							<?php do_settings_sections( 'sam-anti-spam-' . $sam_active_tab ); ?>
						</div>
						<div class="sam-card__footer">
							<?php submit_button( 'Save Settings' ); ?>
						</div>
					</section>
				</form>

				<?php if ( 'general' === $sam_active_tab ) : ?>
					<!-- ==================== Troubleshooting (restore .htaccess) ==================== -->
					<section class="sam-card sam-danger-zone">
						<header class="sam-card__head">
							<h2 class="sam-card__title">
								<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 3.5 4.5 7v4.6c0 4.6 3 8.7 7.5 10.4 4.5-1.7 7.5-5.8 7.5-10.4V7L12 3.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12 8.5v5M12 16.5v.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
								Troubleshooting
							</h2>
						</header>
						<div class="sam-card__body">
							<p class="sam-body-text">If enabling the Spam FireWall broke your site, use this button to restore your <code>.htaccess</code> file to its previous state.</p>
							<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="margin-top:14px;">
								<input type="hidden" name="action" value="sam_restore_htaccess">
								<?php wp_nonce_field( 'sam_restore_htaccess' ); ?>
								<?php submit_button( 'Restore .htaccess', 'secondary button-danger', 'submit', false, array( 'onclick' => 'return confirm("Are you sure you want to restore the .htaccess file?");' ) ); ?>
							</form>
						</div>
					</section>
				<?php endif; ?>

			<?php endif; ?>
		</main>
	</div>
</div>