(function ($) {
	'use strict';

	$(document).ready(function () {
		var statusDiv = document.getElementById('sam-api-status');
		if (!statusDiv) {
			return;
		}

		var spinner = document.getElementById('sam-connect-spinner');
		var nonce = (window.samAdmin && samAdmin.nonce) ? samAdmin.nonce : '';
		var ajaxurl = (window.samAdmin && samAdmin.ajaxurl) ? samAdmin.ajaxurl : window.ajaxurl;

		var cloudSvg = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
			'<path d="M7 18a4 4 0 0 1-.5-7.97A5 5 0 0 1 16.9 8.5 3.5 3.5 0 0 1 17 18H7Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>';

		function maskKey(rawKey) {
			if (typeof rawKey !== 'string' || rawKey.length < 9) {
				return (rawKey || '').substring(0, 2) + '****';
			}
			return rawKey.substring(0, 4) + '••••••••••••••••••••' + rawKey.substring(rawKey.length - 4);
		}

		function renderConnected(maskedKey) {
			statusDiv.innerHTML =
				'<div class="sam-cloud__icon is-success" aria-hidden="true">' + cloudSvg + '</div>' +
				'<div class="sam-cloud__body">' +
					'<p class="sam-cloud__status"><span class="sam-pill is-success"><span class="dot" aria-hidden="true"></span>Cloud Connected</span></p>' +
					'<p class="sam-cloud__detail">API Key <code>' + maskedKey + '</code></p>' +
				'</div>' +
				'<div class="sam-cloud__actions">' +
					'<button type="button" class="button button-secondary" id="sam-disconnect-btn">Disconnect</button>' +
				'</div>';
			statusDiv.appendChild(spinner);
		}

		function renderDisconnected(siteUrl, adminEmail) {
			statusDiv.innerHTML =
				'<div class="sam-cloud__icon is-warning" aria-hidden="true">' + cloudSvg + '</div>' +
				'<div class="sam-cloud__body">' +
					'<p class="sam-cloud__status"><span class="sam-pill is-warning"><span class="dot" aria-hidden="true"></span>Not Connected</span></p>' +
					'<p class="sam-cloud__detail">Site <code><span id="sam-site-url">' + siteUrl + '</span></code> &middot; Email <code><span id="sam-admin-email">' + adminEmail + '</span></code></p>' +
				'</div>' +
				'<div class="sam-cloud__actions">' +
					'<button type="button" class="button button-primary" id="sam-connect-btn">Connect to Cloud</button>' +
				'</div>';
			statusDiv.appendChild(spinner);
		}

		function post(data) {
			return fetch(ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams(data).toString()
			}).then(function (response) { return response.json(); });
		}

		statusDiv.addEventListener('click', function (e) {
			if (e.target && e.target.id === 'sam-connect-btn') {
				e.preventDefault();
				var connectBtn = e.target;
				var siteUrlEl = document.getElementById('sam-site-url');
				var adminEmailEl = document.getElementById('sam-admin-email');
				if (!siteUrlEl || !adminEmailEl) {
					return;
				}
				var siteUrl = siteUrlEl.innerText;
				var adminEmail = adminEmailEl.innerText;

				if (spinner) { spinner.classList.add('is-active'); }
				connectBtn.disabled = true;

				post({
					action: 'sam_register_cloud',
					security: nonce,
					site_url: siteUrl,
					admin_email: adminEmail
				}).then(function (data) {
					if (spinner) { spinner.classList.remove('is-active'); }
					if (data.success && data.data && data.data.api_key) {
						renderConnected(maskKey(data.data.api_key));
					} else {
						alert((data.data && data.data.message) ? data.data.message : 'Connection failed.');
						connectBtn.disabled = false;
					}
				}).catch(function () {
					if (spinner) { spinner.classList.remove('is-active'); }
					alert('An error occurred.');
					connectBtn.disabled = false;
				});
			}

			if (e.target && e.target.id === 'sam-disconnect-btn') {
				e.preventDefault();
				var disconnectBtn = e.target;
				if (!confirm('Are you sure you want to disconnect from the Cloud API?')) {
					return;
				}

				if (spinner) { spinner.classList.add('is-active'); }
				disconnectBtn.disabled = true;

				post({
					action: 'sam_disconnect_cloud',
					security: nonce
				}).then(function (data) {
					if (spinner) { spinner.classList.remove('is-active'); }
					if (data.success) {
						var siteUrl = (window.samAdmin && samAdmin.siteUrl) ? samAdmin.siteUrl : '';
						var adminEmail = (window.samAdmin && samAdmin.adminEmail) ? samAdmin.adminEmail : '';
						renderDisconnected(siteUrl, adminEmail);
					} else {
						alert((data.data && data.data.message) ? data.data.message : 'Disconnect failed.');
						disconnectBtn.disabled = false;
					}
				});
			}
		});
	});
})(jQuery);