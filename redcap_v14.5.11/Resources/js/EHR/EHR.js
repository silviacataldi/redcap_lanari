/**
 * subset of the core REDCap functions extracted for the "launch from EHR"
 * environment and meant to be compatible with IE 10/11
 */

var isNumeric    = function(n){return !isNaN(parseFloat(n)) && isFinite(n);};
if(typeof lang=='undefined'){var lang={}};

// Check for invalid characters in record names
// Returns TRUE if valid, else returns error message.
function recordNameValid(id) {
	var valid = true;
	// Don't allow pound signs in record names
	if (/#/g.test(id)) {
		valid = "Pound signs (#) are not allowed in record names! Please enter another record name.";
	}
	// Don't allow apostrophes in record names
	if (/'/g.test(id)) {
		valid = "Apostrophes (') are not allowed in record names! Please enter another record name.";
	}
	// Don't allow ampersands in record names
	if (/&/g.test(id)) {
		valid = "Ampersands (&) are not allowed in record names! Please enter another record name.";
	}
	// Don't allow plus signs in record names
	if (/\+/g.test(id)) {
		valid = "Plus signs (+) are not allowed in record names! Please enter another record name.";
	}
	// Don't allow tabs in record names
	if (/\t/g.test(id)) {
		valid = "Tab characters are not allowed in record names! Please enter another record name.";
	}
	return valid;
}

//Display "Working" div as progress indicator
function showProgress(show,ms) {
	// Set default time for fade-in/fade-out
	if (ms == null) ms = 500;
	if (!$("#working").length) 	$('body').append('<div id="working"><img alt="Working..." src="'+app_path_images+'progress_circle.gif">&nbsp; Working...</div>');
	if (!$("#fade").length) 	$('body').append('<div id="fade"></div>');
	if (show) {
		$('#fade').addClass('black_overlay').show();
		$('#working').center().fadeIn(ms);
	} else {
		setTimeout(function(){
			$("#fade").removeClass('black_overlay').hide();
			$("#working").fadeOut(ms);
		},ms);
	}
}

// Creates hidden div needed for jQuery UI dialog box. If div exists and is a dialog already, removes as existing dialog.
function initDialog(div_id,inner_html) {
	if ($('#'+div_id).length) {
		if ($('#'+div_id).hasClass('ui-dialog-content')) $('#'+div_id).dialog('destroy');
		$('#'+div_id).addClass('simpleDialog');
	} else {
		$('body').append('<div id="'+div_id+'" class="simpleDialog"></div>');
	}
	$('#'+div_id).html((inner_html == null ? '' : inner_html));
}

// Fit a jQuery UI dialog box on the page if too tall.
function fitDialog(ob) {
    try {
        var winh = $(window).height();
        var isSurvey = (page == 'surveys/index.php');
        var hasNavBar = (!isSurvey && $('.navbar.navbar-light.fixed-top').css('display') != 'none');
        if (hasNavBar) winh -= $('.navbar.navbar-light.fixed-top').height() + 30;
        var thisHeight = $(ob).height();
        var dialogCollapsedOnMobile = (isMobileDevice && thisHeight < 20);
        if ($(ob).hasClass('ui-dialog-content') && ((thisHeight + 110) >= winh || dialogCollapsedOnMobile)) {
            // Set new height to be slightly smaller than window size
            $(ob).dialog('option', 'height', winh - (isMobileDevice ? 130 : 30));
            // If height somehow ends up as 0 (tends to happen on mobile devices)
            if (dialogCollapsedOnMobile) {
                $(ob).height(winh - 85);
            }
            // Center it
            $(ob).dialog('option', 'position', ["center", 10]);
        } else {
            // Center it
            $(ob).dialog('option', 'position', {my: 'center', at: 'center', of: window});
        }
    } catch(e){
		console.log(e)
	 }
}


// Center a jQuery object via .center()
jQuery.fn.center = function () {
    this.css("position","absolute");
    this.css("top", Math.max(0, (($(window).height() - $(this).outerHeight()) / 2) +
                                                $(window).scrollTop()) + "px");
    this.css("left", Math.max(0, (($(window).width() - $(this).outerWidth()) / 2) +
                                                $(window).scrollLeft()) + "px");
    return this;
}

// This will override the function used when setting jQuery UI dialog titles, allowing it to contain HTML.
jQuery.widget("ui.dialog", jQuery.extend({}, $.ui.dialog.prototype, {
    _title: function(title) {
        if (!this.options.title ) {
            title.html("&#160;");
        } else {
            title.html(this.options.title);
        }
    }
}));

