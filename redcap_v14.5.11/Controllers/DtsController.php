<?php

class DtsController extends Controller
{
	private $_connection;

	public function __construct($conn)
	{
		global $dtsHostname, $dtsUsername, $dtsPassword, $dtsDb;

		if (empty($conn))
		{
			$dts_conn = mysqli_connect(remove_db_port_from_hostname($dtsHostname), $dtsUsername, $dtsPassword, $dtsDb, get_db_port_by_hostname($dtsHostname));
			if (!$dts_conn) die("Failed to connect to the dts database server");

			$this->_connection = $dts_conn;
		}
		else
		{
			$this->_connection = $conn;
		}
	}
	
	// Render the DTS adjudication table page
	public function adjudication()
	{
		global $project_id, $dtsHostname, $dtsUsername, $dtsPassword, $dtsDb, $redcap_id, $dts;

		$redcap_id = $_GET['id'];
		$existingValues = array();
		$deniedRecommendations = array();
		
		// Project header
		$this->render('HeaderProject.php', $GLOBALS);

		// create DTS object to get data
		$dts_connection = mysqli_connect(remove_db_port_from_hostname($dtsHostname), $dtsUsername, $dtsPassword, $dtsDb, get_db_port_by_hostname($dtsHostname));
		if (!$dts_connection) die("Failed to connect to the dts database server");
		$dts = new DtsController($dts_connection);
		?>

		<script type='text/javascript'>
		jQuery(document).ready(function() {
			jQuery(":radio").click(function() {
				var group = jQuery(this).prop("name");
				var color = jQuery(":radio[name="+group+"]").eq(0).parent().prev().css("background-color");

				jQuery(":radio[name="+group+"]").each(function() {
					if (jQuery(this).is(":checked")) {
						if (jQuery(this).val() == "rejectAll") {
							jQuery(this).parent().css("background-color", "#D17373");
							jQuery(this).parent().prev().css("background-color", "#D17373");
						}
						else {
							jQuery(this).parent().css("background-color", "#73D173");
						}

						jQuery(this).parent().children("a").remove();
						jQuery(this).parent().append("<a href='javascript:;' class='reset' style='font-size:8pt; color: black;'>reset</a>");
						jQuery(this).parent().find(":hidden").val("Accepted");
					}
					else {
						jQuery(this).parent().css("background-color", color);
						jQuery(this).parent().children("a").remove();
						jQuery(this).parent().find(":hidden").val("Rejected");

						if (jQuery(this).val() == "rejectAll") {
							jQuery(this).parent().prev().css("background-color", color);
						}
					}
				});
			});

			jQuery("a.reset").on("click", function() {
				var group = jQuery(this).parent().children(":radio").prop("name");
				var color = jQuery(":radio[name="+group+"]").eq(0).parent().prev().css("background-color");

				jQuery(":radio[name="+group+"]").each(function() {
					if (jQuery(this).is(":checked")) {
						jQuery(this).parent().css("background-color", color);
						jQuery(this).prop("checked", false);
						jQuery(this).parent().children("a").remove();

						if (jQuery(this).val() == "rejectAll") {
							jQuery(this).parent().prev().css("background-color", color);
						}
					}
					jQuery(this).parent().find(":hidden").val("Pending");
				});
			});

			jQuery(".notes[title]").tooltip2({
				position: 'bottom right',
				delay: 0
			});
		});
		</script>

		<?php

		renderPageTitle("<i class=\"fas fa-database\"></i> Data Adjudication Table<br/>");

		if (isset($_POST['cancel']))
		{
			redirect(APP_PATH_WEBROOT . "index.php?pid=" . $project_id . "&route=DtsController:adjudication");
		}
		else if (isset($_POST['save']))
		{
			//get statuses
			$statusResult = $dts->getTransferRecommendationStatuses();
			$transfer_recommendation_statuses = array();
			while ($row = db_fetch_assoc($statusResult)) {
				$transfer_recommendation_statuses[$row['description']] = $row['id'];
			}

			// get recommendations
			$recommendations = DtsController::getRecommendations();

			@db_query("START TRANSACTION", $rc_connection);
			@db_query("START TRANSACTION", $dts_connection);

			try
			{
				//process the recomendations
				foreach($recommendations as $project_recommendation)
				{
					if ($_POST["status_".$project_recommendation["id"]] == 'Pending')
					{
						//nothing has been decided for this item so keep as pending and just update the date
						$query = "UPDATE transfer_recommendation
								  SET updated_at = '".NOW."'
								  WHERE id = ".$project_recommendation["id"];
						if (!db_query($query, [], $dts_connection)) throw new Exception(db_error());
						continue;
					}
					else if ($_POST["status_".$project_recommendation["id"]] == 'Delete')
					{
						//delete this item because the date has changed in redcap
						$query = "DELETE FROM transfer_recommendation
								  WHERE id = ".$project_recommendation["id"];
						if (!db_query($query, [], $dts_connection)) throw new Exception(db_error());
						continue;
					}
					else
					{
						if($_POST["status_".$project_recommendation["id"]] == 'Accepted')
						{
							//try to find this row in redcap
							$query = "SELECT *
									  FROM ".\Records::getDataTable($project_id)."
									  WHERE project_id = $project_id
										AND event_id = ".$project_recommendation["event_id"]."
										AND record = '$redcap_id'
										AND field_name = '".$project_recommendation["target_field"]."'";
							$rowCount = db_num_rows(db_query($query));

							if($rowCount == 0)	//if no row found insert one
							{
								$query = "INSERT INTO ".\Records::getDataTable($project_id)."
											(project_id,
											event_id,
											record,
											field_name,
											value)
										  VALUES
											($project_id,
											".$project_recommendation["event_id"].",
											'$redcap_id',
											'".$project_recommendation["target_field"]."',
											'".trim($project_recommendation["source_value"])."')";
								if (!db_query($query)) throw new Exception(db_error());
							}
							else	//row found, update it
							{
								$query = "UPDATE ".\Records::getDataTable($project_id)."
										  SET value = '".trim($project_recommendation["source_value"])."'
										  WHERE project_id = $project_id
											AND event_id = ".$project_recommendation["event_id"]."
											AND record = '$redcap_id'
											AND field_name = '".$project_recommendation["target_field"]."'";
								if (!db_query($query)) throw new Exception(db_error());
							}

							//log information
							$display = $project_recommendation["target_field"]." = ".$project_recommendation["source_value"];
							Logging::logEvent($query, "redcap_data", "update", $redcap_id, $display, "Update record (DTS)");

							//mark as accepted
							$query = "UPDATE transfer_recommendation
									  SET trans_rec_status_id = ".$transfer_recommendation_statuses['Accepted']."
										, updated_at = '".NOW."'
									  WHERE id = ".$project_recommendation["id"];
							if (!db_query($query, [], $dts_connection)) throw new Exception(db_error());
						}
						else
						{
							//mark as rejected
							$query = "UPDATE transfer_recommendation
									  SET trans_rec_status_id = ".$transfer_recommendation_statuses['Rejected']."
										, updated_at = '".NOW."'
									  WHERE id = ".$project_recommendation["id"];
							if (!db_query($query, [], $dts_connection)) throw new Exception(db_error());
						}
					}
				}

				//commit to redcap first so if that fails we will rollback both.  We do not want to remove
				//data from dts without ensuring it made it to redcap
				@db_query("COMMIT", $rc_connection);
				@db_query("COMMIT", $dts_connection);

				print "<br/><div class='green'>The data for subject <b>$redcap_id</b> has been saved</div><br/>";

				DtsController::printAdjudicationTop();

				print "</table>
					   </form>";
			}
			catch (Exception $e)
			{
				@db_query("ROLLBACK", $dts_connection);
				@db_query("ROLLBACK", $rc_connection);
				print("<br/>Could not save, caught exception: ".$e->getMessage()."\n");
			}
		}
		else if (isset($_POST['review']))
		{
			try
			{
				// get recommendations
				$recommendations = DtsController::getRecommendations();

				// set header for one of the columns based of the type of project
				$columnHeader = $longitudinal ? "Event": "Date";

				print '<form id="searchForm" enctype="application/x-www-form-urlencoded" method="post" action="'.
					APP_PATH_WEBROOT.'index.php?pid='.$project_id.'&id='.$redcap_id.'&route=DtsController:adjudication">';

				print "<table class='form_border' style='width:800px;'>
						<tr>
							<td class='context_msg'  colspan='9'>
								<div class='blue' style='max-width: 100%;'>
									<img src='".APP_PATH_IMAGES."pencil.png'> Review Adjudication for REDCap ID: $redcap_id
								</div>
							</td>
						</tr>";

				print "<tr>
							<td class='labelrc'>Form</td>
							<td class='labelrc'>$columnHeader</td>
							<td class='labelrc'>REDCap Variable</td>
							<td class='labelrc'>REDCap Date</td>
							<td class='labelrc'>Source Date</td>
							<td class='labelrc'>REDCap Value</td>
							<td class='labelrc'>Source Value</td>
							<td class='labelrc'>Info</td>
							<td class='labelrc'>Status</td>
						</tr>\n";

				$prevRow = "";
				$currentRow = "";
				$prevEvent = "";
				//$counter = 1;
				$altClass = "adjudicateData";
				$prevDisabled = false;

				foreach($recommendations as $i => $row)
				{
					$currentRow = $row["form_name"] . "-" . $row["event_id"] . "-" . $row["target_field"];
					$headerId = $longitudinal ? $row["event_id"] : $row["form_name"];
					$header = $longitudinal ? $row["event_name"] : $row["form_name_long"];

					if ($prevRow != $currentRow)
					{
						if ($prevEvent != $headerId)
						{
							print '<tr><td colspan="9" style="background-color: #000; color: #fff; padding: 3px; font-weight: bold;">'.$header.'</td></tr>';
							if ($row["disabled"]) {
								print '<tr><td colspan="9" style="background-color: #B0B0B0; padding: 3px;">
									Source data was pulled for this timepoint, however, the date in REDCap was changed.  Because of that, the source data
									needs to be refreshed. Upon saving, the subject will be queued again.</td></tr>';
							}
						}
						$prevEvent = $headerId;

						if ( !$row["disabled"] )
						{
							print "<tr>";
							print "<td class='$altClass'>" . $row["form_name"] . "</td>";
							print "<td class='$altClass'>" . $row["event_name"] . "</td>";
							print "<td class='$altClass'>" . $row["target_field"] . "</td>";
							print "<td class='$altClass' style='white-space: nowrap;'>" . $row["event_date"] . "</td>";
						}
					}
					else
					{
						if ( !$row["disabled"] )
						{
							print "<tr>";
							print "<td colspan='4'>&nbsp;</td>";
						}
					}

					if ( !$row["disabled"] )
					{
						$sourceDate = date("Y-m-d", strtotime($row["date_of_service"]));
						if (date("H:i", strtotime($row["date_of_service"])) != "00:00") {
							$sourceDate .= "&nbsp;&nbsp;<span style='font-size: smaller;'>".date("H:i", strtotime($row["date_of_service"]))."</span>";
						}
						print "<td class='$altClass' style='white-space: nowrap;'>$sourceDate</td>";

						print "<td class='$altClass'>" . $row["redcap_value"] . "</td>";
						print "<td class='$altClass'>" . $row["source_value"] . "</td>";
						$comment = ($row['source_comment'] == '') ? "" : "title='".$row['source_comment']."'";
						print "<td class='$altClass' style='vertical-align: middle; text-align: center;'><img class='notes'
								src='".APP_PATH_IMAGES."help.png' $comment /></td>";

						$adjudication_status = $_POST["status_".$row["id"]];

						if ($adjudication_status == "Accepted")
							$cellColor = "#73D173";
						else if  ($adjudication_status == "Rejected" || $adjudication_status == "Delete")
							$cellColor = "#D17373";
						else
							$cellColor = "";

						print "<td class='$altClass' style='background-color: $cellColor'>
									".htmlspecialchars($adjudication_status, ENT_QUOTES)."
									<input type='hidden' name='status_". $row["id"] ."' value='".htmlspecialchars($adjudication_status, ENT_QUOTES)."'>
							  </td>";
						print "</tr>\n";
					}
					else
					{
						print "<tr><td colspan='9'><input type='hidden' name='status_".$row["id"]."' value='Delete' /></td></tr>\n";
					}

					$prevRow = $currentRow;
					$prevDisabled = $row["disabled"] ? true : false;
				}

				print	"<tr>
							<td class='labelrc' colspan = '9'>
								<div style='float:right'>
									<input type='submit' name='cancel' id='cancel' value='Cancel' />
									<input type='submit' name='save' id='save' value='Save Data for this Subject' />
								</div>
							</td>
						</tr>
					</table>
					</form>";
			}
			catch(Exception $e)
			{
				echo 'Caught exception: ',  $e->getMessage(), "\n";
			}
		}
		else
		{
			if (empty($redcap_id))
			{
				DtsController::printAdjudicationTop();

				print "</table>
					   </form>";
			}
			else
			{
				//display the data for adjudication
				try
				{
					// get recommendations to display
					$recommendations = DtsController::getRecommendations();

					// show instructions and legend
					DtsController::printAdjudicationTop();

					// set header for one of the columns based of the type of project
					$columnHeader = $longitudinal ? "Event": "Date";

					print "<tr>
								<td class='labelrc'>Form</td>
								<td class='labelrc'>$columnHeader</td>
								<td class='labelrc'>REDCap Variable</td>
								<td class='labelrc'>REDCap Date</td>
								<td class='labelrc'>Source Date</td>
								<td class='labelrc'>REDCap Value</td>
								<td class='labelrc'>Source Value</td>
								<td class='labelrc'>Info</td>
								<td class='labelrc'>Status</td>
							</tr>\n";

					$prevRow = "";
					$currentRow = "";
					$prevEvent = "";
					//$counter = 1;
					$altClass = "adjudicateData";
					$prevDisabled = false;

					foreach($recommendations as $i => $row)
					{
						$currentRow = $row["form_name"] . "-" . $row["event_id"] . "-" . $row["target_field"];
						$headerId = $longitudinal ? $row["event_id"] : $row["form_name"];
						$header = $longitudinal ? $row["event_name"] : $row["form_name_long"];

						if ($prevRow != $currentRow)
						{
							if ($i != 0 & !$prevDisabled)
							{
								print "<tr>
											<td colspan='6'>&nbsp;</td>
											<td colspan='2' class='$altClass' style='text-align: right; border-right: 0;'>Reject All</td>
											<td class='$altClass' style='border-left: 0; white-space: nowrap;'>
												<input type='radio' name='$prevRow' value='rejectAll' />
											</td>
									   </tr>\n";
							}

							if ($prevEvent != $headerId)
							{
								print '<tr><td colspan="9" style="background-color: #000; color: #fff; padding: 3px; font-weight: bold;">'.$header.'</td></tr>';
								if ($row["disabled"]) {
									print '<tr><td colspan="9" style="background-color: #B0B0B0; padding: 3px;">
										Source data was pulled for this timepoint, however, the date in REDCap was changed.  Because of that, the source data
										needs to be refreshed. Upon saving, the subject will be queued again.</td></tr>';
								}
							}
							$prevEvent = $headerId;

							if ( !$row["disabled"] )
							{
								print "<tr>";
								print "<td class='$altClass'>" . $row["form_name"] . "</td>";
								print "<td class='$altClass'>" . $row["event_name"] . "</td>";
								print "<td class='$altClass'>" . $row["target_field"] . "</td>";
								print "<td class='$altClass' style='white-space: nowrap;'>" . $row["event_date"] . "</td>";
							}
						}
						else
						{
							if ( !$row["disabled"] )
							{
								print "<tr>";
								print "<td colspan='4'>&nbsp;</td>";
							}
						}

						if ( !$row["disabled"] )
						{
							$sourceDate = date("Y-m-d", strtotime($row["date_of_service"]));
							if ( date("H:i", strtotime($row["date_of_service"])) != "00:00" )
								$sourceDate .= "&nbsp;&nbsp;<span style='font-size: smaller;'>".date("H:i", strtotime($row["date_of_service"]))."</span>";

							if ( $row["event_date"] == date("Y-m-d", strtotime($row["date_of_service"])) && $row["disabled"] == false) {
								print "<td class='$altClass' style='white-space: nowrap; background: #73D173;'>$sourceDate</td>";
							}
							else {
								print "<td class='$altClass' style='white-space: nowrap;'>$sourceDate</td>";
							}

							$redcapValue = $row["redcap_value"];
							if($redcapValue != "")
							{
								if($redcapValue == $row["source_value"]) {
									print "<td class='$altClass' style='background: #73D173;'>";
								}
								else {
									print "<td class='$altClass' style='background: #DD9A00;'>";
								}
							}
							else
							{
								print "<td class='$altClass'>";
							}
							print $redcapValue;
							print "</td>";
							print "<td class='$altClass'>" . $row["source_value"] . "</td>";
							$comment = ($row['source_comment'] == '') ? "" : "title='".$row['source_comment']."'";
							print "<td class='$altClass' style='vertical-align: middle; text-align: center;'><img class='notes'
									src='".APP_PATH_IMAGES."help.png' $comment /></td>";
							print "<td class='$altClass' style='text-align: left; white-space: nowrap;'>";

							print "<input type='hidden' name='status_".$row["id"]."' value='Pending' />";
							print "<input type='radio' name='$currentRow' value='accept' /> ";

							print "</td>";
							print "</tr>\n";
						}
						else
						{
							print "<tr><td colspan='9'><input type='hidden' name='status_".$row["id"]."' value='Delete' /></td></tr>\n";
						}

						if ( count($recommendations) == $i+1 & !$row["disabled"] )
						{
							print "<tr>
										<td colspan='6'>&nbsp;</td>
										<td colspan='2' class='$altClass' style='text-align: right; border-right: 0;'>Reject All</td>
										<td class='$altClass' style='border-left: 0; white-space: nowrap;'>
											<input type='radio' name='$currentRow' value='rejectAll' />
										</td>
								   </tr>\n";
						}

						$prevRow = $currentRow;
						$prevDisabled = $row["disabled"] ? true : false;
					}

					print "<tr>
								<td class='labelrc' colspan ='9'>
									<div style='float:right;'>
										<input type='submit' name='review' id='review' value='Review Decisions' ";
											if(count($recommendations) == 0) print " disabled ";
					print 				"/>
									</div>
								</td>
							</tr>
						</table>
						</form>";
				}
				catch(Exception $e)
				{
					echo 'Caught exception: ',  $e->getMessage(), "\n";
				}
			}
		}
		
		// Reset db connection
		db_connect();
		// Project footer
		$this->render('FooterProject.php');
	}
	
