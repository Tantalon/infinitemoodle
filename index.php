<?php

require('../../config.php');
require_once($CFG->dirroot.'/report/infiniterooms/lib.php');
require_once($CFG->libdir.'/adminlib.php');

admin_externalpage_setup('reportinfiniterooms', '', null, '', array('pagelayout'=>'report'));

$PAGE->requires->yui2_lib('progressbar');
$PAGE->requires->yui2_lib('json');
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('pluginname', 'report_infiniterooms'));

$integration = new MoodleInfiniteRoomsIntegration();
$log_size = $integration->get_log_size();
$log_done = $integration->get_log_done();
$log_ratio = 100.0 * $log_done / $log_size;

?>

Analytics coverage
<div class="progress">
<div id="infiniterooms_progress_bar"></div>
<div id="infiniterooms_progress_percent"><?php printf('%1.1f%%', $log_ratio) ?></div>
<div id="infiniterooms_progress_words"><?php echo $log_done ?> of <?php echo $log_size ?></div>
</div>
<div class="clearfloat"></div>

<p>
Over time data is processed by the Infinite Rooms analytics engine.<br />
To force the processing of all remaining data, press the button below.<br />
WARNING: This can take several hours if there is a lot of data to process.
</p>

<form action="sync-full.php" method="POST" target="sync-results">
<button>Update analytics</button>
</form>

<script type="text/javascript">
//<![CDATA[
var pb = new YAHOO.widget.ProgressBar({
	maxValue: <?php echo $log_size ?>,
	value: <?php echo $log_done ?>,
	width: '200px',
	height: '20px',
	anim: true
}).render('infiniterooms_progress_bar');

var anim = pb.get('anim');
if (anim) {
	anim.duration = 0.1;
	anim.method = YAHOO.util.Easing.easeOut;
}


var ProgressUpdater = {
	scope: this,
	success: function(o) {
		var data = YAHOO.lang.JSON.parse(o.responseText);
		var current = data.current;
		var total = data.total;
		pb.value = current;
		pb.maxValue = total;

		var percent = Math.round(1000.0 * current / total) / 10.0;
		YAHOO.util.Dom.get('infiniterooms_progress_percent').innerText = "" + percent + "%";
		YAHOO.util.Dom.get('infiniterooms_progress_words').innerText = "" + current + " of " + total;

		ProgressUpdater.repeat();
	},
	failure: function(o) {
		ProgressUpdater.repeat();
	},
	start: function() {
		YAHOO.util.Connect.asyncRequest('GET', 'progress.php', ProgressUpdater);
	},
	repeat: function() {
		window.setTimeout(ProgressUpdater.start, 10000);
	}
 
};
ProgressUpdater.start();

//]]>
</script>

<h2>Status output</h2>
<iframe name="sync-results" style="width: 400px;"></iframe>

<?php
echo $OUTPUT->footer();
?>
