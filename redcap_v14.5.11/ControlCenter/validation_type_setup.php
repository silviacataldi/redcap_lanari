<?php


// Config for non-project pages
require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";


//If user is not a super user, go back to Home page
if (!ACCESS_CONTROL_CENTER) redirect(APP_PATH_WEBROOT);


// Obtain validation types
$valTypes = getValTypes();



// If making an AJAX call to enable/disable a validation type
if ($isAjax && isset($_POST['visible']) && isset($_POST['val_type']) && ACCESS_SYSTEM_CONFIG)
{
	// Check if we have everything
	if (!isset($valTypes[$_POST['val_type']])) exit('0');
	if ($_POST['visible'] != '1' & $_POST['visible'] != '0') exit('0');
	// Save the change
	$sql = "update redcap_validation_types set visible = {$_POST['visible']}
			where validation_name = '" . db_escape($_POST['val_type']) . "'";
	if (db_query($sql))
	{
		// Log the event
		Logging::logEvent($sql,"redcap_validation_types","MANAGE",$_POST['val_type'],"validation_name = '" . db_escape($_POST['val_type']) . "'","Modify field validation settings");
		// Now change value in array since array is already populated (this will update the table)
		$valTypes[$_POST['val_type']]['visible'] = $_POST['visible'];
	}
}
else
{
	// Regular page display
	include 'header.php';
	if (!ACCESS_SYSTEM_CONFIG) print "<script type='text/javascript'>$(function(){ disableAllFormElements(); });</script>";
	?>

	<h4 style="margin-top: 0;"><i class="fas fa-check-square" style="margin-left:2px;"></i> <?php echo $lang['control_center_150'] ?></h4>
	<p style='margin-top:0;'><?php echo $lang['control_center_151'] ?></p>
	<p style='margin-bottom:20px;'><?php echo $lang['control_center_154'] ?></p>

	<script type="text/javascript">
	function enableValType(val_type,visible) {
		$.post(app_path_webroot+page, { val_type: val_type, visible: visible }, function(data){
			if (data=='0') {
				alert(woops);
			} else {
				$('#val_table').html(data);
				highlightTableRow(val_type,3000);
			}
		});
	}
	</script>

	<div id="val_table">

<?php
}
?>



<!-- Table -->
<table cellpadding=0 cellspacing=0 style='border:0;border-collapse:collapse;' border="1">
	<tr>
		<td class='labelrc' style='padding-top:10px;padding-bottom:10px;background-color:#eee;font-family:verdana;color:#800000;' colspan='3'>
			<?php echo $lang['control_center_152'] ?>
		</td>
	</tr>
<?php foreach ($valTypes as $valType=>$valAttr) { ?>
	<tr id="<?php echo $valType ?>">
		<td class='data2'>
			<b><?php echo $valAttr['validation_label'] ?></b>
			<div style="color:#777;font-size:10px;">(<?php echo $valType ?>)</div>
		</td>
		<td class='data2' style='padding:0 15px;text-align:center;'>
			<?php if ($valAttr['visible']) { ?>
				<img src="<?php echo APP_PATH_IMAGES ?>tick.png">
			<?php } else { ?>
				<img src="<?php echo APP_PATH_IMAGES ?>delete.png">
			<?php } ?>
		</td>
		<td class='data2' style='padding:0 15px;text-align:center;'>
			<button onclick="this.disabled=true;enableValType('<?php echo $valType ?>',<?php echo ($valAttr['visible'] ? 0 : 1) ?>);"><?php echo ($valAttr['visible'] ? $lang['control_center_153'] : $lang['survey_152']) ?></button>
		</td>
	</tr>
<?php } ?>
</table>



<?php

if (!$isAjax) {
	print "</div>";
	include 'footer.php';
}