	public static function printAdjudicationTop()
	{
		global $project_id, $redcap_id, $dts, $deniedRecommendations, $rc_connection;

		print  "<div style='font: normal 12px Verdana, Arial, Helvetica, sans-serif; padding: 6px 6px 6px 6px; border: 1px solid #A7C3F1;
					color: #000066; max-width: 788px; background: #E2EAFA url(../images/blue-bg.gif) repeat-x scroll 0 0;'>

					<b style='font-size: 15px;'>Instructions for Data Adjudication</b> <br/><br/>
					Automatically collected data from other institutional source systems are displayed in the Adjudication Data Table below.<br/><br/>

					For each data point below you must <b>assign a Status</b>:<br/><br/>

					Select <b>Accepted</b> when the data values displayed should be imported into the database.<br/>
					Select <b>Rejected</b> when the data has a service date that indicates it does not correspond to the database.<br/>
					Select <b>Pending</b> if you wish to review the data at a later time.<br/><br/>

					After adjudicating the data,<b> click the Review Data button at the bottom of the page </b> to review your selections.<br/><br/>

					<table>
					<tr>
						<td style = 'color:white;background-color:black'><b>KEY for Adjudication Data Table Below</b></td>
					</tr>
					<tr>
						<td class='data' style='background: #73D173;'>existing data in REDCap for this event & patient with same values</td>
					</tr>
					<tr>
						<td class='data' style='background: #DD9A00;'>existing data in REDCap project for this event & patient with discrepant value(s)</td>
					</tr>
					<tr>
						<td class='data' style='background: #B0B0B0;'>source system data that was recommended prior to an event date change in REDCap</td>
					</tr>
					</table>
				</div>";

		if (count($deniedRecommendations) > 0)
		{
			print "<div id='adjudicationDeniedMsg' class='red' style='max-width: 788px; margin-top: 20px;'>
						<img src='".APP_PATH_IMAGES."exclamation.png'/>
						Some recommendations were blocked because you do not have edit rights to the following forms:<br/>";

						foreach($deniedRecommendations as $key => $value)
						{
							$query = "SELECT form_menu_description
									  FROM redcap_metadata
									  WHERE project_id = $project_id
										AND form_menu_description IS NOT NULL
										AND form_name = '".$value."'";
							$result = db_query($query);
							$description = db_result($result, 0);

							echo "<b>$description</b><br/>";
						}

			print "	   </div>";
		}

		print '<form id="searchForm" enctype="application/x-www-form-urlencoded" method="post" action="'.
			APP_PATH_WEBROOT.'index.php?pid='.$project_id.'&id='.$redcap_id.'&route=DtsController:adjudication">';

		print  "<br/><div id = 'adjudicationErrorMsg' class='red' style='width: 100%; display: none;'>
					<img src='".APP_PATH_IMAGES."exclamation.png'/>
				</div><br/>";

		print  "<table id='adjudicationTable' class='form_border' style='width:800px;'>
				<tr class='blue'>
					<td colspan='9'>
						<div style='max-width: 100%;'>
							<img src='".APP_PATH_IMAGES."pencil.png'> Adjudication for REDCap Study ID: ";

		print '<select class="x-form-text x-form-field" style="" name="redcap_id"
					onChange="if(this.value.length>0){window.location.href=\''.APP_PATH_WEBROOT.'index.php?pid='.$project_id.'&route=DtsController:adjudication&id=\'+this.value;}">';
		print '<option value="" label="Select">Select</option>';

		$redcapIds = $dts->getDistinctRedcapIds($project_id);
		while ($row = db_fetch_assoc($redcapIds))
		{
			$value = $row['redcap_id'];
			print '<option value="'.$value.'" ';
			if($value == $redcap_id & !isset($_POST['save'])) print ' selected="selected"';
			print '>'.$value.'</option>';
		}

		print '</select>
				</div>
				</td>
				</tr>';
	}

