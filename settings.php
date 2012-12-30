<?php
/**
 * Settings and links
 *
 * @package    infiniterooms
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$ADMIN->add('reports', new admin_externalpage('reportinfiniterooms', get_string('pluginname', 'report_infiniterooms'), "$CFG->wwwroot/report/infiniterooms/index.php",'moodle/site:config'));

// no report settings
$settings = null;
