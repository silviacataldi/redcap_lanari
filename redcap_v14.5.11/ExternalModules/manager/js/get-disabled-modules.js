$(function(){
	// first show disabledModal and then show enableModal
	var disabledModal = $('#external-modules-disabled-modal');
	var enableModal = $('#external-modules-enable-modal');

	var reloadThisPage = function(){
		$('<div class="modal-backdrop fade in"></div>').appendTo(document.body);
		window.location.reload();
	}

	disabledModal.find('.delete-selected-button').click(function(event){
		var row = $(event.target).closest('tr');
		var title = row.find('td:eq(0)')[0].childNodes[0].textContent.trim();
		var prefix = row.data('module');
		var versions = row.find('option').map((i,option) => option.textContent).toArray();
		var versionChecks = '';
		versions.forEach(function(version){
			const safeVersion = version.replace(/\./g, '_');
			versionChecks += '<div class="form-check">';
			versionChecks += `<input class="form-check-input module-delete-checkbox" type="checkbox" value="${prefix}_${version}" id="${prefix}_${safeVersion}_checkbox">`;
			versionChecks += `<label class="form-check-label" for="${prefix}_${safeVersion}_checkbox"><b>${prefix}_${version}</b></label>`;
			versionChecks += '</div>';
		});
		var selectAllButton = `<button class="btn btn-xs btn-defaultrc mb-2" onclick="$('.module-delete-checkbox').prop('checked', true);">${ExternalModules.$lang.tt('em_manage_142')}</button>`;

		simpleDialog(
			//= Select the versions of the module {0} that you wish to delete. Note that doing so will permanently remove the module's directories from the REDCap server. {1}
			ExternalModules.$lang.tt('em_manage_137', '<b>' + title + '</b>', '<br><br>' + selectAllButton + versionChecks +'<br>'), 
			//= Delete Versions
			ExternalModules.$lang.tt('em_manage_136'),
			null, null, null, 
			//= Cancel
			ExternalModules.$lang.tt('em_manage_12'), 
			function(){
				showProgress(1);
				var errors1 = [];
				var errors0 = [];
				var successes = [];
				var promises = versions.filter(version => $(`input[value="${prefix}_${version}"]`).is(':checked'))
				.map(function(version){
					return $.post('ajax/delete-module.php', { module_dir: prefix+'_'+version })
					.done(function(data){
						if (data == '1') {
							errors1.push(version);
						} else if (data == '0') {
							errors0.push(version);
						} else {
							successes.push(version);
						}
					});
				});
				if (promises.length === 0) {
					showProgress(0,0);
					//= You must select at least one version of the module to delete.
					simpleDialog(ExternalModules.$lang.tt('em_manage_138'));
					return false;
				}
				Promise.all(promises).then(function(results){
					showProgress(0,0);

					var noErrors = errors1.length === 0 && errors0.length === 0;
					if (noErrors) {
						$('#external-modules-disabled-modal').hide();
					}
					var dialogTitle = noErrors ? ExternalModules.$lang.tt('em_manage_27') : ExternalModules.$lang.tt('em_manage_30');
					
					var errorText1 = errorText0 = successText = '';
					if (successes.length > 0) {
						//= The following versions of the module have been deleted successfully: {0}
						successText += '<span class="far fa-circle-check text-success" aria-hidden="true"></span>&nbsp;<b>' + ExternalModules.$lang.tt('em_manage_139', '</b><br>' + prefix + '_' + successes.join('<br>' + prefix + '_') + '<br><br>');
					}
					if (errors1.length > 0) {
						//= The following versions of the module could not be found on the REDCap web server: {0}
						errorText1 += '<span class="far fa-circle-question text-danger" aria-hidden="true"></span>&nbsp;<b>' + ExternalModules.$lang.tt('em_manage_140', '</b><br>' + prefix + '_' + errors1.join('<br>' + prefix + '_') + '<br><br>');
					}
					if (errors0.length > 0) {
						//= The following versions of the module could not be deleted from the REDCap web server: {0}
						errorText0 += '<span class="far fa-xmark-circle text-danger" aria-hidden="true"></span>&nbsp;<b>' + ExternalModules.$lang.tt('em_manage_141', '</b><br>' + prefix + '_' + errors0.join('<br>' + prefix + '_') + '<br><br>');
					}
					simpleDialog(
						successText + errorText1 + errorText0,
						dialogTitle,
						null,null,function(){
							successes.length > 0 && window.location.reload();
						}, 
						//= Close
						ExternalModules.$lang.tt('em_manage_68'));
				});
			}, 
			//= Delete Versions
			ExternalModules.$lang.tt('em_manage_136'));
		return false;
	});

	disabledModal.find('.enable-button').click(function(event){
		// Prevent form submission
		event.preventDefault();
		var myClass = $(this).attr('class');
		const sendEnableRequest = myClass.split(" ").includes('module-request');
		disabledModal.hide();

		var row = $(event.target).closest('tr');
		var prefix = row.data('module');
		var version = row.find('[name="version"]').val();

		var enableErrorDiv = $('#external-modules-enable-modal-error');
		enableErrorDiv.html(''); // Clear out any previous errors

		var enableButton = enableModal.find('.enable-button');

		var enableModule = function(){
			const onError = function(message, stackTrace){
				if(stackTrace){
					enableErrorDiv.show();
					enableErrorDiv.html(message + '<br><br><pre>' + stackTrace + '</pre>');
					$('.close-button').attr('disabled', false);
					enableButton.hide();
				}
				else{
					//= An error occurred while enabling the module:
					var errorPrefix = '';
					if (sendEnableRequest) {
						errorPrefix = ExternalModules.$lang.tt('em_manage_89')+' ';
					}
					else {
						errorPrefix = ExternalModules.$lang.tt('em_manage_69')+' ';
					}
					var message = errorPrefix+' '+message;
					console.log('AJAX Request Error:', message);
					alert(message);
					disabledModal.modal('hide');
					enableModal.modal('hide');
				}
			}

			const onSuccess = () => {
				if (sendEnableRequest) {
					disabledModal.modal('hide');
					enableModal.modal('hide');
					simpleDialog(ExternalModules.$lang.tt('em_errors_112'),ExternalModules.$lang.tt('em_manage_27'));
				} else {
					reloadThisPage();
				}
			}

			ExternalModules.enableModule(prefix, version, sendEnableRequest, null, onSuccess, onError)
		}

		if (!pid) {
			enableButton.html('Enable');
			enableModal.find('button').attr('disabled', false);

			var list = enableModal.find('.modal-body ul');
			list.html('');
			
			$.get(ExternalModules.APP_URL_EXTMOD_RELATIVE + 'manager/ajax/list-hooks.php?prefix=' + prefix + '&version=' + version, (response) => {
				try{
					const hooks = JSON.parse(response)
					var permissionCount = 0;
					hooks.forEach(function(permission){
						if (permission != "") {
							list.append("<li>" + permission + "</li>");
							permissionCount++;
						}
					});
					if (permissionCount == 0) {
						list.append('<li><i>' +
							//= None (no permissions requested)
							ExternalModules.$lang.tt('em_manage_70') + 
							'</i></li>');
					}

					enableButton.off('click'); // disable any events attached from other modules
					enableButton.click(function(){
						//= Enabling... 
						enableButton.html(ExternalModules.$lang.tt('em_manage_71')); 
						enableModal.find('button').attr('disabled', true);
						enableModule();
					});
					enableButton.show();
					enableModal.modal('show');
				}
				catch(e){
					alert(response);
					reloadThisPage();
				}
			})
		} else {   // pid
			enableModule();
		}
	});

	if (enableModal) {
		enableModal.on('hide.bs.modal', function(){
			// We used to try to display the previous dialog again here, but it caused some odd edge cases related to multiple dialogs.
			// Simply reloading is cleaner.
			reloadThisPage();
		});
	}
});
