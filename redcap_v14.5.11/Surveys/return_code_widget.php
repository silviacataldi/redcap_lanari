<?php

// MLM - This file is now obsolete and can be deleted. The HTML is generate in Survey::getReturnCodeWidget()

exit("THIS FILE IS OBSOLETE");

// This page displays the "Returning?" box at the top left of a public survey page
if (!defined("APP_PATH_SURVEY_FULL")) exit;
?>

<div id="return_corner" class="trigger">
	<span class="fas fa-redo-alt" style="color:#0b5394;font-size:13px;"></span>&nbsp;<a aria-label="<?php print RCView::tt_js2("survey_1141") ?>" href="<?php echo APP_PATH_SURVEY_FULL ?>?s=<?php
		echo $_GET['s'] ?>&__return=1" style="color:#277ABE;"><b><?php echo RCView::tt("survey_22") ?></b></a>
</div>

<table id="dpop" class="popup">
	<tr>
		<td class="left"></td>
		<td>
			<table class="popup-contents">
				<tr>
					<td style="padding:5px;">
					<span class="fas fa-redo-alt" style="color:#277ABE;" aria-hidden="true"></span>
					<span style="color:#277ABE;"><b><?php echo RCView::tt("survey_22") ?></b> <?php echo RCView::tt("survey_23") ?></span>
					<br><br>
					<?php echo RCView::tt("survey_24") ?>
					<div style="text-align:center;padding:10px;">
						<button class="jqbuttonmed" style="color:#800000;" onclick="window.location.href='<?php echo APP_PATH_SURVEY_FULL ?>?s=<?php echo $_GET['s'] ?>&__return=1';"><?php echo RCView::tt("survey_25") ?></button>
					</div>
					</td>
				</tr>
			</table>
		</td>
		<td class="right"></td>
	</tr>
</table>
