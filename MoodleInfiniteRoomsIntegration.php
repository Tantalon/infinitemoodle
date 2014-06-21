<?php

require_once('InfiniteRoomsIntegration.php');
require_once('lib/CallbackMappingIterator.php');

class MoodleInfiniteRoomsIntegration extends InfiniteRoomsIntegration {

	protected function get_config($key) {
		return get_config('report_infiniterooms', $key);
	}

	protected function set_config($key, $value) {
		set_config($key, $value, 'report_infiniterooms');
	}

	protected function get_access_key() {
		return get_config(null, 'report_infiniterooms_accesskey');
	}

	public function get_site_name() {
		global $SITE;
		$cn = null;
		if (empty($cn)) $cn = $SITE->fullname;
		if (empty($cn)) $cn = $SITE->shortname;
		if (empty($cn)) $cn = parent::get_site_name();
		return $cn;
	}

	public function get_site_contact() {
		global $CFG;
		return $CFG->supportemail;
	}

	public function get_log_size() {
		global $DB;
		return $DB->count_records('log');
	}

	public function get_log_done() {
		global $DB;
		$server_time = $this->get_last_sync();
		return $DB->count_records_select('log', 'time <= ?', array($server_time));
	}

	public function get_users($since_time) {
		return $this->query("
			SELECT id as sysid,
			username,
			nullif(idnumber, '#N/A') as idnumber,
			concat_ws(' ', firstname, lastname) as name,
			email
			FROM {user}
			WHERE timemodified >= ?
		", array($since_time));
	}
	
	public function get_groups($since_time) {
		return $this->query("
			SELECT concat('course_', id) as sysid,
			'1' as type,
			nullif(idnumber, '#N/A') as idnumber,
			fullname as name
			FROM {course}
			WHERE timemodified >= ?
		", array($since_time));
	}

	public function get_artefacts($since_time) {
		return $this->query("
			SELECT DISTINCT(module) as sysname
			FROM {log}
		");
	}

	public function get_modules($since_time) {		
		// this can be done more efficiently, but it would compromise portability

		$metadata_rs = $this->query("
			SELECT cm.id cmid, cm.instance, m.name module, ld.mtable, ld.field,
			concat('course_', cm.course) as `group`
			FROM {course_modules} cm
			INNER JOIN {modules} m ON m.id = cm.module
			LEFT JOIN {log_display} ld ON ld.module = m.name AND ld.action = 'view'
		");
		
		return new CallbackMappingIterator($metadata_rs, function($key, $metadata) {
			global $DB;
			
			$name = $DB->get_field(
				$metadata->mtable ?: $metadata->module,
				$metadata->field ?: 'name',
				array('id' => $metadata->instance));
			
			// need group and artefact here!
			return (object) array(
				'sysid' => $metadata->cmid,
				'name' => $name,
				'artefact' => $metadata->module,
				'group' => $metadata->group
			);
		});
	}

	public function get_actions($since_time, $limit) {
		return $this->query("
			SELECT from_unixtime(l.time, '%Y-%m-%dT%H:%i:%sZ') as time,
			l.action,
			nullif(l.userid, 0) as user,
			l.ip as user_ip,
			l.module as artefact,
			concat('course_', nullif(l.course, 0)) as `group`,
			cm.id as module,
			l.id as sysid,
			l.info as sysinfo
			FROM {log} l
			left join {modules} m on m.name = l.module
			left join {course_modules} cm on cm.module = m.id
			WHERE l.time >= ?
			LIMIT $limit
		", array($since_time));
	}

	protected function query($query, $params = array()) {
		global $DB;
		$rs = $DB->get_recordset_sql($query, $params);
		if (!$rs->valid()) {
			$rs->close();
			$rs = false;
		}
		return $rs;
	}

}

