<?php

namespace Vanderbilt\REDCap\Classes\MyCap;

use RCView;
use Webmozart\Assert\Assert;
class Message
{
    /**
     * Announcement is sent to all participants
     * Appointment is sent to a single participant with a custom notification (not implemented)
     * Standard is sent to or from a single participant
     *
     * @var string
     */

    const TOFROM_SERVER = '.Server';

    const ANNOUNCEMENT = '.Announcement';
    const APPOINTMENT = '.Appointment';
    const STANDARD = '.Standard';

    const ANNOUNCEMENTS_PER_PAGE = 100;
    const MESSAGES_PER_PAGE = 100;

    public static $action_needed_messages = 0;
    public $id;
    public $studyId;
    public $type;
    public $to;
    public $from;
    public $body;
    public $sentDate;
    public $receivedDate;
    public $readDate;
    public $repeatInstance;
    public $title;

    /**
     * Get table layout of all Sub tabs for messages - inbox, outbox, announcement
     *
     * @return string
     */
    public static function renderMessagesTabs()
    {
        global $lang;

        print RCView::p(array('class'=>'mt-0 mb-2', 'style'=>'max-width:900px;'), $lang['mycap_mobile_app_873']);
        print MyCap::getMessageContainers();

        $actionNeededCount = self::$action_needed_messages;
        $countBlock = '';
        if ($actionNeededCount > 0) {
            $countBlock = '<span class="badgerc">'.$actionNeededCount.'</span>';
        }

        $tabs = [];
        $tabs["messages"] = array("fa"=>"fas fa-inbox", "label"=>$lang['mycap_mobile_app_417'].$countBlock);
        $tabs["outbox"] = array("fa"=>"fas fa-paper-plane", "label"=>$lang['mycap_mobile_app_418']);
        $tabs["announcements"] = array("fa"=>"fas fa-bullhorn", "label"=>$lang['mycap_mobile_app_419']);

        $tabContent = '<div id="sub-nav" class="project_setup_tabs d-none d-sm-block" style="padding-left: 0px !important;"><ul>';

        foreach ($tabs as $this_param => $this_set) {
            $class = (isset($_GET[$this_param]) &&  $_GET[$this_param] == 1) ? 'class="active"' : '';
            $color = (isset($_GET[$this_param]) &&  $_GET[$this_param] == 1) ? '#3e3e3e' : '#3e3e3ec9';

            $tabContent .= '<li '.$class .'>';
            $url = APP_PATH_WEBROOT . 'MyCapMobileApp/index.php?' . $this_param . '=1&pid=' . PROJECT_ID;
            $tabContent .= '<a href="'. $url .'" style="outline:none;font-size:13px;color:'.$color.';padding:7px 9px;">';
            $tabContent .= '<i class="'.$this_set['fa'].'"></i> ';
            $tabContent .= '<span style="vertical-align:middle;">'.$this_set['label'].'</span></a></li>';
        }
        $tabContent .= '</ul></div>';
        print $tabContent;
    }

    /**
     * Get table layout of all Sub tabs for App Design - About, Contacts, Links, Theme
     *
     * @return string
     */
    public static function renderAppDesignTabs()
    {
        global $lang;

        $tabs = [];
        $tabs["about"] = array("fa"=>"fas fa-home", "label"=>$lang['mycap_mobile_app_02']);
        $tabs["contacts"] = array("fa"=>"fa-regular fa-address-card fs14", "label"=>$lang['mycap_mobile_app_03']);
        $tabs["links"] = array("fa"=>"fas fa-link", "label"=>$lang['mycap_mobile_app_04']);
        $tabs["theme"] = array("fa"=>"fa-solid fa-palette", "label"=>$lang['mycap_mobile_app_05']);
        $tabs["notification"] = array("fa"=>"fas fa-bell me-1", "label"=>$lang['mycap_mobile_app_867']);

        $tabContent = '<div id="sub-nav" class="project_setup_tabs d-none d-sm-block" style="padding-left: 0px !important;"><ul>';

        foreach ($tabs as $this_param => $this_set) {
            $class = (isset($_GET[$this_param]) &&  $_GET[$this_param] == 1) ? 'class="active"' : '';
            $color = (isset($_GET[$this_param]) &&  $_GET[$this_param] == 1) ? '#3e3e3e' : '#3e3e3ec9';

            $tabContent .= '<li '.$class .'>';
            $url = APP_PATH_WEBROOT . 'MyCapMobileApp/index.php?' . $this_param . '=1&pid=' . PROJECT_ID;
            $tabContent .= '<a href="'. $url .'" style="outline:none;font-size:13px;color:'.$color.';padding:7px 9px;">';
            $tabContent .= '<i class="'.$this_set['fa'].'"></i> ';
            $tabContent .= '<span style="vertical-align:middle;">'.$this_set['label'].'</span></a></li>';
        }
        $tabContent .= '</ul></div>';

        // Make Additional Customizations button
        $button = RCView::button(array('class'=>'btn btn-defaultrc btn-xs fs13','style'=>'float: right;','onclick'=>'dialogModifyAppProjectTitle();'), '<i class="fa-solid fa-edit"></i> '.$lang['mycap_mobile_app_691']);

        renderPageTitle("<img src='" . APP_PATH_IMAGES . "mycap_logo_black.png' style='width:35px;position:relative;top:-2px;margin-right:1px;'>&nbsp;" . $lang['mycap_mobile_app_877'].$button);
        print RCView::div(['class'=>'mt-2 mb-3', 'style'=>'max-width:880px;'],
                $lang['mycap_mobile_app_878'] . " " . $lang['mycap_mobile_app_879'] . " " . $lang['mycap_mobile_app_689'] . " " .
                RCView::a(['href' => APP_PATH_WEBROOT."Resources/misc/mycap_help.pdf", 'target' => '_blank', 'class'=>'text-dangerrc', 'style' => 'text-decoration:underline;margin-left:1px;'],
                    '<i class="fa-solid fa-file-pdf me-1"></i>'.$lang['mycap_mobile_app_688']
                ) . $lang['period']
        );
        print $tabContent;
    }

    /**
     * Set number of inbox messages of project those are needed action - to display with "Inbox" and "Messages" tab label
     *
     * @param int $projectId
     * @return void
     */
    public static function setActionNeededInboxMessagesCount($projectId) {
        $allInboxMessages = self::getMessages($projectId, self::STANDARD);
        $num_messages = 0;
        foreach ($allInboxMessages as $message) {
            if ($message['processed'] == 0) {
                $num_messages++;
            }
        }
        self::$action_needed_messages = $num_messages;
    }

