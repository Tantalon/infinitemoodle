<?php

require_once('InfiniteRoomsIntegration.php');

class MoodleInfiniteRoomsIntegration extends InfiniteRoomsIntegration {

	protected function get_config($key) {
		return get_config('report_infiniterooms', $key);
	}

	protected function set_config($key, $value) {
		set_config($key, $value, 'report_infiniterooms');
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
			concat_ws(' ', firstname, lastname) as name
			FROM {user}
			WHERE timemodified >= ?
		", array($since_time));
	}
	
	public function get_groups($since_time) {
		return $this->query("
			SELECT concat('course_', id) as sysid,
			nullif(idnumber, '#N/A') as idnumber,
			fullname as name
			FROM {course}
			WHERE timemodified >= ?
		", array($since_time));
	}

	public function get_artefacts($since_time) {
		return $this->query("
			SELECT name
			FROM {modules}
		");
	}

	private function get_log_display() {
		$log_display_rs = $this->query("
				SELECT concat(module, '-', action) display_key,
				mtable, field
				FROM {log_display}
		");
		
		$log_display = array();
		foreach ($log_display_rs as $row) {
			$key = $row->display_key;
			$log_display[$key] = (object) array(
				'mtable' => $row->mtable,
				'field' => $row->field);
		}
		return $log_display;
	}

	public function get_modules($since_time) {
		global $DB;
		
		$log_display_lookup = $this->get_log_display();

		$rs = $this->query("
			SELECT cmid as sysid,
				module as artefact,
				concat(module, '-', action) display_key,
				info
			FROM {log}
			WHERE time >= ?
		", array($since_time));
		
		$myrs = array();
		foreach($rs as $row) {
			$display_key = $row->display_key;
			$info = $row->info;
			
			$log_display = @$log_display_lookup[$display_key];
			$display_name = $info;
			if($log_display && is_numeric($info)) {
				$display_name = $DB->get_field(
					$log_display->mtable,
					$log_display->field,
					array('id' => $info));
			}
			
			$myrs[] = (object) array(
				'sysid' => $row->sysid,
				'artefact' => $row->artefact,
				'name' => $display_name
			);
		}

		return $myrs;
	}

	public function get_actions($since_time, $limit) {
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

