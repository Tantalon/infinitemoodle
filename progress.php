<?php

require(dirname(__FILE__).'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('lib.php');

header('Content-Type: application/json');
echo json_encode(array(
	'current' => report_infiniterooms_get_log_done(),
	'total' => report_infiniterooms_get_log_size()
));

