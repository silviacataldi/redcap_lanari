function createInput(key, value) {
	var input = document.createElement('input');
	input.type = 'hidden';
	input.name = key;
	input.value = value;
	return input;
}

function createApiForm(url, csrf_token) {
	var form = document.createElement('form');
	form.setAttribute('action', url);
	form.setAttribute('method', 'POST');
	var csrfTokenInput = createInput('redcap_csrf_token', csrf_token);
	form.appendChild(csrfTokenInput);
	document.body.appendChild(form);
	return form;
}

function PatientConnector(browserSupported, url, csrf_token) {
	this.constructor = function() {
		this.newRecord = null;
		this.form = createApiForm(url, csrf_token);
		this.browserSupported = (browserSupported === true) ? true : false;

		// Check record name for invalid characters
		$('#addPatientDialog #newRecordName').blur(function() {
			var input = $(this);
			input.val(input.val().trim());
			var validRecordName = recordNameValid(input.val());
			if (validRecordName !== true) {
				alert(validRecordName);
				input.focus();
				return false;
			}
		});
	}
	this.constructor.apply(this);

	this.addProject = function(pid) {
		var data = { pid: pid };
		this.submitForm('add-project', data);
	}

	this.removeProject = function(pid) {
		var data = { pid: pid };
		this.submitForm('remove-project', data);
	}

	this.ddpExplainDialog = function() {
		$('#ddpExplainDialog').dialog('open');
	}

	this.showRecord = function(projectID, recordID) {
		var makeQueryParam = function(params) {
			var queryParams = [];
			for (var key in params) {
				if (Object.prototype.hasOwnProperty.call(params, key)) {
					var value = params[key];
					var pair = [key, encodeURIComponent(value)];
					queryParams.push(pair.join('='));
				}
			}
			return queryParams.join('&');
		}

		var params = {
			pid: projectID,
			id: recordID
		};

		var queryParam = makeQueryParam(params);
		var url = app_path_webroot + 'DataEntry/record_home.php?' + queryParam;

		if (!this.browserSupported) {
			alert('this feature is not supported in this browser');
			return;
		}
		window.location.href = url;
	}

	this.addPatientToProject = function(pid, mrn, record_auto_numbering) {
		var createRecord = function() {
			var data = {
				pid: pid,
				mrn: mrn,
				record: $('#addPatientDialog #newRecordName').val()
			};
			this.submitForm('create-patient-record', data);
		}

		var self = this;
		if (record_auto_numbering == '1') {
			$('#addPatientDialog #newRecordNameDiv, #addPatientDialog #newRecordNameAutoNumText').hide();
		} else {
			$('#addPatientDialog #newRecordNameDiv, #addPatientDialog #newRecordNameAutoNumText').show();
		}
		$('#addPatientDialog #newRecordName').val('');
		var projectTitle = $('.ehr-project-title-' + pid).text();
		$('#addPatientDialog #newRecordNameProjTitle').text(projectTitle);

		$('#addPatientDialog').dialog({
			bgiframe: true,
			modal: true,
			width: 500,
			buttons: {
				'Cancel': function() {
					$(this).dialog('close');
				},
				'Create record': function() {
					if (record_auto_numbering == '0' && $('#addPatientDialog #newRecordName').val() == '') {
						setTimeout(function() {
							self.addPatientToProject(pid, mrn, record_auto_numbering);
							simpleDialog('Please enter a record name for the new record.');
						}, 100);
						return false;
					}
					showProgress(1);
					createRecord.apply(self);
				}
			}
		});
	}

	this.submitForm = function(action, additionalData = {}) {
		var form = this.form;
		var elements = form.elements;

		// Remove any previously added hidden inputs except CSRF token
		for (var i = 0; i < elements.length; i++) {
			var element = elements[i];
			if (element.name == 'redcap_csrf_token') continue;
			element.parentNode.removeChild(element);
		}

		// Set the action
		additionalData.action = action;

		// Add additional hidden inputs based on the function being called
		for (var key in additionalData) {
			if (Object.prototype.hasOwnProperty.call(additionalData, key)) {
				var input = createInput(key, additionalData[key]);
				form.appendChild(input);
			}
		}

		// Submit the form
		form.submit();
	}
}
