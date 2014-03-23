<?php
/**
 * Settings and links
 *
 * @package    infiniterooms
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$ADMIN->add('reports', new admin_externalpage('reportinfiniterooms', get_string('pluginname', 'report_infiniterooms'), "$CFG->wwwroot/report/infiniterooms/index.php",'moodle/site:config'));

// settings that are used-defined
$settings->add(new admin_setting_configtext('report_infiniterooms_accesskey', get_string('accesskey', 'report_infiniterooms'),
                       get_string('configaccesskey', 'report_infiniterooms'), null, PARAM_NOTAGS));

