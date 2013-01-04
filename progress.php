<?php

require(dirname(__FILE__).'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('lib.php');

header('Content-Type: application/json');
$integration = new MoodleInfiniteRoomsIntegration();
echo json_encode(array(
	'current' => $integration->get_log_done(),
	'total' => $integration->get_log_size()
));

