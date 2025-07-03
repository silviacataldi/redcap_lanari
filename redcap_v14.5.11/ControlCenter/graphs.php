<?php


include 'header.php';
if (!ACCESS_ADMIN_DASHBOARDS) redirect(APP_PATH_WEBROOT);

/**
 * Render graphs
 */

// Past week
if (!isset($_GET['plottime']) || $_GET['plottime'] == "1w" || $_GET['plottime'] == "") {
	if (!isset($_GET['plottime'])) $_GET['plottime'] = "1w";
	$date_label = $lang['dashboard_07'];
// Past day
} elseif ($_GET['plottime'] == "1d") {
	$date_label = $lang['dashboard_89'];
// Past month
} elseif ($_GET['plottime'] == "1m") {
	$date_label = $lang['dashboard_08'];
// Past three months
} elseif ($_GET['plottime'] == "3m") {
	$date_label = $lang['dashboard_09'];
// Past six months
} elseif ($_GET['plottime'] == "6m") {
	$date_label = $lang['dashboard_10'];
// Past year
} elseif ($_GET['plottime'] == "12m") {
	$date_label = $lang['dashboard_11'];
// All
} elseif ($_GET['plottime'] == "all") {
	$date_label = $lang['dashboard_12'];
}

// Select time interval for plots
print  "<div id='plots' style='padding-top:0px;padding-bottom:30px;'>
		<div style='font-size:14px;font-weight:bold;padding-bottom:4px;'>{$lang['dashboard_06']}</div>";
if ($_GET['plottime'] == "1d") print $lang['dashboard_89']; else print "<a href='{$_SERVER['PHP_SELF']}?plottime=1d' style='text-decoration:underline;font-size:12px;'>{$lang['dashboard_89']}</a>";
print  "&nbsp;|&nbsp; ";
if ($_GET['plottime'] == "1w") print $lang['dashboard_07']; else print "<a href='{$_SERVER['PHP_SELF']}?plottime=1w' style='text-decoration:underline;font-size:12px;'>{$lang['dashboard_07']}</a>";
print  "&nbsp;|&nbsp; ";
if ($_GET['plottime'] == "1m") print $lang['dashboard_08']; else print "<a href='{$_SERVER['PHP_SELF']}?plottime=1m' style='text-decoration:underline;font-size:12px;'>{$lang['dashboard_08']}</a>";
print  " &nbsp;|&nbsp; ";
if ($_GET['plottime'] == "3m") print $lang['dashboard_09']; else print "<a href='{$_SERVER['PHP_SELF']}?plottime=3m' style='text-decoration:underline;font-size:12px;'>{$lang['dashboard_09']}</a>";
print  " &nbsp;|&nbsp; ";
if ($_GET['plottime'] == "6m") print $lang['dashboard_10']; else print "<a href='{$_SERVER['PHP_SELF']}?plottime=6m' style='text-decoration:underline;font-size:12px;'>{$lang['dashboard_10']}</a>";
print  " &nbsp;|&nbsp; ";
if ($_GET['plottime'] == "12m") print $lang['dashboard_11']; else print "<a href='{$_SERVER['PHP_SELF']}?plottime=12m' style='text-decoration:underline;font-size:12px;'>{$lang['dashboard_11']}</a>";
print  "&nbsp;|&nbsp; ";
if ($_GET['plottime'] == "all") print $lang['dashboard_12']; else print "<a href='{$_SERVER['PHP_SELF']}?plottime=all' style='text-decoration:underline;font-size:12px;'>{$lang['dashboard_12']}</a>";
print  "</div>";

// Concurrent Users
print  '<div class="graph-parent">
			<div class="graph-title">
				'.$lang['dashboard_127'].'
				<span class="graph-freq">&nbsp;(' . $date_label . ')</span>
			</div>
			<div id="chart7" class="graph">
				&nbsp;&nbsp;
				<img src="' . APP_PATH_IMAGES . 'progress_circle.gif">
				'.$lang['dashboard_14'].'...
			</div>
		</div>';

// DB Usage
print  '<div class="graph-parent">
			<div class="graph-title">
				'.$lang['dashboard_109'].'
				<span class="graph-freq">&nbsp;(' . $date_label . ')</span>
			</div>
			<div id="chart11" class="graph">
				&nbsp;&nbsp;
				<img src="' . APP_PATH_IMAGES . 'progress_circle.gif">
				'.$lang['dashboard_14'].'...
			</div>
		</div>';

// Uploaded Files Usage
print  '<div class="graph-parent">
			<div class="graph-title">
				'.$lang['dashboard_110'].'
				<span class="graph-freq">&nbsp;(' . $date_label . ')</span>
			</div>
			<div id="chart10" class="graph">
				&nbsp;&nbsp;
				<img src="' . APP_PATH_IMAGES . 'progress_circle.gif">
				'.$lang['dashboard_14'].'...
			</div>
		</div>';

// User Logins
print  '<div class="graph-parent">
			<div class="graph-title">
				'.$lang['dashboard_99'].'
				<span class="graph-freq">&nbsp;(' . $date_label . ')</span>
			</div>
			<div id="chart9" class="graph">
				&nbsp;&nbsp;
				<img src="' . APP_PATH_IMAGES . 'progress_circle.gif">
				'.$lang['dashboard_14'].'...
			</div>
		</div>';

// Projects Created
print  '<div class="graph-parent">
			<div class="graph-title">
				'.$lang['dashboard_15'].'
				<span class="graph-freq">&nbsp;(' . $date_label . ')</span>
			</div>
			<div id="chart4" class="graph">
				&nbsp;&nbsp;
				<img src="' . APP_PATH_IMAGES . 'progress_circle.gif">
				'.$lang['dashboard_14'].'...
			</div>
		</div>';

