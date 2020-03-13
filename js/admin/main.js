/* global WPRUS, console */
jQuery(document).ready(function($) {

	if ( WPRUS.debug ) {
		$('body').addClass('wprus-debug');
	}
	
	var toggleUI        = function() {
			$('.wprus-togglable').hide();
			$('.' + $('.nav-tab-active').data('toggle')).show();
			updateLogScroll();

			if (
				'wprus-site' === $('.nav-tab-active').data('toggle') &&
				!$('#postbox-container-2 .postbox.wprus-site').length
			) {
				$('#sites_placeholder').show();
			} else {
				$('#sites_placeholder').hide();
			}
		},
		refreshLogs     = function(handle) {

			if ( 'undefined' !== typeof handle ) {
				handle.attr('disabled', 'disabled');
			}

			$.ajax({
				url: WPRUS.ajax_url,
				type: 'POST',
				data: { action: 'wprus_refresh_logs' },
				success: function(response) {

					if ( response.success ) {
						$('#logs_view').html(response.data.html);
						$('.logs-clean-trigger').val(response.data.clean_trigger_text);
					}
				},
				error: function(jqXHR, textStatus) {
					WPRUS.debug && console.log(textStatus);
				},
				complete: function() {
					updateLogScroll();

					if ( 'undefined' !== typeof handle ) {
						handle.removeAttr('disabled');
					}
				}
			});
		},
		updateLogScroll = function() {
			var element = document.getElementById('logs_view');

			element.scrollTop = element.scrollHeight;
		};

	$('.nav-tab').on('click', function(e) {
		e.preventDefault();
		$('.nav-tab-active').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');
		toggleUI();
	});

	$('.wprus-container').each( function(idx, el) {
		var element = $(el);

		element.closest('.postbox').addClass(element.data('postbox_class'));
	});

	toggleUI();

	$('#postbox-container-2 .wprus-site').closest('.postbox').addClass('closed');

	$('#normal-sortables').on('click', '.handlediv, .wprus-site .hndle', function(e) {
		e.preventDefault();

		$(this).closest('.postbox').toggleClass('closed');
	});

	$('#postbox-container-2').on('click', '.deletion', function(e) {
		e.preventDefault();

		var r = window.confirm(WPRUS.delete_site_confirm);
			
		if (r) {
			$(this).closest('.wprus-site').remove();
		}
	});

	$('#wprus_add_trigger').on('click', function(e) {
		e.preventDefault();

		var url = $('#wprus_add_value').val();

		if (!url || $('.wprus-site[data-url="' + url + '"]').length) {

			return;
		}

		if (!url.startsWith('http')) {
			window.alert(WPRUS.http_required);

			return;
		}

		var site   = $('#postbox-container-2 .wprus-site-template').clone(),
			siteId = $('.wprus-site').length;

		site.find('input, select').each(function(idv, el) {
			var element = $(el);

			element.attr('name', 'wprus[sites][' + siteId + ']' + element.data('name'));
		});
		site.find('.wprus-container').attr('data-url', url);
		site.find('.hndle span').html(url);
		site.find('input[type="hidden"]').val(url);
		site.removeClass('wprus-site-template').addClass('wprus-site');
		site.attr({
			'data-url': url,
			'id': 'site_' + siteId
		});
		site.find('.wprus-select').select2({width: '100%'});
		site.find('.wprus-select-tag').select2({
			width: '100%',
			tags: true
		});
		$('#site_template').before(site);
		toggleUI();
	});

	$('.wprus-site .wprus-select, .wprus-users .wprus-select').select2({width: '100%'});
	$('.wprus-site .wprus-select-tag').select2(
		{
			width: '100%',
			tags: true
		}
	);

	$('#normal-sortables').on('change', '.wprus-select-tag', function() {
		$(this).find('option').each(function(index, el) {
			var element = $(el);

			element.attr('value', element.html());
		});
	});

	$('#normal-sortables').on('change', '.action-checkbox input', function() {
		var checkbox = $(this),
			row      = checkbox.closest('tr'),
			button   = row.find('.action-test button');

		row.find('.action-test-result span').hide();

		if ( checkbox.prop('checked') ) {
			button.removeAttr('disabled');
		} else {
			button.attr('disabled', 'disabled');
		}
	});

	$('#normal-sortables').on('click', '.action-test button', function(e) {
		e.preventDefault();

		var button = $(this),
			dataContainer = button.parent(),
			row           = button.closest('tr'),
			data          = {
				site_url  : button.closest('.wprus-container').data('url'),
				direction : dataContainer.data('direction'),
				data      : { username: WPRUS.username },
				action    : 'wprus_' + dataContainer.data('action') + '_notify_ping_remote',
			};

		button.attr('disabled', 'disabled');
		row.find('.failure').hide();
		row.find('.success').hide();

		$.ajax({
			url: WPRUS.ajax_url,
			type: 'POST',
			data: data,
			success: function(response) {

				if ( response.success ) {
					row.find('.success').show();
				} else {
					row.find('.failure').show();
					setTimeout(function() {
						window.alert( response.data );
					}, 10);	
				}
			},
			error: function(jqXHR, textStatus) {
				row.find('.success').hide();
				row.find('.failure').show();
				setTimeout(function() {
					window.alert( WPRUS.undefined_error );
				}, 10);
				WPRUS.debug && console.log(textStatus);
			},
			complete: function() {
				button.removeAttr('disabled');
				refreshLogs();
			}
		});
	});

	$('input[type="password"].toggle').on('focus', function() {
		$(this).attr('type','text');
	});

	$('input[type="password"].toggle').on('blur', function() {
		$(this).attr('type','password');
	});

	$('#wprus_log_refresh').on('click', function(e) {
		e.preventDefault();
		refreshLogs($(this));
	});

	$('.logs-clean-trigger').on('click', function(e) {
		e.preventDefault();

		var button = $(this);

		button.attr('disabled', 'disabled');

		$.ajax({
			url: WPRUS.ajax_url,
			type: 'POST',
			data: { action: 'wprus_clear_logs' },
			error: function(jqXHR, textStatus) {
				WPRUS.debug && console.log(textStatus);
			},
			complete: function() {
				button.removeAttr('disabled');
				refreshLogs();
			}
		});
	});

	$('#wprus_export_trigger').on('click', function(e) {
		e.preventDefault();

		var button  = $(this),
			data    = {
				action                        : 'wprus_export_users',
				nonce                         : $('#wprus_import_export_nonce').val(),
				offset                        : $('#wprus_export_offset').val(),
				max                           : $('#wprus_export_max').val(),
				keep_role                     : $('#wprus_export_keep_roles').val(),
				user_roles                    : $('#wprus_roles_export_select').val(),
				meta_keys                     : $('#wprus_metadata_export_select').val(),
				doing_import_export_operation : 1
			};

		console.log(data);

		button.attr('disabled', 'disabled');
		button.next().css('visibility', 'visible');
		$('.export-result').removeClass('show');
		$('.export-result a, .export-result-icons, .export-result-icons *').hide();

		$.ajax({
			url: WPRUS.ajax_url,
			type: 'POST',
			data: data,
			success: function(response) {

				if (response.success) {
					$('.export-result-icons .success').show();
					$('.export-result a').show();
				} else {

					if ( response.data.file_name ) {
						$('.export-result-icons .warning').show();
						$('.export-result a').show();
					} else {
						$('.export-result-icons .failure').show();
					}
				}

				if ( response.data && response.data.file_name ) {
					$('.export-result a').attr('data-file_name', response.data.file_name);
					$('.export-result a').attr(
						'href',
						WPRUS.download_url + '?wprus_file=' + response.data.file_name + '&nonce=' + data.nonce
					);
				}

				if ( response.data && response.data.message ) {
					$('#export_message').html(response.data.message);
					$('.export-result-icons').show();
					$('.export-result').addClass('show');
				}
			},
			error: function(jqXHR, textStatus) {
				WPRUS.debug && console.log(textStatus);
			},
			complete: function() {
				button.removeAttr('disabled');
				button.next().css('visibility', 'hidden');
			}
		});

	});

	$('.export-result a').on('click', function(e) {

		if ( $(this).hasClass('invalidate') ) {
			e.preventDefault();

			return;
		}

		$(this).addClass('invalidated');
	});

	$('#wprus_import_file').on('change', function() {
		var fileinput = $(this);
		
		if (0 < fileinput.prop('files').length) {
			$('#wprus_import_file_filename').val(fileinput.prop('files')[0].name);
			$('#wprus_import_file_trigger').removeAttr('disabled');
		} else {
			$('#wprus_import_file_filename').val('');
			$('#wprus_import_file_trigger').attr('disabled', 'disabled');
		}
	});

	$('#wprus_import_file_dropzone').on('drag dragstart dragend dragover dragenter dragleave drop', function(e) {
		e.preventDefault();
		e.stopPropagation();
	}).on('drop', function(e) {
		var fileinput = $('#wprus_import_file');

		fileinput.prop('files', e.originalEvent.dataTransfer.files);
		fileinput.trigger('change');
	});

	$('#wprus_import_file_trigger').on('click', function(e) {
		e.preventDefault();

		var button  = $(this),
			valid   = true,
			data    = new FormData(),
			file    = $('#wprus_import_file').prop('files')[0],
			regex   = /^([a-zA-Z0-9\-\_]*)\.dat/gm,
			summary = $('#wprus_import_results .summary'),
			errors  = $('#wprus_import_results .errors');

		button.attr('disabled', 'disabled');
		button.next().css('visibility', 'visible');
		summary.hide();
		errors.hide();

		if (
			typeof file !== 'undefined' &&
			typeof file.type !== 'undefined' &&
			typeof file.size !== 'undefined' &&
			typeof file.name !==  'undefined'
		) {

			if (!regex.test(file.name)) {
				window.alert(WPRUS.invalid_file_name);

				valid = false;
			}
			
		} else {
			window.alert(WPRUS.invalid_file);

			valid = false;
		}

		if (valid) {
			data.append('action','wprus_import_users');
			data.append('file', file);
			data.append('nonce', $('#wprus_import_export_nonce').val());
			data.append('doing_import_export_operation', 1);

			$.ajax({
				url: WPRUS.ajax_url,
				data: data,
				type: 'POST',
				cache: false,
				contentType: false,
				processData: false,
				success: function(response) {
					
					if (response.data) {

						if (response.data.message) {
							summary.html(response.data.message);
							summary.show();
						}

						if (response.data.errors) {
							errors.html('');

							$.each(response.data.errors, function(index, error) {
								var li = $('<li>' + error + '</li>');

								errors.append(li);
							});

							errors.show();
						}
					}
				},
				error: function (jqXHR, textStatus) {
					summary.html(WPRUS.undefined_import_error);
					summary.show();
					WPRUS.debug && console.log(textStatus);
				},
				complete: function() {
					button.removeAttr('disabled');
					button.next().css('visibility', 'hidden');
				}
			});
		} else {
			button.next().css('visibility', 'hidden');
			button.removeAttr('disabled');
		}
	});

	$('.wprus-help-title').on('click', function(e) {
		e.preventDefault();
		$(this).next().slideToggle(200);
	});

	$('.wprus-ui-wait').show();
});