    /**
     * Return all messages of specific type - inbox, outbox or announcement
     *
     * @param int $project_id
     * @param string $type
     * @return array $messages
     */
    public static function getMessages($project_id, $type)
    {
        global $user_rights;
        $groupID = ($user_rights['group_id'] != '' ? $user_rights['group_id'] : array());
        $codesList = array();
        $executeSQL = true;
        // Format $codesList as array
        if (!is_array($groupID) && $groupID == '0') {
            // If passing group_id as "0", assume we want to return unassigned records.
        } elseif (!empty($groupID) && is_numeric($groupID)) {
            $codesList = Participant::getParticipantsInDAG(array($groupID));
            if (empty($codesList))  $executeSQL = false;
        } elseif (!is_array($groupID)) {
            $codesList = array();
        }

        $messages = array();

        if (isset($_GET['filterBeginTime']) && $_GET['filterBeginTime'] != '') {
            $filterBeginTimeYmd = \DateTimeRC::format_ts_to_ymd($_GET['filterBeginTime']);
        }
        if (isset($_GET['filterEndTime']) && $_GET['filterEndTime'] != '') {
            $filterEndTimeYmd = \DateTimeRC::format_ts_to_ymd($_GET['filterEndTime']);
        }

        // Get main attributes
        $sql = "SELECT * FROM redcap_mycap_messages WHERE project_id = ".$project_id;
        if ($type == self::ANNOUNCEMENT) {
            $sql .= " AND type = '".self::ANNOUNCEMENT."'";
            // Check if participants listed from same DAG
            if (!empty($codesList)) $sql .= " AND `from` = '".db_escape($user_rights['username'])."'";
            if ($executeSQL == false) $sql .= " AND `from` = '".db_escape($user_rights['username'])."'";
            $sql .= " ORDER BY sent_date DESC";
            $executeSQL = true;
        } else if ($type == self::STANDARD) {
            $sql .= " AND type = '".self::STANDARD."' AND from_server = 0";
            if (!empty($_GET['filterParticipant'])) $sql .= " AND `from` = '".db_escape($_GET['filterParticipant'])."'";
            // Check if participants listed from same DAG
            if (!empty($codesList)) $sql .= " AND `from` IN (".prep_implode($codesList).")";
            $sql .= " ORDER BY received_date DESC";
        } else if ($type == 'outbox') {
            $sql .= " AND type = '".self::STANDARD."' AND from_server = 1 ";
            if (!empty($_GET['filterUser'])) $sql .= " AND `from` = '".db_escape($_GET['filterUser'])."'";
            if (!empty($_GET['filterParticipant'])) $sql .= " AND `to` = '".db_escape($_GET['filterParticipant'])."'";

            // Check if participants listed from same DAG
            if (!empty($codesList)) $sql .= " AND `to` IN (".prep_implode($codesList).")";
            $sql .= " ORDER BY sent_date DESC";
        }

        if ($executeSQL) {
            $q = db_query($sql);
            while ($row = db_fetch_assoc($q)) {
                $date = $row['received_date'];
                if ($type == 'outbox') {
                    $date = $row['sent_date'];
                }
                // Filter by begin time - Sent On
                if (isset($filterBeginTimeYmd) && substr($date, 0, 16) < $filterBeginTimeYmd) {
                    unset($row);
                    continue;
                }
                // Filter by end time - Sent On
                if (isset($filterEndTimeYmd) && substr($date, 0, 16) > $filterEndTimeYmd) {
                    unset($row);
                    continue;
                }
                // Add to messages array
                $messages[] = $row;
            }
        }

        // If no messages, then return empty array
        if (empty($messages)) return array();

        return $messages;
    }