// Projects Moved to Production
print  '<div class="graph-parent">
			<div class="graph-title">
				'.$lang['dashboard_88'].'
				<span class="graph-freq">&nbsp;(' . $date_label . ')</span>
			</div>
			<div id="chart8" class="graph">
				&nbsp;&nbsp;
				<img src="' . APP_PATH_IMAGES . 'progress_circle.gif">
				'.$lang['dashboard_14'].'...
			</div>
		</div>';

// Active Users
print  '<div class="graph-parent">
			<div class="graph-title">
				'.$lang['dashboard_17'].'
				<span class="graph-freq">&nbsp;(' . $date_label . ')</span>
			</div>
			<div id="chart5" class="graph">
				&nbsp;&nbsp;
				<img src="' . APP_PATH_IMAGES . 'progress_circle.gif">
				'.$lang['dashboard_14'].'...
			</div>
		</div>';

// First time accessing REDCap
print  '<div class="graph-parent">
			<div class="graph-title">
				'.$lang['dashboard_18'].'
				<span class="graph-freq">&nbsp;(' . $date_label . ')</span>
			</div>
			<div id="chart6" class="graph">
				&nbsp;&nbsp;
				<img src="' . APP_PATH_IMAGES . 'progress_circle.gif">
				'.$lang['dashboard_14'].'...
			</div>
		</div>';

// Logged Events
print  '<div class="graph-parent">
			<div class="graph-title">
				'.$lang['dashboard_16'].'
				<span class="graph-freq">&nbsp;(' . $date_label . ')</span>
			</div>
			<div id="chart2" class="graph">
				&nbsp;&nbsp;
				<img src="' . APP_PATH_IMAGES . 'progress_circle.gif">
				'.$lang['dashboard_14'].'...
			</div>
		</div>';

// Page Hits
print  '<div class="graph-parent">
			<div class="graph-title">
				'.$lang['dashboard_13'].'
				<span class="graph-freq">&nbsp;(' . $date_label . ')</span>
			</div>
			<div id="chart1" class="graph">
				&nbsp;&nbsp;
				<img src="' . APP_PATH_IMAGES . 'progress_circle.gif">
				'.$lang['dashboard_14'].'...
			</div>
		</div>';

// AJAX requests to load the stats table and graphs (much faster than if running inline)
loadJS('StatsAndCharts.js');
?>
<style type="text/css">
.graph-parent { width:100%;max-width:500px;border:1px solid #000000;background-color:#f7f7f7;margin-bottom:30px; }
.graph-title { text-align:center;font-weight:bold;font-size:14px;padding:5px; }
.graph-freq { font-size:11px;font-weight:normal;color:#555; }
.graph { height:250px; }
</style>
<script type="text/javascript">
// Use raw data to draw the chart
function drawChart(id,raw_data,dateformat) {
	var data = new google.visualization.DataTable();
	data.addColumn(dateformat, 'Date');
	data.addColumn('number');
	for (var i = 0; i < raw_data.length; i++) {
        var piece = raw_data[i][0].split(',');
        if (piece.length == 3) {
            var thisDate = new Date(piece[0],piece[1],piece[2]);
        } else {
            var thisDate = new Date(piece[0],piece[1],piece[2],piece[3],piece[4],piece[5]);
        }
		data.addRow([thisDate, raw_data[i][1]*1]);
	}
	var chart = new google.visualization.LineChart(document.getElementById(id));
	chart.draw(data, {legend: 'none', title: '', chartArea: { width: 410, height: 180 }});
}
function displayChart(chartid, data) {
    $('#'+chartid).html('');
    try {
        data = jQuery.parseJSON(data);
        if (data.raw_data.length > 0) {
            drawChart(chartid, data.raw_data, data.format);
        }
    } catch(e) { }
}
$(function() {
	// Chain all ajax events so that they are fired sequentially
	var ccstats  = app_path_webroot + 'ControlCenter/stats_ajax.php';
	var plottime = getParameterByName('plottime');
	// Chart 11
	$.get(ccstats, { plottime: plottime, chartid: 'chart11'}, function(data) { displayChart('chart11',data);
		// Chart 10
		$.get(ccstats, { plottime: plottime, chartid: 'chart10'}, function(data) { displayChart('chart10',data);
			// Chart 4
			$.get(ccstats, { plottime: plottime, chartid: 'chart4'}, function(data) { displayChart('chart4',data);
				// Chart 8
				$.get(ccstats, { plottime: plottime, chartid: 'chart8'}, function(data) { displayChart('chart8',data);
					// Chart 1
					$.get(ccstats, { plottime: plottime, chartid: 'chart1'}, function(data) { displayChart('chart1',data);
						// Chart 5
						$.get(ccstats, { plottime: plottime, chartid: 'chart5'}, function(data) { displayChart('chart5',data);
							// Chart 6
							$.get(ccstats, { plottime: plottime, chartid: 'chart6'}, function(data) { displayChart('chart6',data);
								// Run the rest of the charts simultaneously
								// Chart 7
								$.get(ccstats, { plottime: plottime, chartid: 'chart7'}, function(data) { displayChart('chart7',data);
								} );
								// Chart 9
								$.get(ccstats, { plottime: plottime, chartid: 'chart9'}, function(data) { displayChart('chart9',data);
								} );
								// Chart 2
								$.get(ccstats, { plottime: plottime, chartid: 'chart2'}, function(data) { displayChart('chart2',data);
								} );
							} );
						} );
					} );
				} );
			} );
		} );
	} );
});
</script>

<?php 
include 'footer.php';