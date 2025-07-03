<?php


// Config for non-project pages
require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";

include 'header.php';
if (!ACCESS_CONTROL_CENTER) redirect(APP_PATH_WEBROOT);
if (!ACCESS_SYSTEM_CONFIG) print "<script type='text/javascript'>$(function(){ disableAllFormElements(); $('button.jqbuttonmed').button('disable'); $('#table-template_projects_list a>img').remove(); });</script>";

?>
<style type="text/css">
#pagecontainer { max-width: 1100px; }
#template_projects_list { width: 100% !important; }
</style>
<script type="text/javascript">
$(function(){
	// If choose to use a template, then enable the tempate drop-down
	$('input[name="project_template_radio"]').change(function(){
		if ($('input[name="project_template_radio"]:checked').val() == '1') {
			// Enable drop-down and description box
			$('input[name="copyof"]').prop('disabled',false);
			$('#template_projects_list').fadeTo('fast',1);
		} else {
			// Disable the drop-down and reset its value
			$('input[name="copyof"]').prop('checked',false).prop('disabled',true);
			$('#template_projects_list').fadeTo('fast',0.25);
		}
	});
	// Template table: If click row, have it select the radio
	$('#table-template_projects_list tr').click(function(){
		if (!$('input[name="project_template_radio"]').length || ($('input[name="project_template_radio"]').length && $('input[name="project_template_radio"]:checked').val() == '1')) {
			$(this).find('input[name="copyof"]').prop('checked',true);
		}
	});
});
</script>
<?php
// Page title
print RCView::h4(array('style'=>'margin-top:0;'),
		'<i class="fas fa-star"></i> ' . $lang['create_project_79']
	  );
// Instructions
print RCView::p(array('style'=>'margin-top:0;'), $lang['create_project_80']);
// Display table of project templates
print ProjectTemplates::buildTemplateTable();

include 'footer.php';