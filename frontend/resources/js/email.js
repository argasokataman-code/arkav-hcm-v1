/*
Author       : Dreamstechnologies
Template Name: Smarthr - Bootstrap Admin Template
*/

(function () {
    "use strict";
	
	// View all Show hide One
	if($('.more-menu').length > 0) {
		$(".more-menu").hide();
		$(".viewall-button").on("click", function() {
			$(this).text($(this).text() === "Less" ? "Show More" : "Less");
			$(".more-menu").slideToggle(900);
		});	  	
	}

	if($('.more-menu-2').length > 0) {
		$(".more-menu-2").hide();
		$(".viewall-button-2").on("click", function() {
			$(this).text($(this).text() === "Less" ? "Show More" : "Less");
			$(".more-menu-2").slideToggle(900);
		});	  	
	}
	
	if($('.more-menu-3').length > 0) {
		$(".more-menu-3").hide();
		$(".viewall-button-3").on("click", function() {
			$(this).text($(this).text() === "Less" ? "Show More" : "Less");
			$(".more-menu-3").slideToggle(900);
		});	  	
	}

	// Compose Mail Popup
	function openComposeView() {
		if ($('.modal-backdrop').length === 0) {
			$('body').append('<div class="modal-backdrop fade show"></div>');
		}
		$('#compose-view').addClass('show');
	}

	function closeComposeView() {
		$('.modal-backdrop').remove();
		$('#compose-view').removeClass('show');
	}

	function normalize(value) {
		return (value || '').toString().trim();
	}

	function getApiToken() {
		try {
			if (window.AuthApi && typeof window.AuthApi.getToken === 'function') {
				return window.AuthApi.getToken() || null;
			}
		} catch (_e) {}

		return window.localStorage.getItem('arcav_access_token') ||
			window.sessionStorage.getItem('arcav_access_token') ||
			window.localStorage.getItem('token') ||
			window.sessionStorage.getItem('token') ||
			(document.querySelector('meta[name="api-token"]') || {}).content ||
			(document.querySelector('meta[name="auth-token"]') || {}).content ||
			null;
	}

	async function resolveApiToken() {
		var token = getApiToken();
		if (token) {
			return token;
		}

		try {
			var response = await fetch('/api-token', { credentials: 'include' });
			var payload = await response.json().catch(function () { return null; });
			if (!response.ok || !payload || payload.success !== true || !payload.data || !payload.data.token) {
				return null;
			}

			return String(payload.data.token);
		} catch (_error) {
			return null;
		}
	}

	function getTenantContext() {
		try {
			if (window.AuthApi && typeof window.AuthApi.getTenantContext === 'function') {
				return window.AuthApi.getTenantContext() || {};
			}
		} catch (_e) {}

		return {};
	}

	function buildHeaders(extra) {
		var headers = {
			'Accept': 'application/json',
			'Content-Type': 'application/json',
			'X-Requested-With': 'XMLHttpRequest'
		};

		var token = getApiToken();
		if (token) {
			headers.Authorization = 'Bearer ' + String(token);
		}

		var tenant = getTenantContext();
		if (tenant.companyCode) {
			headers['X-Company-Code'] = String(tenant.companyCode);
		}
		if (tenant.companyId) {
			headers['X-Company-Id'] = String(tenant.companyId);
		}
		if (tenant.companyUuid) {
			headers['X-Company-UUID'] = String(tenant.companyUuid);
		}

		if (extra) {
			Object.keys(extra).forEach(function (key) {
				headers[key] = extra[key];
			});
		}

		return headers;
	}

	function showComposeFeedback(type, message) {
		var $feedback = $('[data-email-compose-feedback]');
		if ($feedback.length === 0) {
			return;
		}

		$feedback.removeClass('d-none alert-success alert-danger alert-warning alert-info');
		$feedback.addClass('alert-' + type);
		$feedback.text(message);
	}

	function parseComposeError(payload, fallback) {
		if (!payload) {
			return fallback;
		}

		if (payload.error && payload.error.message) {
			return String(payload.error.message);
		}

		if (payload.message) {
			return String(payload.message);
		}

		return fallback;
	}

	function setComposeSubmittingState(isSubmitting) {
		var $submit = $('[data-email-compose-submit]');
		if ($submit.length === 0) {
			return;
		}

		$submit.prop('disabled', isSubmitting);
		$submit.html(isSubmitting ? 'Sending...' : 'Send <i class="ti ti-arrow-right ms-2"></i>');
	}

	function escapeHtml(value) {
		return (value || '').toString()
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function incrementEmailCounters() {
		var sentNode = document.querySelector('[data-email-count-sent]');
		var sent = Number((sentNode && sentNode.textContent) || 0) || 0;
		sent += 1;

		if (sentNode) {
			sentNode.textContent = String(sent);
		}
	}

	function appendSentRow(payload) {
		var list = document.querySelector('.mails-list');
		if (!list) {
			return;
		}

		var emptyState = list.querySelector('.email-empty-state');
		if (emptyState) {
			emptyState.remove();
		}

		var subject = escapeHtml(payload.subject || '(No subject)');
		var recipient = escapeHtml(payload.to || '-');
		var preview = escapeHtml((payload.message || '').slice(0, 160));
		var searchText = escapeHtml(((payload.to || '') + ' ' + (payload.subject || '') + ' ' + (payload.message || '')).toLowerCase());

		var html = '' +
			'<div class="list-group-item email-message-row" data-email-item="1" role="button" tabindex="0" aria-label="Open email preview" data-folder="sent" data-contact-label="To" data-contact-value="' + recipient + '" data-subject="' + subject + '" data-preview="' + preview + '" data-time="Baru saja" data-time-iso="" data-search-text="' + searchText + '">' +
			'  <div class="d-flex align-items-center justify-content-between">' +
			'    <div>' +
			'      <h6 class="mb-1">' + subject + '</h6>' +
			'      <p class="mb-1 text-muted">To: ' + recipient + '</p>' +
			'      <p class="mb-0 text-muted">' + preview + '</p>' +
			'    </div>' +
			'    <small class="text-muted">Baru saja</small>' +
			'  </div>' +
			'</div>';

		list.insertAdjacentHTML('afterbegin', html);
		incrementEmailCounters();

		var sentLink = document.querySelector('.email-tags a[data-email-folder="sent"]');
		if (sentLink) {
			sentLink.click();
		}

		list.dispatchEvent(new CustomEvent('email:list-updated'));
	}

	function setPreviewFromRow($row) {
		if (!$row || !$row.length) {
			return;
		}

		$('.mails-list .list-group-item').removeClass('active');
		$row.addClass('active');

		var subject = ($row.attr('data-subject') || '(No subject)').toString();
		var contactLabel = ($row.attr('data-contact-label') || 'From').toString();
		var contactValue = ($row.attr('data-contact-value') || '-').toString();
		var body = ($row.attr('data-preview') || '').toString();
		var time = ($row.attr('data-time') || '-').toString();

		$('[data-email-preview-subject]').text(subject);
		$('[data-email-preview-contact-label]').text(contactLabel);
		$('[data-email-preview-contact-value]').text(contactValue);
		$('[data-email-preview-time]').text(time);
		$('[data-email-preview-body]').text(body || 'Tidak ada preview konten untuk email ini.');
	}

	$("#compose_mail").on('click', function () {
		openComposeView();
	});
	
	$("#compose-close").on('click', function () {
		closeComposeView();
	});

	if ($('#compose-view').attr('data-auto-open') === '1') {
		openComposeView();
	}

	$('[data-email-compose-form]').on('submit', async function (event) {
		event.preventDefault();
		var form = event.currentTarget;

		var token = await resolveApiToken();
		if (!token) {
			showComposeFeedback('warning', 'API token tidak ditemukan. Silakan login ulang lalu coba lagi.');
			openComposeView();
			return;
		}

		var formData = new FormData(form);
		var payload = {
			to: normalize(formData.get('to')),
			subject: normalize(formData.get('subject')),
			message: normalize(formData.get('message'))
		};

		setComposeSubmittingState(true);
		showComposeFeedback('info', 'Mengirim email runtime...');

		try {
			var response = await fetch('/v1/hcm/email-settings/compose', {
				method: 'POST',
				headers: buildHeaders(),
				credentials: 'same-origin',
				body: JSON.stringify(payload)
			});

			var result = await response.json().catch(function () { return null; });
			if (!response.ok || !result || result.success !== true) {
				showComposeFeedback('danger', parseComposeError(result, 'Email gagal dikirim.'));
				openComposeView();
				return;
			}

			form.reset();
			$('.bootstrap-tagsinput .tag').remove();
			appendSentRow(payload);
			showComposeFeedback('success', 'Email berhasil dikirim ke ' + payload.to + '.');
			closeComposeView();
		} catch (_error) {
			showComposeFeedback('danger', 'Email gagal dikirim. Periksa koneksi lalu coba lagi.');
			openComposeView();
		} finally {
			setComposeSubmittingState(false);
		}
	});

	// Basic runtime wiring for static email template page.
	if ($('.mails-list').length > 0) {
		var $rows = $('.mails-list .list-group-item');
		var activeFolder = 'inbox';

		$rows.each(function () {
			var $row = $(this);
			var text = $row.text().toLowerCase();
			var starred = $row.find('.ti-star-filled').length > 0;
			var existingFolder = $row.attr('data-folder');
			$row.attr('data-starred', starred ? '1' : '0');
			$row.attr('data-folder', existingFolder || 'inbox');
			$row.attr('data-search-text', text);
		});

		function ensureEmptyState() {
			if ($('.mails-list .email-empty-state').length > 0) {
				return;
			}

			$('.mails-list').append(
				'<div class="list-group-item p-4 text-center text-muted email-empty-state d-none">No emails found for this filter.</div>'
			);
		}

		function normalizeFolder(label) {
			if (typeof label === 'object' && label && label.folderKey) {
				return label.folderKey;
			}
			var value = (label || '').toLowerCase();
			if (value.indexOf('inbox') !== -1) return 'inbox';
			if (value.indexOf('starred') !== -1) return 'starred';
			if (value.indexOf('sent') !== -1) return 'sent';
			if (value.indexOf('draft') !== -1) return 'drafts';
			if (value.indexOf('deleted') !== -1) return 'deleted';
			if (value.indexOf('spam') !== -1) return 'spam';
			if (value.indexOf('important') !== -1) return 'important';
			if (value.indexOf('all email') !== -1) return 'all';
			return 'inbox';
		}

		function belongsToFolder($row, folder) {
			var rowFolder = $row.attr('data-folder') || 'inbox';
			var starred = $row.attr('data-starred') === '1';

			if (folder === 'all') return true;
			if (folder === 'starred' || folder === 'important') return starred && rowFolder !== 'deleted' && rowFolder !== 'spam';
			if (folder === 'inbox') return rowFolder === 'inbox';
			return rowFolder === folder;
		}

		function applyFilters() {
			var query = ($('input[placeholder="Search Email"]').val() || '').toLowerCase().trim();
			var visibleCount = 0;
			var $firstVisible = $();

			$rows.each(function () {
				var $row = $(this);
				var folderMatch = belongsToFolder($row, activeFolder);
				var textMatch = query === '' || (($row.attr('data-search-text') || '').indexOf(query) !== -1);
				var show = folderMatch && textMatch;
				$row.toggle(show);
				if (show) {
					visibleCount += 1;
					if ($firstVisible.length === 0 && $row.attr('data-email-item') === '1') {
						$firstVisible = $row;
					}
				}
			});

			ensureEmptyState();
			$('.mails-list .email-empty-state').toggleClass('d-none', visibleCount > 0);

			if ($firstVisible.length > 0) {
				setPreviewFromRow($firstVisible);
			} else {
				$('[data-email-preview-subject]').text('No email selected');
				$('[data-email-preview-contact-label]').text('From');
				$('[data-email-preview-contact-value]').text('-');
				$('[data-email-preview-time]').text('-');
				$('[data-email-preview-body]').text('Tidak ada email untuk filter yang dipilih.');
			}
		}

		$('.email-tags a').on('click', function (e) {
			e.preventDefault();
			$('.email-tags a').removeClass('active');
			$(this).addClass('active');
			activeFolder = normalizeFolder({ folderKey: $(this).attr('data-email-folder') || '', label: $(this).text() });
			if (activeFolder === '') {
				activeFolder = normalizeFolder($(this).text());
			}
			applyFilters();
		});

		$('input[placeholder="Search Email"]').on('input', function () {
			applyFilters();
		});

		$('.mails-list').on('click', '.dropdown-item', function (e) {
			var action = ($(this).text() || '').toLowerCase().trim();
			var $row = $(this).closest('.list-group-item');

			if (!$row.length) {
				return;
			}

			if (action === 'delete') {
				$row.attr('data-folder', 'deleted');
				applyFilters();
				e.preventDefault();
				return;
			}

			if (action === 'archive') {
				$row.attr('data-folder', 'archive');
				applyFilters();
				e.preventDefault();
				return;
			}

			if (action === 'move to junk') {
				$row.attr('data-folder', 'spam');
				applyFilters();
				e.preventDefault();
				return;
			}

			if (action === 'mark as unread') {
				var $dot = $row.find('.ti-point-filled').first();
				$dot.removeClass('text-success').addClass('text-danger');
				e.preventDefault();
			}
		});

		$('.mails-list').on('click keydown', '.email-message-row[data-email-item="1"]', function (e) {
			if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
				return;
			}
			if ($(e.target).closest('.dropdown-menu, .dropdown-toggle, .dropdown-item, a, button').length > 0) {
				return;
			}

			e.preventDefault();
			setPreviewFromRow($(this));
		});

		$('.mails-list').on('email:list-updated', function () {
			$rows = $('.mails-list .list-group-item');
			applyFilters();
		});

		applyFilters();
	}

})();