    /**
     * Render Announcement listing html
     *
     * @return string
     */
    public static function renderAnnouncementList()
    {
        global $lang, $user_rights, $Proj;

        // Is user in a DAG? If so, display their DAG name.
        $dagDisplay = ($user_rights['group_id'] == '') ? '' : $Proj->getGroups($user_rights['group_id']);
        if ($dagDisplay != '') {
            $dagDisplay = "<span style='color:#008000; font-weight: normal;'> ($dagDisplay)</span>";
        }

        renderPageTitle("<div style='float:left;'>".$lang['mycap_mobile_app_419'].$dagDisplay."</div><br>");
        print RCView::p(array('class'=>'mt-0 mb-2', 'style'=>'max-width:900px;'), $lang['mycap_mobile_app_875']);
        // Get list of announcements to display as table
        $announcements_list = self::getMessages(PROJECT_ID, self::ANNOUNCEMENT);

        ## BUILD THE DROP-DOWN FOR PAGING THE ANNOUNCEMENTS
        // Get announcement count
        $announcementCount = count($announcements_list);
        // Section the Announcements into multiple pages
        $num_per_page = self::ANNOUNCEMENTS_PER_PAGE;
        // Calculate number of pages for dropdown
        $num_pages = ceil($announcementCount/$num_per_page);
        // Limit
        $limit_begin = 0;
        if (!isset($_GET['pagenum'])) $_GET['pagenum'] = 1;
        if (isset($_GET['pagenum']) && $_GET['pagenum'] == 'last') {
            $_GET['pagenum'] = $num_pages;
        }
        if (isset($_GET['pagenum']) && is_numeric($_GET['pagenum']) && $_GET['pagenum'] > 1) {
            $limit_begin = ($_GET['pagenum'] - 1) * $num_per_page;
        }
        ## Build the paging drop-down for announcements list
        $pageDropdown = "<select onchange='loadAnnouncementList(this.value)' style='vertical-align:middle;font-size:11px;'>";
        //Loop to create options for dropdown
        for ($i = 1; $i <= $num_pages; $i++) {
            $end_num   = $i * $num_per_page;
            $begin_num = $end_num - $num_per_page + 1;
            $value_num = $end_num - $num_per_page;
            if ($end_num > $announcementCount) $end_num = $announcementCount;
            $pageDropdown .= "<option value='$i' " . ($_GET['pagenum'] == $i ? "selected" : "") . ">$begin_num - $end_num</option>";
        }
        $pageDropdown .= "</select>";
        $pageDropdown  = "{$lang['survey_45']} $pageDropdown {$lang['survey_133']} $announcementCount";

        // If viewing ALL announcements, then set $num_per_page to null to return all announcements
        if (isset($_GET['pagenum']) && $_GET['pagenum'] == 'ALL') $num_per_page = null;

        $item_num = 0; // loop counter
        foreach (array_slice($announcements_list, $limit_begin, $num_per_page) as $this_ann => &$attr)
        {
            $announcement_id = $attr['uuid'];
            foreach ($attr as $configKey => $configVal) {
                // Store values in array to convert to JSON to use when loading the dialog
                $info_modal[$item_num][str_replace("_", "-", $configKey)] = $configVal . "";
            }
            // Trim identifier
            $date = \DateTimeRC::format_ts_from_ymd($attr['sent_date']);

            $message = str_replace(array("\n", "\r"), ' ', htmlentities($attr['body']));
            if (strlen($message) > 85) {
                $message = substr($message, 0 , 85) . "...";
            }

            // Add to array
            $ann_list_full[$i] = array();
            $ann_list_full[$i][] = "<div class='wrapemail'>{$date}</div>";
            $ann_list_full[$i][] = "<div class='wrapemail'>{$attr['from']}</div>";
            $ann_list_full[$i][] = "<div class='wrapemail'>{$message}</div>";
            if ($attr['uuid'] != '') {
                $ann_list_full[$i][] = '<a onclick="__rcfunc_editAnnouncementRow'.$item_num.'();" href="javascript:;" style="outline:none;color:green;font-family:Tahoma;font-size:12px;"><i class="fas fa-edit"></i> </a>';
                $ann_list_full[$i][] .= "<script type=\"text/javascript\">function __rcfunc_editAnnouncementRow{$item_num}(){ editAnnouncement(".json_encode($info_modal[$item_num]).",'".$announcement_id."',".$item_num.") }</script>";
                // Increment row counter
                $item_num++;
            } else {
                $ann_list_full[$i][] = '';
            }

            $i++;
            // Remove this row to save memory
            unset($announcements_list[$this_ann]);
        }

        // If no announcements exist yet, render one row to let user know that
        if (empty($ann_list_full))
        {
            // No announcements exist yet
            $ann_list_full[0] = array(RCView::div(array('class'=>'wrap','style'=>'color:#800000;'), $lang['mycap_mobile_app_424']),"","","","");
        }

        // Build announcement list table
        $annTableWidth = 915;
        $annTableHeaders = array();
        $annTableHeaders[] = array(120, $lang['mycap_mobile_app_442']);
        $annTableHeaders[] = array(150, $lang['mycap_mobile_app_435']);
        $annTableHeaders[] = array(524, $lang['messaging_110']);
        $annTableHeaders[] = array(77, $lang['mobile_app_87'], "center", "string", false);

        $announcement_note = ($user_rights['group_id'] == '') ? '' : RCView::div(array('style'=>'font-weight:normal;padding:2px 5px 0 5px;color:#C00000;'), $lang['mycap_mobile_app_555']);

        $annTableTitle = RCView::div(array(),
            RCView::div(array('style'=>'padding:2px 5px 0 5px;float:left;font-size:14px;'),
                $lang['mycap_mobile_app_425'] . RCView::br() .
                RCView::span(array('style'=>'line-height:24px;color:#666;font-size:11px;font-weight:normal;'),
                    $lang['mycap_mobile_app_427']
                )
            ) .
            ## QUICK BUTTONS
            RCView::div(array('style'=>'font-weight:normal;float:left;font-size:11px;padding-left:12px;border-left:1px solid #ccc;'),
                RCView::button(array('class'=>'jqbuttonmed ui-button ui-corner-all ui-widget',
                    'onclick'=>"editAnnouncement('', '', '');"), '<i class="fas fa-plus"></i> '. $lang['mycap_mobile_app_426'])
            )  .
            ## PAGING
            RCView::div(array('style'=>'font-weight:normal;float:right;font-size:11px;padding-left:12px;border-left:1px solid #ccc;'),
                $pageDropdown
            ) .
            RCView::div(array('class'=>'clear'), '') .
            $announcement_note
        );
        // Build Announcement List
        $list = renderGrid("announcement_table", $annTableTitle, $annTableWidth-count($annTableHeaders), "auto", $annTableHeaders, $ann_list_full);
        return "<div class='mt-3'>".$list."</div>";
    }

    /**
     * Render Add/Edit Announcement Forms
     *
     * @return string
     */
    public static function renderAddEditAnnouncementForm() {
        global $lang;

        $warningBox = "<div id='warningBox' class='red' style='margin:15px 0; display: none;'>
                            <img src='".APP_PATH_IMAGES."exclamation.png'>
                            <b>{$lang['global_48']}{$lang['colon']}</b> {$lang['mycap_mobile_app_446']}
                         </div>";
        $form = '<form class="form-horizontal" action="" method="post" id="saveAnnouncement" enctype="multipart/form-data">                
                    <div class="modal fade" id="external-modules-configure-modal-ann" name="external-modules-configure-modal-ann" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true">
                        <div class="modal-dialog" role="document" style="max-width: 950px !important;">
                            <div class="modal-content">
                                <div class="modal-header py-2">
                                    <button type="button" class="py-2 close closeCustomModal" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                                    <h4 id="add-edit-title-text" class="modal-title form-control-custom"></h4>
                                </div>
                                <div class="modal-body pt-2">
                                    <div id="errMsgContainerModal" class="alert alert-danger col-md-12" role="alert" style="display:none;margin-bottom:20px;"></div>
                                    '.$warningBox.'
                                    <table class="code_modal_table" id="code_modal_table_update">
                                        <tr class="form-control-custom" field="">
                                            <td class="align-text-top pt-1 pe-1">
                                                <label class="text-nowrap boldish">'.$lang['messaging_110'].$lang['colon'].'</label><div class="requiredlabel p-0">* '.$lang['data_entry_39'].'</div>
                                            </td>
                                            <td class="external-modules-input-td">
                                                <textarea id="announcement_msg" name="announcement_msg" placeholder="'.$lang['messaging_110'].'" class="external-modules-input-element ms-3" style="max-width:95%;height:150px;"></textarea>
                                            </td>
                                        </tr>
                                    </table>
                                <input type="hidden" value="" id="index_modal_update" name="index_modal_update">
                                </div>
            
                                <div class="modal-footer">
                                    <button class="btn btn-rcgreen" id="btnModalsaveAnn">'.$lang['designate_forms_13'].'</button>
                                    <button class="btn btn-defaultrc" id="btnCloseAnnModal" data-dismiss="modal" onclick="return false;">'.$lang['global_53'].'</button>
                                    <button id="removeButton" type="button" class="ui-button ui-corner-all ui-widget" style="color: rgb(192, 0, 0); font-size: 11px; margin: 9px 0px 0px 40px; display: none;">'.$lang['mycap_mobile_app_444'].'</button>
                                </div>
                            </div>
                        </div>
                    </div>
               </form>';
        $form .= '<div class="modal fade" id="delete-announcement-modal" name="delete-announcement-modal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true">
				<div class="modal-dialog" role="document" style="width: 800px">
					<div class="modal-content">
						<div class="modal-header">
							<button type="button" class="close closeCustomModal" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
							<h4 class="modal-title">'.$lang['mycap_mobile_app_444'].'</h4>
						</div>
						<div class="modal-body">
							<div class="mb-3">
								'.$lang['mycap_mobile_app_443'].'
							</div>
						</div>
						<div class="modal-footer">
							<button data-toggle="modal" class="btn btn-rcred" id="delete-announcement-modal-body-submit" onclick="return false;">'.$lang['mycap_mobile_app_444'].'</button>
							<button class="btn btn-defaultrc" data-dismiss="modal" onclick="return false;">'.$lang['global_53'].'</button>
						</div>
					</div>
				</div>
			</div>';

        return $form;
    }

