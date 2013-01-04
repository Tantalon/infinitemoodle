<?php

require_once('MoodleInfiniteRoomsIntegration.php');

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @global object
 * @return bool
 */
function report_infiniterooms_cron() {
	$integration = new MoodleInfiniteRoomsIntegration();
	$integration->sync();
	return TRUE;
}

