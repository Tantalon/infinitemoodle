<?php

/**
 * A remote call to the Infinite Rooms API, using a cilent-side certificate for authentication.
 */
function report_infiniterooms_remote($method, $url, $payload = null) {
	$report_infiniterooms_url = 'https://localhost/infiniterooms/api';
	$url = $report_infiniterooms_url . '/' . $url;

	$creds = report_infiniterooms_get_credentials();
	$creds_file = tempnam(sys_get_temp_dir(), "infiniterooms-cert");
	file_put_contents($creds_file, $creds);

	$ch = curl_init($url);
	#curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
	#curl_setopt($ch, CURLOPT_HEADER, TRUE);
	curl_setopt($ch, CURLOPT_SSLCERT, $creds_file);
	curl_setopt($ch, CURLOPT_SSLKEY, $creds_file);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	#curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
	#curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	#curl_setopt($ch, CURLOPT_FAILONERROR, TRUE);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
	
	if ($method == 'PUT') {
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Transfer-Encoding: chunked'));
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_UPLOAD, TRUE);
		curl_setopt($ch, CURLOPT_READFUNCTION, function($ch, $fd, $length) use ($payload) {
		    return fread($payload, $length) ?: '';
		});
	}

	$result = curl_exec($ch);
	if ($result === FALSE) die("Remote call to $url failed: " . curl_error($ch));

	curl_close($ch);
	unlink($creds_file);

	return $result;
}

function report_infiniterooms_get_credentials() {
	$creds = get_config('report_infiniterooms', 'credentials');
	if (empty($creds)) {
		$creds = report_infiniterooms_create_credentials();
		set_config('credentials', $creds, 'report_infiniterooms');
	}
	return $creds;
}

function report_infiniterooms_create_credentials($cn = NULL) {
	global $CFG, $SITE;

	# find an approperiate common name
	if (empty($cn)) $cn = $SITE->fullname;
	if (empty($cn)) $cn = $SITE->shortname;
	if (empty($cn)) $cn = php_uname('n');

	# build the dn
	$dn = array(
		"countryName" => 'GB',
		"organizationName" => 'Infinite Rooms',
		"organizationalUnitName" => 'Client',
		"commonName" => $cn
	);

	# include a contact email if one is available
	$email = $CFG->supportemail;
	if (!is_null($email)) $dn['emailAddress'] = $email;

	# certificate configuration options
	$openssl_config = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'openssl.cnf';
	$lifespan = 365 * 10;
	$configargs = array(
		'config' => $openssl_config,
		'private_key_bits' => 2048,
		
	);

	#print "dn: " . implode(',', $dn) . "\n";
	#print "openssl config: $openssl_config\n";

	$keypair = openssl_pkey_new($configargs);
	$csr = openssl_csr_new($dn, $keypair, $configargs);
	if (!$csr) die('Unable to generate certificate signing request: ' . openssl_error_string());
	$sscert = openssl_csr_sign($csr, null, $keypair, $lifespan);
	openssl_x509_export($sscert, $publickey);
	openssl_pkey_export($keypair, $privatekey, NULL);

	#print "publickey: $publickey\n";
	#print "privatekey: $privatekey\n";

	// return certificate and private key
	$creds = $publickey . $privatekey;
	return $creds;
}

function report_infiniterooms_get_last_sync() {
	$last_entry_unixtime = 0;
	$last_entry_rfc3339 = report_infiniterooms_remote('GET', 'import/last-updated');
	if (!empty($last_entry_rfc3339)) {
		$last_entry_datetime = DateTime::createFromFormat(DateTime::RFC3339, $last_entry_rfc3339, new DateTimeZone('UTC'));
		$last_entry_unixtime = $last_entry_datetime->getTimestamp();
	}
	return $last_entry_unixtime;
}

function convert_date($format_in, $format_out, $datetime_in) {
        $utc = new DateTimeZone('UTC');
        $datetime = DateTime::createFromFormat($format_in, $datetime_in, $utc);
        if (!$datetime) {
                $errors = DateTime::getLastErrors();
                header('HTTP/1. 400 Bad Request');
                die("Failed to parse date $datetime_in as " . $format_in . " due to " . $errors['errors'][0]);
        }
        $datetime->setTimezone($utc);
        return $datetime->format($format_out);
}

function report_infiniterooms_get_log_size() {
	global $DB;
	return $DB->count_records('log');
}

function report_infiniterooms_get_log_done() {
	global $DB;
	$server_time = report_infiniterooms_get_last_sync();
	return $DB->count_records_select('log', 'time <= ?', array($server_time));
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @global object
 * @return bool
 */
function report_infiniterooms_cron() {
	report_infiniterooms_sync();
	return TRUE;
}

function report_infiniterooms_sync($limit = 10000) {
	$last_time = report_infiniterooms_get_last_sync();

	report_infiniterooms_send(
		"import/user",
		"SELECT id as sysid,
			username,
			concat_ws(' ', firstname, lastname) as name
			FROM {user}
			WHERE timemodified >= ?",
		array($last_time));

	report_infiniterooms_send(
		"import/module",
		"SELECT concat('course_', 0) as sysid,
			nullif(idnumber, '#N/A') as idnumber,
			fullname as name
			FROM {course}
			WHERE timemodified >= ?",
		array($last_time));

	report_infiniterooms_send(
		"import/action",
		"SELECT from_unixtime(time, '%Y-%m-%dT%H:%i:%sZ') as time,
			action,
			nullif(userid, 0) as user,
			concat('course_', nullif(course, 0)) as module
			FROM {log}
			WHERE time >= ?
			LIMIT $limit",
		array($last_time));

}

function report_infiniterooms_send($target, $query, $params) {
	global $DB;

	// Ideally we should be streaming this to reduce memory requirements
	$buffer = fopen('php://temp', 'w+b');
	//stream_filter_append($buffer, "zlib.deflate", STREAM_FILTER_WRITE);

	$fields = null;
	$rs = $DB->get_recordset_sql($query, $params);
	foreach ($rs as $record) {
		if (is_null($fields)) {
			$fields = array_keys(get_object_vars($record));
			fwrite($buffer, implode(',', $fields));
			fwrite($buffer, "\n");
		}

		$values = array();
		foreach ($fields as $field) {
			array_push($values, $record->{$field});
		}

		fputcsv($buffer, $values);
	}
	$rs->close();

	// print out csv for debugging
	/*
	rewind($buffer);
	$out = fopen('php://output', 'w+b');
	stream_copy_to_stream($buffer, $out);
	fclose($out);
	*/

	// send the data
	rewind($buffer);
	print report_infiniterooms_remote('PUT', $target, $buffer);
	fclose($buffer);
}