    /**
     * Render Inbox Messages listing page
     *
     * @return string
     */
    public static function renderInboxMessagesList()
    {
        global $lang, $user_rights, $Proj;

        // Is user in a DAG? If so, display their DAG name.
        $dagDisplay = ($user_rights['group_id'] == '') ? '' : $Proj->getGroups($user_rights['group_id']);
        if ($dagDisplay != '') {
            $dagDisplay = "<span style='color:#008000; font-weight: normal;'> ($dagDisplay)</span>";
        }

        renderPageTitle("<div style='float:left;'>".$lang['mycap_mobile_app_417'].$dagDisplay."</div><br>");
        print RCView::p(array('class'=>'mt-0 mb-2', 'style'=>'max-width:900px;'), $lang['mycap_mobile_app_874']);
        // Get list of messages to display as table
        $messages_list = self::getMessages(PROJECT_ID, self::STANDARD);

        ## BUILD THE DROP-DOWN FOR PAGING THE MESSAGES
        // Get messages count
        $messageCount = count($messages_list);
        // Section the Messages into multiple pages
        $num_per_page = self::MESSAGES_PER_PAGE;
        // Calculate number of pages for dropdown
        $num_pages = ceil($messageCount/$num_per_page);
        // Limit
        $limit_begin  = 0;
        if (!isset($_GET['pagenum'])) $_GET['pagenum'] = 1;
        if (isset($_GET['pagenum']) && $_GET['pagenum'] == 'last') {
            $_GET['pagenum'] = $num_pages;
        }
        if (isset($_GET['pagenum']) && is_numeric($_GET['pagenum']) && $_GET['pagenum'] > 1) {
            $limit_begin = ($_GET['pagenum'] - 1) * $num_per_page;
        }
        ## Build the paging drop-down for messages
        $pageDropdown = "<select onchange='loadInboxMessagesList(this.value)' style='vertical-align:middle;font-size:11px;'>";
        //Loop to create options for dropdown
        for ($i = 1; $i <= $num_pages; $i++) {
            $end_num   = $i * $num_per_page;
            $begin_num = $end_num - $num_per_page + 1;
            $value_num = $end_num - $num_per_page;
            if ($end_num > $messageCount) $end_num = $messageCount;
            $pageDropdown .= "<option value='$i' " . ($_GET['pagenum'] == $i ? "selected" : "") . ">$begin_num - $end_num</option>";
        }
        $pageDropdown .= "</select>";
        $pageDropdown  = "{$lang['survey_45']} $pageDropdown {$lang['survey_133']} $messageCount";

        // If viewing ALL messages, then set $num_per_page to null to return all messages
        if (isset($_GET['pagenum']) && $_GET['pagenum'] == 'ALL') $num_per_page = null;

        $item_num = 0; // loop counter
        foreach (array_slice($messages_list, $limit_begin, $num_per_page) as $this_message => &$attr)
        {
            $participantCode = $attr['from'];
            $details = Participant::getParticipantDetails($participantCode);

            foreach ($attr as $configKey => $configVal) {
                // Store values in array to convert to JSON to use when loading the dialog
                $info_modal[$item_num][str_replace("_", "-", $configKey)] = $configVal . "";
            }
            // Trim identifier
            $date = \DateTimeRC::format_ts_from_ymd($attr['received_date']);

            $message = str_replace(array("\n", "\r"), ' ', htmlentities($attr['body']));
            if (strlen($message) > 65) {
                $message = substr($message, 0 , 65) . "...";
            }

            $action_needed = ($attr['processed'] == '1') ? $lang['design_99'] : '<span style="color: red;">'.$lang['design_100'].'</span>';
            // Add to array
            $message_list_full[$i] = array();
            $message_list_full[$i][] = "<div class='wrapemail'>{$date}</div>";
            $message_list_full[$i][] = "<div class='wrapemail'>{$details[$participantCode]['identifier']}</div>";
            $message_list_full[$i][] = "<div class='wrapemail'>{$message}</div>";
            $message_list_full[$i][] = "<div class='wrapemail' id='action_needed_".$attr['uuid']."'>{$action_needed}</div>";
            if ($attr['uuid'] != '') {
                $message_list_full[$i][] = '<a onclick="openMessagesHistory(\''.$participantCode.'\', \''.$attr['uuid'].'\');" href="javascript:;" style="outline:none;color:green;font-family:Tahoma;font-size:12px;"><i class="fas fa-magnifying-glass"></i> </a>';
                // Increment row counter
                $item_num++;
            } else {
                $message_list_full[$i][] = '';
            }

            $i++;
            // Remove this row to save memory
            unset($messages_list[$this_message]);
        }

        // If no messages exist yet, render one row to let user know that
        if (empty($message_list_full))
        {
            // No messages exist yet
            $message_list_full[0] = array(RCView::div(array('class'=>'wrap','style'=>'color:#800000;'), $lang['mycap_mobile_app_433']),"","","","");
        }

        // Build messages list table
        $messageTableWidth = 915;
        $messageTableHeaders = array();
        $messageTableHeaders[] = array(120, $lang['mycap_mobile_app_434']);
        $messageTableHeaders[] = array(180, $lang['mycap_mobile_app_435']);
        $messageTableHeaders[] = array(370, $lang['messaging_110']);
        $messageTableHeaders[] = array(97, $lang['mycap_mobile_app_436']);
        $messageTableHeaders[] = array(78, $lang['mycap_mobile_app_542'], "center", "string", false);

        list($fromList, $toList) = self::getDropDownList(PROJECT_ID, self::STANDARD);
        $messageTableTitle =	RCView::div(array(),
            RCView::div(array('style'=>'padding:2px 5px 0 5px;float:left;font-size:14px;'),
                $lang['mycap_mobile_app_432'] . RCView::br() .
                RCView::span(array('style'=>'line-height:24px;color:#666;font-size:11px;font-weight:normal;'),
                    $lang['mycap_mobile_app_427']
                ) . RCView::br() . RCView::br() .
                RCView::span(array('style'=>'color:#555;font-size:11px;font-weight:normal;'),
                    $pageDropdown
                )
            ) .
            ## FILTERS
            RCView::div(array('style'=>'max-width:500px;font-weight:normal;float:left;font-size:11px;padding-left:15px;margin-left:10px;border-left:1px solid #ccc;'),
                // Date/time range
                $lang['mycap_mobile_app_434']." ".$lang['survey_439'] .
                RCView::text(array('id'=>'filterBeginTime','value'=>$_GET['filterBeginTime']??'','class'=>'x-form-text x-form-field filter_datetime_mdy','style'=>'margin-right:8px;margin-left:3px;width:102px;height:20px;line-height:20px;font-size:11px;', 'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
                $lang['mycap_mobile_app_434']." ".$lang['survey_440'] .
                RCView::text(array('id'=>'filterEndTime','value'=>(isset($_GET['filterEndTime']) ? $_GET['filterEndTime'] : ""),'class'=>'x-form-text x-form-field filter_datetime_mdy','style'=>'margin-left:3px;width:102px;height:20px;line-height:20px;font-size:11px;', 'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
                RCView::span(array('class'=>'df','style'=>'color:#777;'), '('.\DateTimeRC::get_user_format_label().' H:M)') . RCView::br() .
                // Display all active participants displayed in this view
                $lang['mycap_mobile_app_435'] .
                RCView::select(array('id'=>'filterParticipant','style'=>'font-size:11px;margin:2px 3px;'), $fromList, $_GET['filterParticipant']??'',300) .
                RCView::br() .
                // "Apply filters" button
                RCView::button(array('class'=>'jqbuttonsm','style'=>'margin-top:5px;font-size:11px;color:#800000;','onclick'=>"loadInboxMessagesList(1)"), $lang['survey_442']) .
                RCView::a(array('href'=>PAGE_FULL."?messages=1&pid=".PROJECT_ID,'style'=>'vertical-align:middle;margin-left:15px;text-decoration:underline;font-weight:normal;font-size:11px;'), $lang['setup_53'])
            ) .
            RCView::div(array('class'=>'clear'), '')
        );
        // Build Message List
        $list = renderGrid("inbox_table", $messageTableTitle, $messageTableWidth-count($messageTableHeaders), "auto", $messageTableHeaders, $message_list_full);
        return "<div class='mt-3'>".$list."</div>";
    }

    /**
     * Return participant messages
     *
     * @param int $participantCode
     * @return array $messages
     */
    public static function getParticipantMessages($participantCode, $projectId = null)
    {
        if (is_null($projectId)) {
            $projectId = PROJECT_ID;
        }
        $participant_details = Participant::getParticipantDetails($participantCode);
        $identifier = $participant_details[$participantCode]['identifier'];

        $sql = "SELECT * FROM redcap_mycap_messages 
                WHERE project_id = ".$projectId." 
                    AND (`from` = '".$participantCode."' OR `to` = '".$participantCode."' OR `to` = '')
                ORDER BY sent_date";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            // Add to messages array
            $from = ($row['from_server'] == 1) ? $row['from'] : $identifier;
            if ($row['type'] == self::ANNOUNCEMENT) { // This is announcement
                $to = $identifier;
            } else {
                $to = ($row['from_server'] == 0) ? $row['to'] : $identifier;
            }

            $details[] = array('message_id' => $row['uuid'],
                                'type' => $row['type'],
                                'from_server' => $row['from_server'],
                                'from' => $from,
                                'to' => $to,
                                'sent_date' => (!empty($row['sent_date'])) ? $row['sent_date'] : '-',
                                'received_date' => (!empty($row['received_date'])) ? $row['received_date'] : '-',
                                'title' => $row['title'],
                                'body' => $row['body'],
                                'processed' => $row['processed']);
        }

        // If no participant, then return empty array
        if (empty($details)) return array(array(), $identifier);

        return array($details, $identifier);
    }

    /**
     * Display Message history for participant
     *
     * @param string $participantCode
     * @return string
     */
    public static function displayMessageHistory($participantCode)
    {
        global $lang;
        if (!Participant::isValidParticipant($participantCode, PROJECT_ID, false)) {
            return $lang['mycap_mobile_app_510'];
        }

        list($messages, $participantIdentifier) = self::getParticipantMessages($participantCode);
        $html  = '<div style="text-align: center;color:#800000;font-size:24px;">'.$participantIdentifier.'</div>';
        $html .= '<ul id="messageTimeline" class="timeline">';
        if (count($messages) > 0) {
            $i = 0;
            foreach ($messages as $message) {
                if ($message['from_server'] == 1) { // From REDCap server to Participant
                    $fromText = '<span style="font-size: 11px;" class="text-muted; float-end">'.$message['from'].'</span>';
                    $liClass = '';
                    $iconClass = 'fas fa-laptop';
                    $action_needed_block = '';
                } else { // From Participant to REDCap server
                    $fromText = '';
                    $liClass = 'class="timeline-inverted"';
                    $iconClass = 'fas fa-mobile-alt';
                    $checked = ($message['processed'] == 1) ? "" : "checked = 'checked'";
                    $action_needed_block = '<div class="custom-control custom-switch mt-2" style="border-top: 1px solid #ccc; padding-top: 5px;">
                                                <input class="custom-control-input" name="is_action_needed_'.$i.'" id="is_action_needed_'.$i.'" '.$checked.' type="checkbox" onchange="processActionNeeded(this, \''.$message['message_id'].'\');">
                                                <label class="custom-control-label" style="font-weight: bold;" for="is_action_needed_'.$i.'">'.$lang['mycap_mobile_app_436'].'</label>
                                            </div>';
                }

                $html .= '<li '.$liClass.'>
                            <div class="timeline-badge">
                                <i class="'.$iconClass.'"></i>
                            </div>
                            <div class="timeline-panel" id="message_'.$message['message_id'].'">';
                if ($message['type'] == self::ANNOUNCEMENT) {
                    $html .= '<div class="timeline-heading">
                                <span class="announcement-block">'.$lang['mycap_mobile_app_437'].'</span>
                                '.$fromText.'
                            </div>';
                } else {
                    $html .= $fromText;
                }
                $html .= '<div class="timeline-body" style="padding-top: 5px;">
                                <p class="wrap-long-url">'.htmlspecialchars($message['body']).'</p>
                            </div>
                            <div style="font-size: 11px; padding-top: 10px;" class="text-muted">
                                <i class="far fa-clock"></i> '.self::getFriendlyTimeAgo(strtotime($message['sent_date']), 1).'
                                <span class="float-end">'.$message['sent_date'].'</span>
                            </div>
                            '.$action_needed_block.'
                        </div>
                     </li>';
                $i++;
            }
        }

        $html .= '<li>
                    <div class="timeline-badge">
                        <i class="fas fa-laptop"></i>
                    </div>
                    <div class="timeline-panel">
                        <div class="timeline-body">
                            <form id="newMessageForm">
                                <input type="hidden" name="participantId" value="'.$participantCode.'">
                                <div class="form-control-custom">
                                    <label for="body"><b>Body</b></label> <span class="requiredlabel p-0">(* '.$lang['data_entry_39'].')</span>
                                    <textarea id="body" name="body" style="max-width:95%;height:70px;"></textarea>
                                </div>
                            </form>
                            <div style="padding-top: 10px;">
                                <button id="newMessageSubmit" onclick="sendNewMessage(\''.$participantIdentifier.'\');" type="button" class="btn btn-sm btn-success float-start"><i class="fas fa-paper-plane"></i> SEND MESSAGE</button>
                            </div>
                        </div>
                    </div>
                </li>';

        $html .= '</ul>';

        return $html;
    }

    /**
     * Get Friendly text for time ago
     *
     * @param string $distant_timestamp]
     * @param integer $max_units
     * @return string
     */
    public static function getFriendlyTimeAgo($distant_timestamp, $max_units = 3) {
        $i = 0;
        $time = time() - $distant_timestamp; // to get the time since that moment
        $tokens = [
            31536000 => 'YEAR',
            2592000 => 'MONTH',
            604800 => 'WEEK',
            86400 => 'DAY',
            3600 => 'HOUR',
            60 => 'MINUTE',
            1 => 'SECOND'
        ];

        $responses = [];
        while ($i < $max_units && $time > 0) {
            foreach ($tokens as $unit => $text) {
                if ($time < $unit) {
                    continue;
                }
                $i++;
                $numberOfUnits = floor($time / $unit);

                $responses[] = $numberOfUnits . ' ' . $text . (($numberOfUnits > 1) ? 'S' : '');
                $time -= ($unit * $numberOfUnits);
                break;
            }
        }

        if (!empty($responses)) {
            return implode(', ', $responses) . ' AGO';
        }

        return 'Just now';
    }

    /**
     * Render Sent Messages listing page
     *
     * @return string
     */
    public static function renderOutboxMessagesList()
    {
        global $lang, $user_rights, $Proj;

        // Is user in a DAG? If so, display their DAG name.
        $dagDisplay = ($user_rights['group_id'] == '') ? '' : $Proj->getGroups($user_rights['group_id']);
        if ($dagDisplay != '') {
            $dagDisplay = "<span style='color:#008000; font-weight: normal;'> ($dagDisplay)</span>";
        }

        renderPageTitle("<div style='float:left;'>".$lang['mycap_mobile_app_418'].$dagDisplay."</div><br>");
        print RCView::p(array('class'=>'mt-0 mb-2', 'style'=>'max-width:900px;'), $lang['mycap_mobile_app_874']);
        // Get list of messages to display as table
        $messages_list = self::getMessages(PROJECT_ID, 'outbox');

        ## BUILD THE DROP-DOWN FOR PAGING THE SENT MESSAGES
        // Get messages count
        $messageCount = count($messages_list);
        // Section the Messages into multiple pages
        $num_per_page = self::MESSAGES_PER_PAGE;
        // Calculate number of pages for dropdown
        $num_pages = ceil($messageCount/$num_per_page);
        // Limit
        $limit_begin  = 0;
        if (!isset($_GET['pagenum'])) $_GET['pagenum'] = 1;
        if (isset($_GET['pagenum']) && $_GET['pagenum'] == 'last') {
            $_GET['pagenum'] = $num_pages;
        }
        if (isset($_GET['pagenum']) && is_numeric($_GET['pagenum']) && $_GET['pagenum'] > 1) {
            $limit_begin = ($_GET['pagenum'] - 1) * $num_per_page;
        }
        ## Build the paging drop-down for messages
        $pageDropdown = "<select onchange='loadOutboxMessagesList(this.value)' style='vertical-align:middle;font-size:11px;'>";
        //Loop to create options for dropdown
        for ($i = 1; $i <= $num_pages; $i++) {
            $end_num   = $i * $num_per_page;
            $begin_num = $end_num - $num_per_page + 1;
            $value_num = $end_num - $num_per_page;
            if ($end_num > $messageCount) $end_num = $messageCount;
            $pageDropdown .= "<option value='$i' " . ($_GET['pagenum'] == $i ? "selected" : "") . ">$begin_num - $end_num</option>";
        }
        $pageDropdown .= "</select>";
        $pageDropdown  = "{$lang['survey_45']} $pageDropdown {$lang['survey_133']} $messageCount";

        // If viewing ALL messages, then set $num_per_page to null to return all messages
        if (isset($_GET['pagenum']) && $_GET['pagenum'] == 'ALL') $num_per_page = null;

        $item_num = 0; // loop counter
        foreach (array_slice($messages_list, $limit_begin, $num_per_page) as $this_message => &$attr)
        {
            $participantCode = $attr['to'];
            $details = Participant::getParticipantDetails($participantCode);
            foreach ($attr as $configKey => $configVal) {
                // Store values in array to convert to JSON to use when loading the dialog
                $info_modal[$item_num][str_replace("_", "-", $configKey)] = $configVal . "";
            }
            // Trim identifier
            $date = \DateTimeRC::format_ts_from_ymd($attr['sent_date']);

            $message = str_replace(array("\n", "\r"), ' ', htmlentities($attr['body']));
            if (strlen($message) > 65) {
                $message = substr($message, 0 , 65) . "...";
            }

            // Add to array
            $message_list_full[$i] = array();
            $message_list_full[$i][] = "<div class='wrapemail'>{$date}</div>";
            $message_list_full[$i][] = "<div class='wrapemail'>{$attr['from']}</div>";
            $message_list_full[$i][] = "<div class='wrap'>{$details[$participantCode]['identifier']}</div>";
            $message_list_full[$i][] = "<div class='wrapemail'>{$message}</div>";
            if ($attr['uuid'] != '') {
                $message_list_full[$i][] = '<a onclick="openMessagesHistory(\''.$participantCode.'\', \''.$attr['uuid'].'\');" href="javascript:;" style="outline:none;color:green;font-family:Tahoma;font-size:12px;"><i class="fas fa-comment-alt"></i> </a>';
                // Increment row counter
                $item_num++;
            } else {
                $message_list_full[$i][] = '';
            }

            $i++;
            // Remove this row to save memory
            unset($messages_list[$this_message]);
        }

        // If no messages exist yet, render one row to let user know that
        if (empty($message_list_full))
        {
            // No messages exist yet
            $message_list_full[0] = array(RCView::div(array('class'=>'wrap','style'=>'color:#800000;'), $lang['mycap_mobile_app_433']),"","","","");
        }

        // Build messages list table
        $messageTableWidth = 915;
        $messageTableHeaders = array();
        $messageTableHeaders[] = array(120, $lang['mycap_mobile_app_442']);
        $messageTableHeaders[] = array(150, $lang['mycap_mobile_app_435']);
        $messageTableHeaders[] = array(110, $lang['alerts_26']);
        $messageTableHeaders[] = array(394, $lang['messaging_110']);
        $messageTableHeaders[] = array(69, $lang['mobile_app_87'], "center", "string", false);

        list($fromList, $toList) = self::getDropDownList(PROJECT_ID, 'outbox');
        $messageTableTitle = RCView::div(array(),
                            RCView::div(array('style'=>'padding:2px 5px 0 5px;float:left;font-size:14px;'),
                                $lang['mycap_mobile_app_441'] . RCView::br() .
                                    RCView::span(array('style'=>'line-height:24px;color:#666;font-size:11px;font-weight:normal;'),
                                        $lang['mycap_mobile_app_427']
                                    ) . RCView::br() . RCView::br() .
                                        RCView::span(array('style'=>'color:#555;font-size:11px;font-weight:normal;'),
                                            $pageDropdown
                                        )
                                ) .
                                ## FILTERS
                                RCView::div(array('style'=>'max-width:500px;font-weight:normal;float:left;font-size:11px;padding-left:15px;margin-left:10px;border-left:1px solid #ccc;'),
                                    // Date/time range
                                    $lang['mycap_mobile_app_442']." ".$lang['survey_439'] .
                                    RCView::text(array('id'=>'filterBeginTime','value'=>$_GET['filterBeginTime']??'','class'=>'x-form-text x-form-field filter_datetime_mdy','style'=>'margin-right:8px;margin-left:3px;width:102px;height:20px;line-height:20px;font-size:11px;', 'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
                                    $lang['mycap_mobile_app_442']." ".$lang['survey_440'] .
                                    RCView::text(array('id'=>'filterEndTime','value'=>(isset($_GET['filterEndTime']) ? $_GET['filterEndTime'] : ""),'class'=>'x-form-text x-form-field filter_datetime_mdy','style'=>'margin-left:3px;width:102px;height:20px;line-height:20px;font-size:11px;', 'onblur'=>"redcap_validate(this,'','','hard','datetime_'+user_date_format_validation,1,1,user_date_format_delimiter);")) .
                                    RCView::span(array('class'=>'df','style'=>'color:#777;'), '('.\DateTimeRC::get_user_format_label().' H:M)') . RCView::br() .
                                    // Display all users displayed in this view
                                    $lang['mycap_mobile_app_435'] .
                                    RCView::select(array('id'=>'filterUser','style'=>'font-size:11px;margin:2px 3px;'), $fromList, $_GET['filterUser']??'',300) .
                                    // Display active participants displayed in this view
                                    $lang['alerts_26'] .
                                    RCView::select(array('id'=>'filterParticipant','style'=>'font-size:11px;margin:2px 3px;'), $toList, $_GET['filterParticipant']??'',300) .
                                    RCView::br() .
                                    // "Apply filters" button
                                    RCView::button(array('class'=>'jqbuttonsm','style'=>'margin-top:5px;font-size:11px;color:#800000;','onclick'=>"loadOutboxMessagesList(1)"), $lang['survey_442']) .
                                    RCView::a(array('href'=>PAGE_FULL."?outbox=1&pid=".PROJECT_ID,'style'=>'vertical-align:middle;margin-left:15px;text-decoration:underline;font-weight:normal;font-size:11px;'), $lang['setup_53'])
                                ) .
                                RCView::div(array('class'=>'clear'), '')
                            );
        // Build Message List
        $list = renderGrid("outbox_table", $messageTableTitle, $messageTableWidth-count($messageTableHeaders), "auto", $messageTableHeaders, $message_list_full);
        return "<div class='mt-3'>".$list."</div>";
    }

    /**
     * Get participants and users dropdown list for filtering
     *
     * @param integer $project_id
     * @param string $type
     * @return array
     */
    public static function getDropDownList($project_id, $type = 'outbox')
    {
        global $lang, $user_rights;

        $groupID = ($user_rights['group_id'] != '' ? $user_rights['group_id'] : array());
        $codesList = array();
        $executeSQL = true;
        // Format $codesList as array
        if (!is_array($groupID) && $groupID == '0') {
            // If passing group_id as "0", assume we want to return unassigned records.
        } elseif (!empty($groupID) && is_numeric($groupID)) {
            $codesList = Participant::getParticipantsInDAG(array($groupID));
            if (empty($codesList))  $executeSQL = false;
        } elseif (!is_array($groupID)) {
            $codesList = array();
        }
        $sql = "SELECT * FROM redcap_mycap_messages WHERE project_id = ".$project_id;

        if ($type == self::STANDARD) {
            $fromList = array('' => $lang['mycap_mobile_app_365']);
            $toList = array();
            $sql .= " AND type = '".self::STANDARD."' AND from_server = 0";
            if (!empty($codesList)) $sql .= " AND `from` IN (".prep_implode($codesList).")";
            $sql .= " ORDER BY received_date DESC";
        } else if ($type == 'outbox') {
            $fromList = array('' => $lang['control_center_182']);
            $toList = array('' => $lang['mycap_mobile_app_365']);
            if ($executeSQL) {
                $sql .= " AND type = '".self::STANDARD."' AND from_server = 1";
                if (!empty($codesList)) $sql .= " AND `to` IN (".prep_implode($codesList).")";
                $sql .= " ORDER BY sent_date DESC";
            }
        }

        if ($executeSQL) {
            $q = db_query($sql);
            while ($row = db_fetch_assoc($q)) {
                $participantCode = ($type == self::STANDARD) ? $row['from'] : $row['to'];
                $details = Participant::getParticipantDetails($participantCode);
                $identifier = $details[$participantCode]['identifier'];

                if ($type == self::STANDARD) {
                    if (!in_array($identifier, $fromList)) {
                        $fromList[$participantCode] = $identifier;
                    }
                } else if ($type == 'outbox') {
                    if (!in_array($row['from'], $fromList)) {
                        $fromList[$row['from']] = $row['from'];
                    }
                    if (!in_array($identifier, $toList)) {
                        $toList[$participantCode] = $identifier;
                    }
                }
            }
        }

        return array($fromList, $toList);
    }

    /**
     * Load message by uuid
     *
     * @param string $uuid
     */
    public function loadByUuid($uuid)
    {
        $sql = "SELECT * FROM redcap_mycap_messages 
                WHERE uuid = '".$uuid."'
                ORDER BY sent_date";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            $participant_details = Participant::getParticipantDetails($row['from']);
            $identifier = $participant_details[$row['from']]['identifier'];

            // Add to messages array
            $from = ($row['from_server'] == 1) ? $row['from'] : $identifier;
            if ($row['type'] == self::ANNOUNCEMENT) { // This is announcement
                $to = $identifier;
            } else {
                $to = ($row['from_server'] == 0) ? $row['to'] : $identifier;
            }

            $details[] = array('message_id' => $row['uuid'],
                'type' => $row['type'],
                'from_server' => $row['from_server'],
                'from' => $from,
                'to' => $to,
                'sent_date' => (!empty($row['sent_date'])) ? \DateTimeRC::format_user_datetime($row['sent_date'], 'Y-M-D_24') : '-',
                'received_date' => (!empty($row['received_date'])) ? \DateTimeRC::format_user_datetime($row['received_date'], 'Y-M-D_24') : '-',
                'title' => $row['title'],
                'body' => $row['body'],
                'processed' => $row['processed']);
        }

        // If no participant, then return empty array
        if (!empty($details)) $this->setProperties($details);
    }
    /**
     * Set properties
     *
     * @param array $props
     */
    private function setProperties($props)
    {
        $this->repeatInstance = $props['redcap_repeat_instance'];
        $this->studyId = $props['stu_id'];
        $this->id = $props['message_id'];
        $this->type = $props['type'];
        $this->from = $props['from'];
        $this->to = $props['to'];
        $this->title = $props['title'];
        $this->body = $props['body'];
        $this->sentDate = $props['sent_date'];
        $this->receivedDate = $props['received_date'];
        $this->readDate = $props['msg_readdate'];
    }

    /**
     * Validate request
     */
    public function validate()
    {
        Assert::uuid($this->id, "Message 'id' is invalid. Must be a UUID. Given: $this->id");
        /*if (Type::isInvalid($this->type)) {
            throw new \UnexpectedValueException("Message 'type' is invalid. Must be one of: " .
                print_r(Type::values(), true) . ". Given: $this->type");
        }*/
        Assert::stringNotEmpty($this->from, "Message 'from' cannot be empty");
        Assert::stringNotEmpty($this->to, "Message 'to' cannot be empty");
        Assert::stringNotEmpty($this->body, "Message 'body' cannot be empty");
        Assert::integerish(
            $this->sentDate,
            "Message 'sentDate' should be an integer date. Unix timestamp, seconds since 1970. Given: $this->sentDate"
        );
        if (strlen($this->receivedDate)) {
            Assert::integerish(
                $this->receivedDate,
                "Message 'receivedDate' should be an integer date. Unix timestamp, seconds since 1970. " .
                "Given: $this->receivedDate"
            );
        }
        if (isset($this->readDate) && strlen($this->readDate)) {
            Assert::integerish(
                $this->readDate,
                "Message 'readDate' should be an integer date. Unix timestamp, seconds since 1970. " .
                "Given: $this->readDate"
            );
        }
    }

    /**
     * Load message by uuid
     *
     * @param string $uuid
     */
    public function listAllForParticipant($par_code, $project_code) {
        $project_id = MyCap::getProjectIdByCode($project_code);

        list($messages, $participantIdentifier) = self::getParticipantMessages($par_code, $project_id);
        $details = array();

        foreach ($messages as $message) {
            $from = ($message['from_server'] == 1) ? $message['from'] : $par_code;
            if ($message['type'] == self::ANNOUNCEMENT) { // This is announcement
                // If added by user is assigned to DAG then send only to participants in that DAG
                $user_rights = \UserRights::getPrivileges($project_id, $from);
                $user_rights = $user_rights[$project_id][strtolower($from)];
                $groupID = ($user_rights['group_id'] != '' ? $user_rights['group_id'] : array());
                $codesList = array();
                // Format $codesList as array
                if (!is_array($groupID) && $groupID == '0') {
                    // If passing group_id as "0", assume we want to return unassigned records.
                } elseif (!empty($groupID) && is_numeric($groupID)) {
                    $codesList = Participant::getParticipantsInDAG(array($groupID), $project_id);
                } elseif (!is_array($groupID)) {
                    $codesList = array();
                }
                if (!empty($codesList)) {
                    if (!in_array($par_code, $codesList)) continue;
                }
                $to = $par_code;
            } else {
                $to = ($message['from_server'] == 0) ? $message['to'] : $par_code;
            }

            $sent_timestamp = "";
            if ($message['sent_date'] != '-') {
                $sent_timestamp = strtotime($message['sent_date']);
            }

            $received_timestamp = "";
            if ($message['received_date'] != '-') {
                $received_timestamp = strtotime($message['received_date']);
            }

            $details[] = array('msg_id' => $message['message_id'],
                'msg_from' => $from,
                'msg_to' => $to,
                'msg_type' => $message['type'],
                'msg_sentdate' => $sent_timestamp,
                'msg_body' => $message['body'],
                'msg_receiveddate' => $received_timestamp,
                'msg_readdate' => $sent_timestamp
            );
        }

        return $details;
    }

    /**
     * Save message
     */
    public function save($doNotSave = true)
    {
        $data = [
            'stu_id' => $this->studyId,
            'msg_id' => $this->id,
            'msg_type' => $this->type,
            'msg_from' => $this->from,
            'msg_to' => $this->to,
            'msg_title' => $this->title ?? "",
            'msg_body' => $this->body,
            'msg_sentdate' => $this->sentDate,
            'msg_receiveddate' => $this->receivedDate,
            'msg_readdate' => $this->readDate ?? ""
        ];

        $sent_date = ($data['msg_sentdate'] != '') ? date("Y-m-d H:i:s", $data['msg_sentdate']) : NULL;
        $received_date = ($data['msg_receiveddate'] != '') ? date("Y-m-d H:i:s", $data['msg_receiveddate']) : NULL;
        $read_date = ($data['msg_readdate'] != '') ? date("Y-m-d H:i:s", $data['msg_readdate']) : NULL;

        // Begin transaction
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");

        $sql = "INSERT INTO redcap_mycap_messages (uuid, project_id, `type`, from_server, `from`, `to`, body, sent_date, received_date, read_date) VALUES
            ('".$data['msg_id']."', '".$data['stu_id']."', '".$data['msg_type']."', '0', '".$data['msg_from']."', '".$data['msg_to']."', '".db_escape($data['msg_body'])."', ".checkNull($sent_date).", ".checkNull($received_date).", ".checkNull($read_date).")";

        $q = db_query($sql);
        if ($q == false) {
            // ERROR: Roll back all changes made and return the error message
            db_query("ROLLBACK");
            db_query("SET AUTOCOMMIT=1");
            return false;
        } else {
            if ($doNotSave == true) {
                db_query("ROLLBACK");
                db_query("SET AUTOCOMMIT=1");
            } else {
                db_query("COMMIT");
                db_query("SET AUTOCOMMIT=1");
            }
            return true;
        }
    }

    /**
     * Returns message as an array
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'stu_id' => $this->studyId,
            'msg_id' => $this->id,
            'msg_type' => $this->type,
            'msg_from' => $this->from,
            'msg_to' => $this->to,
            'msg_title' => $this->title ?? "",
            'msg_body' => $this->body,
            'msg_sentdate' => $this->sentDate,
            'msg_receiveddate' => $this->receivedDate,
            'msg_readdate' => $this->readDate ?? ""
        ];
    }
}