	public static function getRecommendations()
	{
		global $longitudinal, $multiple_arms, $project_id, $redcap_id, $existingValues, $rc_connection, $dts,
			$deniedRecommendations, $super_user;

		// Get all pending items from the DTS
		$recommendationResults = $dts->findPendingByRedcapId($project_id,$redcap_id);

		$recommendations = array();
		$events = array();
		$sortedRecommendations = array();

		// need to get the user rights
		$query = "SELECT * FROM redcap_user_rights WHERE project_id = $project_id AND username = '".$GLOBALS['userid']."'";
		$rights = db_fetch_assoc(db_query($query));

		$userRights = array();
		$dataEntryArray = UserRights::convertFormRightsToArrayPre($rights['data_entry']);
		foreach ($dataEntryArray as $item) {
			list($this_form, $this_form_rights) = explode(",", $item);
			$userRights[$this_form] = $this_form_rights;
		}

		if ($longitudinal)
		{
			// move resultset into an array for easier processing and sorting
			while ($row = db_fetch_assoc($recommendationResults)) {
				$recommendations[$row['event_id']][] = $row;
			}

			// Get list of events for the project
			$query = "SELECT rea.arm_id, rea.arm_name, rem.event_id, rem.descrip, day_offset
					  FROM redcap_events_arms rea
					  LEFT JOIN redcap_events_metadata rem ON rea.arm_id = rem.arm_id
					  WHERE rea.project_id = $project_id
					  ORDER BY rea.arm_num, rem.day_offset, rem.descrip";
			$eventTable = db_query($query);

			while ($row = db_fetch_assoc($eventTable))
			{
				if ($multiple_arms)
					$events[$row['event_id']] = $row['arm_name'] . " - " . $row['descrip'];
				else
					$events[$row['event_id']] = $row['descrip'];
			}

			foreach($events as $id => $event)
			{
				$prevField = "";
				$formName = "";
				$redcapValue = "";
				$redcapEventDate = "";

				foreach($recommendations[$id] as $row)
				{
					//lookup the form name for this recommendation
					$query = "SELECT form_name FROM redcap_metadata WHERE project_id = $project_id AND field_name = '".$row['target_field']."'";
					$fieldInfo = db_fetch_assoc(db_query($query));

					//security check
					if ( !$super_user && (!array_key_exists($fieldInfo["form_name"], $userRights) || $userRights[$fieldInfo["form_name"]] != 1) ) {
						if (!in_array($fieldInfo["form_name"], $deniedRecommendations)) $deniedRecommendations[] = $fieldInfo["form_name"];
						continue;
					}

					// need to set the full event name for longitudinal projects and use the date field for classic
					if($longitudinal) {
						$row['event_name'] = $events[$row['event_id']];
					}
					else {
						$row['event_name'] = $row['event_field'];
					}

					if ($row["target_field"] != $prevField)
					{
						//check to see if the dates still match up for this recommendation with the corresponding event in REDCap.
						$query = "SELECT value
								  FROM ".\Records::getDataTable($project_id)."
								  WHERE project_id = $project_id
									AND event_id = ".$row['event_id']."
									AND record = '$redcap_id'
									AND field_name = '".$row['event_field']."'";
						$redcapEventDate = db_result(db_query($query), 0);

						//lookup the form name
						$query = "SELECT form_name
								  FROM redcap_metadata
								  WHERE project_id = $project_id
									AND field_name = '".$row["target_field"]."'";
						$formName = db_result(db_query($query), 0);
						$prevField = $row["target_field"];

						//now look for data that already exists in REDCap
						$query = "SELECT value
								  FROM ".\Records::getDataTable($project_id)."
								  WHERE project_id = $project_id
									AND event_id = ".$row['event_id']."
									AND record = '$redcap_id'
									AND field_name = '".$row['target_field']."'";
						$redcapValue = db_result(db_query($query), 0);
					}

					// if the dates do not match disable this recommendation
					if($redcapEventDate != $row['event_date']) {
						$row['disabled'] = true;
					}

					// set the form name for this recommendation
					$row["form_name"] = $formName;

					// set the value currently in redcap for this field
					$row['redcap_value'] = $redcapValue;

					$sortedRecommendations[] = $row;
				}
			}
		}
		else
		{
			$prevField = "";
			$formName = "";
			while ($row = db_fetch_assoc($recommendationResults))
			{
				if ($row['target_field'] != $prevField)
				{
					$query = "SELECT * FROM redcap_metadata WHERE project_id = $project_id AND field_name = '".$row['target_field']."'";
					$formName = db_result(db_query($query), 0, "form_name");
				}
				$recommendations[$formName][] = $row;
			}

			$query = "SELECT form_name, form_menu_description
					  FROM redcap_metadata
					  WHERE project_id = $project_id
						AND form_menu_description is not null
					  ORDER BY field_order";
			$formResults = db_query($query);

			while ($form = db_fetch_assoc($formResults))
			{
				$prevField = "";
				$formName = "";
				$redcapValue = "";
				$redcapEventDate = "";

				foreach($recommendations[$form["form_name"]] as $row)
				{
					//security check
					if ( !$super_user && (!array_key_exists($form["form_name"], $userRights) || $userRights[$form["form_name"]] != 1) ) {
						if (!in_array($form["form_name"], $deniedRecommendations)) $deniedRecommendations[] = $form["form_name"];
						continue;
					}

					// need to set the full event name for longitudinal projects and use the date field for classic
					$row['event_name'] = $row["event_field"];

					if ($row["target_field"] != $prevField)
					{
						//check to see if the dates still match up for this recommendation with the corresponding event in REDCap.
						$query = "SELECT value
								  FROM ".\Records::getDataTable($project_id)."
								  WHERE project_id = $project_id
									AND event_id = ".$row['event_id']."
									AND record = '$redcap_id'
									AND field_name = '".$row['event_field']."'";
						$redcapEventDate = db_result(db_query($query), 0);

						//now look for data that already exists in REDCap
						$query = "SELECT value
								  FROM ".\Records::getDataTable($project_id)."
								  WHERE project_id = $project_id
									AND event_id = ".$row['event_id']."
									AND record = '$redcap_id'
									AND field_name = '".$row['target_field']."'";
						$redcapValue = db_result(db_query($query), 0);

						$prevField = $row["target_field"];
					}

					// if the dates do not match disable this recommendation
					if($redcapEventDate != $row['event_date']) {
						$row['disabled'] = true;
					}

					// set the form name for this recommendation
					$row["form_name"] = $form["form_name"];
					$row["form_name_long"] = $form["form_menu_description"];
					$row['unique_row_key'] = $row["form_name"] . "-" . $row["event_id"] . "-" . $row["target_field"];

					// set the value currently in redcap for this field
					$row['redcap_value'] = $redcapValue;

					$sortedRecommendations[] = $row;
				}
			}
		}

		return $sortedRecommendations;
	}

