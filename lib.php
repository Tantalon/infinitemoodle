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
	if (empty($creds)) $creds = report_infiniterooms_create_credentials();
	return $creds;
}

function report_infiniterooms_create_credentials() {
	global $CFG, $SITE;

	# find an approperiate common name
	$cn = $SITE->fullname;
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

	// save certificate and private key
	$creds = $publickey . $privatekey;
	set_config('credentials', $creds, 'report_infiniterooms');
	return $creds;
}

function report_infiniterooms_get_last_sync() {
	$last_entry = report_infiniterooms_remote('GET', 'log/last-modified.php');
	if (!is_numeric($last_entry)) {
		$last_entry = 0;
	}
	return $last_entry;
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
	$last_time = report_infiniterooms_get_last_sync();

	report_infiniterooms_send(
		'log',
		'SELECT id, time, userid, ip, course, module, cmid, action, url, info FROM {log} WHERE time >= ?',
		array($last_time));

	return TRUE;
}

function report_infiniterooms_send($type, $query, $params) {
	global $DB;

	// Ideally we should be streaming this to reduce memory requirements
	$buffer = fopen('php://temp', 'w+b');
	//stream_filter_append($buffer, "zlib.deflate", STREAM_FILTER_WRITE);

	$limitfrom = 0;
	$limitnum = 100;
	$fields = null;
	$rs = $DB->get_recordset_sql($query, $params, $limitfrom, $limitnum);
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
	print report_infiniterooms_remote('PUT', "moodle/$type", $buffer);
	fclose($buffer);
}

