<?php

// Run the export immediately, useful for testing

require(dirname(__FILE__).'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('lib.php');

header('Content-Type: text/plain');
print report_infiniterooms_cron();