	public function getDistinctRedcapIds($id)
	{
		$query = "SELECT DISTINCT redcap_id
			  FROM project_recommendation
			  WHERE redcap_project_id = $id
			  	AND recommendation_status = 'Pending'
			  ORDER BY ABS(redcap_id), redcap_id";
		return db_query($query, [], $this->_connection);
	}

	/**
	 * get the number of pending recomendations for a project
	 *
	 * @param $id
	 * @return int count - the number pending
	 */
	public function getPendingCountByProjectId($id)
	{
		$query = "SELECT count(redcap_project_id) as count, redcap_project_id
				  FROM project_recommendation
				  WHERE redcap_project_id = $id
			  		AND recommendation_status = 'Pending'
				  GROUP by redcap_project_id";
		$count = db_result(db_query($query, [], $this->_connection), 0, 0);
		return $count;
	}

	public function findPendingByRedcapId($project_id,$redcap_id)
	{
		global $longitudinal;

		if ($longitudinal)
		{
			$query = "SELECT *
					  FROM project_recommendation
					  WHERE redcap_project_id = $project_id
					  	AND redcap_id = '$redcap_id'
					  	AND recommendation_status = 'Pending'
					  ORDER BY event_id, target_field, date_of_service";
		}
		else
		{
			$query = "SELECT *
					  FROM project_recommendation
					  WHERE redcap_project_id = $project_id
					  	AND redcap_id = '$redcap_id'
					  	AND recommendation_status = 'Pending'
					  ORDER BY target_field, date_of_service";
		}
		return db_query($query, [], $this->_connection);
	}

	public function getTransferRecommendationStatuses()
	{
		$query = "SELECT * FROM transfer_recommendation_status";
		return db_query($query, [], $this->_connection);
	}
}

