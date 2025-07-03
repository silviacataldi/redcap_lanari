<?php

class IdentifierCheckController extends Controller
{
	// Render Data Import Tool page
	public function index()
	{
		extract($GLOBALS);
		
		// Project header
		$this->render('HeaderProject.php', $GLOBALS);
		
		renderPageTitle("<img src='".APP_PATH_IMAGES."find.png'> ".$lang['identifier_check_01']);

		$table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";

		#process form
		if (isset($_POST['submit-btn']))
		{
			// Array to keep log of queries
			$sql_all = array();

			// First remove tag from ALL identifiers (will then add each next)
			$sql_all[] = $query = "UPDATE $table SET field_phi = NULL WHERE project_id = $project_id";
			db_query($query);

			foreach ($_POST as $field => $value)
			{
				if ($field != "submit-btn")
				{
					$sql_all[] = $query = "UPDATE $table SET field_phi = '1' WHERE project_id = $project_id AND field_name = '".db_escape($field)."'";
					db_query($query);
				}
			}

			// Log the event
			Logging::logEvent(implode(";\n",$sql_all), $table, "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID, "Tag new identifier fields");

			//give user message informing them changes have been made (then hide the message after set time)
			?>
			<p class="darkgreen" style="text-align:center;">
				<img src="<?php echo APP_PATH_IMAGES ?>tick.png">
				<b><?php echo $lang["control_center_48"] ?></b>
			</p>
			<script type="text/javascript">
			setTimeout(function(){
				$('.darkgreen').hide('slow');
			},2500);
			</script>
			<?php
		}

		//Pre-draft mode: Prompt user to enter draft mode
		if ($draft_mode == 0 && $status > 0)
		{
			print  "<div class='yellow' style='margin-top:25px;'>
						<img src='" . APP_PATH_IMAGES . "exclamation_orange.png'>
						<b>{$lang['global_02']}:</b> {$lang['identifier_check_02']} {$lang['identifier_check_04']}
						<a href='" . APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id'>{$lang['design_25']}</a>
						{$lang['identifier_check_03']}
					</div>";
			$this->render('FooterProject.php');
			exit;
		}
		//Draft mode (show changes)
		elseif ($draft_mode == 1 && $status > 0)
		{
			print  "<div class='yellow' style='margin-top:25px;'>
						<b>{$lang['design_14']}</b> {$lang['design_177']}
						{$lang['identifier_check_04']} <a href='" . APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id'>{$lang['design_25']}</a>
						{$lang['identifier_check_05']}
					</div>";

		}
		//Post-draft mode: Waiting approval from administrator
		elseif ($draft_mode == 2 && $status > 0)
		{
			print  "<div class='yellow' style='margin-top:25px;max-width:850px;'>
						<b>{$lang['design_22']}</b><br><br>
						{$lang['design_23']}
					</div>";
			// Footer
			$this->render('FooterProject.php');
			exit;
		}

		// Keywords to use in query (get from config table and parse into $identifiers array)
		$identifiers = IdentifierCheck::getKeywordArray();

		// Set WHERE clause in query
		$whereFieldName    = "";
		$whereElementLabel = "";
		if (!empty($identifiers)) {
			$whereFieldName    = "(a.field_name LIKE '%" . implode("%' OR a.field_name LIKE '%", $identifiers) . "%') OR";
			$whereElementLabel = "(a.element_label LIKE '%" . implode("%' OR a.element_label LIKE '%", $identifiers) . "%') OR";
		}

		$query = "SELECT a.field_name, a.field_phi, a.form_name, a.field_order, a.element_label
				  FROM $table a
				  WHERE a.project_id = $project_id AND
				  ($whereFieldName $whereElementLabel a.field_phi = '1' 
				  OR a.element_validation_type in ('date','date_ymd','date_mdy','date_dmy','phone','email','zipcode'))
				  ORDER BY a.field_order";
		$result = db_query($query);

		// Instructions
		echo "<p>{$lang['identifier_check_06']}</p>";

		// Build table with all relevent fields to check off
		echo '<div style="max-width:700px;">';
		echo '<form action="'.$_SERVER['REQUEST_URI'].'" method="post">';
		echo '<table width="100%">';
		$prevForm = "";
		while ($row = db_fetch_assoc($result))
		{
			$selected = ($row['field_phi'] == "1") ? "checked" : "";

			if ($prevForm != $row['form_name'])
			{
				echo "<tr><td colspan='3' style='border-top:1px solid #ccc;'><h4 style='color:#800000;'>" . $Proj->forms[$row['form_name']]['menu'] . "</h4></td></tr>";
				echo '<tr>
							<td><b>'.$lang['global_44'].'</b></td>
							<td style="padding-left:5px;"><b>'.$lang['global_40'].'</b></td>
							<td style="text-align: center;"><b>'.$lang['database_mods_73'].'</b></td>
					  </tr>';
				$prevForm = $row['form_name'];
			}

			echo '<tr>
					<td>'.$row['field_name'].'</td>
					<td style="padding-left:5px;">'.RCView::escape(strip_tags(label_decode($row['element_label']))).'</td>
					<td style="text-align: center;"><input type="checkbox" name="'.$row['field_name'].'" value="1" '.$selected.'/></td>
				  </tr>';
		}

		echo '<tr><td colspan="3" style="padding:15px;text-align:center;border-top:1px solid #ccc;">
				<input type="submit" name="submit-btn" value="'.js_escape2($lang['identifier_check_07']).'" />
				<input type="button" onclick="window.location.href=app_path_webroot+\'ProjectSetup/index.php?pid=\'+pid;" value="'.$lang['global_53'].'" />
			  </td></tr>';
		echo '</table>';
		echo '</form>';
		echo '</div>';

		// Project footer
		$this->render('FooterProject.php');
	}
}