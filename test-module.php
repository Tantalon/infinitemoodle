<?php

require(dirname(__FILE__).'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('lib.php');

header('Content-Type: text/plain');
$integration = new MoodleInfiniteRoomsIntegration();
$modules = $integration->get_modules(0);
foreach($modules as $module) {
	print "$module->sysid,$module->name\n";
}