// Display DDP explanation dialog
function ddpExplainDialog(fhir) {
    initDialog('ddpExplainDialog');
    var dialogHtml = $('#ddpExplainDialog').html();
    if (dialogHtml.length > 0) {
        $('#ddpExplainDialog').dialog('open');
    } else {
        fhir = (fhir == '1') ? '?type=fhir' : '';
        $.get(app_path_webroot+'DynamicDataPull/info.php'+fhir,{ },function(data) {
            var json_data = JSON.parse(data);
            $('#ddpExplainDialog').html(json_data.content).dialog({ bgiframe: true, modal: true, width: 750, title: json_data.title,
                open: function(){ fitDialog(this); },
                buttons: {
                    Close: function() { $(this).dialog('close'); }
                }
            });
        });
    }
}

// Display jQuery UI dialog with Close button (provide id, title, content, width, onClose JavaScript event as string)
function simpleDialog(content,title,id,width,onCloseJs,closeBtnTxt,okBtnJs,okBtnTxt,autoOpen) {
	if (typeof autoOpen == 'undefined') autoOpen = true;
	// If no id is provided, create invisible div on the fly to use as dialog container
	var idDefined = true;
	if (id == null || trim(id) == '') {
		id = "popup"+Math.floor(Math.random()*10000000000000000);
		idDefined = false;
	}
	// If this DOM element doesn't exist yet, then add it and set title/content
	if ($('#'+id).length < 1) {
		var existInDom = false;
		initDialog(id);
	} else {
		if (title == null || title == '') title = $('#'+id).attr('title');
		var existInDom = true;
		if (!$('#'+id).hasClass('simpleDialog')) $('#'+id).addClass('simpleDialog');
	}
	// Set content
	if (content != null && content != '') $('#'+id).html(content);
	// Add invisible element at beginning of dialog content to prevent auto-focus issues on inputs and buttons
	$('#'+id).prepend('<span class="ui-helper-hidden-accessible"><input type="text"/></span>');
	// default title
	if (title == null) title = '<span style="color:#555;font-weight:normal;">'+(typeof window.lang.alerts_24 == 'undefined' ? 'Alert' : window.lang.alerts_24)+'</span>';
	// Set parameters
	if (!isNumeric(width)) width = 500; // default width
	// Set default button text
	if (okBtnTxt == null) {
		// Default "okay" text for secondary button
		okBtnTxt = (typeof window.lang.design_401 == 'undefined' ? 'Okay' : window.lang.design_401);
		// Default "cancel" text for first button when have 2 buttons
		if (okBtnJs != null && closeBtnTxt == null) closeBtnTxt = (typeof window.lang.global_53 == 'undefined' ? 'Cancel' : window.lang.global_53);
	}
	if (closeBtnTxt == null) {
		// Default "close" text for single button
		closeBtnTxt = (typeof window.lang.calendar_popup_01 == 'undefined' ? 'Close' : window.lang.calendar_popup_01);
	}
	// Set up button(s)
	if (okBtnJs == null) {
		// Only show a Close button
		var btnClass = '';
		if(onCloseJs === 'delete_iframe'){
		  btnClass = 'hidden';
		}
		var btns =	[{ text: closeBtnTxt, 'class': btnClass, click: function() {
						// Destroy dialog and remove div from DOM if was created on the fly
						try{ $(this).dialog('close').dialog('destroy'); }catch(e){ }
						if (!idDefined) $('#'+id).remove();
					} }];
	} else {
		// Show two buttons
		var btns =	[{ text: closeBtnTxt, click: function() {
						// Destroy dialog and remove div from DOM if was created on the fly
						try{ $(this).dialog('close').dialog('destroy'); }catch(e){ }
						if (!idDefined) $('#'+id).remove();
					}},
					{text: okBtnTxt, click: function() {
						// If okBtnJs was provided, then eval it to execute
						if (okBtnJs != null) {
							if (typeof(okBtnJs) == 'string') {
								eval(okBtnJs);
							} else {
								var okBtnJsFunc = okBtnJs;
								eval("okBtnJsFunc()");
							}
						}
						// Destroy dialog and remove div from DOM if was created on the fly
						$(this).dialog('destroy');
						if (!idDefined) $('#'+id).remove();
					}}];
	}
	// Show dialog
	$('#'+id).dialog({ bgiframe: true, modal: true, width: width, title: title, buttons: btns, autoOpen: autoOpen });
	// If Javascript is provided for onClose event, then set it here
	if (onCloseJs != null) {
		
		if(onCloseJs == 'delete_iframe'){
			var dialogcloseFunc = function(){window.location.reload()};
		}else{
			var dialogcloseFunc = (typeof(onCloseJs) == 'string') ? function(){eval(onCloseJs)} : onCloseJs;
		}
		$('#'+id).bind('dialogclose', dialogcloseFunc);
	}
	// If div already existed in DOM beforehand (i.e. wasn't created here on the fly), then re-add title to div because it gets lost when converted to dialog
	if (existInDom)	$('#'+id).attr('title', title);
}
