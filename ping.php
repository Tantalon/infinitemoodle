<?php

// Ping the remote API, useful for testing

require(dirname(__FILE__).'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('lib.php');

header('Content-Type: text/plain');
$integration = new MoodleInfiniteRoomsIntegration();
print $integration->authenticated_ping();

