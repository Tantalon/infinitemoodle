<?php

require(dirname(__FILE__).'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('lib.php');

header('Content-Type: text/plain');
$integration = new MoodleInfiniteRoomsIntegration();
$integration->send("import/group", $integration->get_groups(0));

