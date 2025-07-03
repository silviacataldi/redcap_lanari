$(function(){
	$('#econsent_confirm_checkbox').prop('disabled',true);
	$('#form button[name=\"submit-btn-saverecord\"]').button('disable');
	$('#econsent_confirm_checkbox_label, #econsent_confirm_checkbox').on('click', function(){	
		if ($('#econsent_confirm_checkbox').prop('checked')) {
			$('#form button[name=\"submit-btn-saverecord\"]').button('enable');
			$('#econsent_confirm_checkbox_div').removeClass('yellow').addClass('green');
		} else {
			$('#form button[name=\"submit-btn-saverecord\"]').button('disable');
			$('#econsent_confirm_checkbox_div').removeClass('green').addClass('yellow');
		}
	});
	showProgress(1,0);
	setTimeout(function(){
		$('#econsent_confirm_checkbox').prop('disabled',false);
		$('#econsent_confirm_checkbox_label').removeClass('opacity50');
	},1000);
	// The "working" progress meter should hide when the pdf loads, but just in case, remove it after 30s.
	checkConsentPdfLoaded(1); // Check every second
	setTimeout(function () {
		showProgress(0, 0);
	}, 30000); // If inline PDF is not displayed, then display page immediately
});

// Check every X seconds to see if the consent certification pdf has loaded fully
function checkConsentPdfLoaded(timeout) {
	if (!isinteger(timeout)) return;
	setTimeout(function () {
		if ($('.inline-pdf-viewer:visible').length == $('.inline-pdf-viewer.inline-pdf-viewer-loaded:visible').length) {
			// Stop showing the progress icon
			showProgress(0, 0);
		} else {
			// Check again in X seconds
			checkConsentPdfLoaded(timeout*1000);
		}
	}, timeout*1000);
}

function resetSignatureValuesPrep() {
    var ob = $('#form button[name=\"submit-btn-saveprevpage\"]');
    ob.attr('onclick','return false;').on('click', function(){
        simpleDialog(null,null,'resetSignatureValuesDialog',600,null,window.lang.global_53,'resetSignatureValues();',window.lang.survey_1266);
	});
}

function resetSignatureValues() {
    $('#form').attr('action', $('#form').attr('action')+'&__es=1');
    var prevPageBtn = $('#form button[name=\"submit-btn-saveprevpage\"]');
    prevPageBtn.button("disable");
    dataEntrySubmit(prevPageBtn);
}