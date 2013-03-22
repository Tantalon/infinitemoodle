<?php

require_once('InfiniteRoomsIntegration.php');

class MoodleInfiniteRoomsIntegration extends InfiniteRoomsIntegration {

	protected function get_config($key) {
		return get_config('report_infiniterooms', $key);
	}

	protected function set_config($key, $value) {
		set_config($key, $value, 'report_infiniterooms');
	}

	protected function get_site_name() {
		global $SITE;
		$cn = null;
		if (empty($cn)) $cn = $SITE->fullname;
		if (empty($cn)) $cn = $SITE->shortname;
		if (empty($cn)) $cn = parent::get_site_name();
		return $cn;
	}

	protected function get_site_contact() {
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

	protected function get_users($since_time) {
		return $this->query("
			SELECT id as sysid,
			username,
			concat_ws(' ', firstname, lastname) as name
			FROM {user}
			WHERE timemodified >= ?
		", array($since_time));
	}

	protected function get_modules($since_time) {
		return $this->query("
			SELECT concat('course_', id) as sysid,
			nullif(idnumber, '#N/A') as idnumber,
			fullname as name
			FROM {course}
			WHERE timemodified >= ?
		", array($since_time));
	}

	protected function get_actions($since_time, $limit) {
		return $this->query("
			SELECT from_unixtime(time, '%Y-%m-%dT%H:%i:%sZ') as time,
			action,
			nullif(userid, 0) as user,
			concat('course_', nullif(course, 0)) as module
			FROM {log}
			WHERE time >= ?
			LIMIT $limit
		", array($since_time));
	}

	protected function query($query, $params) {
		global $DB;
		$rs = $DB->get_recordset_sql($query, $params);
		if (!$rs->valid()) {
			$rs->close();
			$rs = false;
		}
		return $rs;
	}

}

