<?php

require(dirname(__FILE__).'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('lib.php');

header('Content-Type: text/plain');
report_infiniterooms_sync(PHP_INT_MAX);

