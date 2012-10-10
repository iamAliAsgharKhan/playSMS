<?php
if(!(defined('_SECURE_'))){die('Intruder alert');};

/**
 * Validate username and password
 * @param string $username Username
 * @param password $password Password
 * @return boolean TRUE when validated or FALSE when validation failed
 */
function validatelogin($username,$password) {
	$db_query = "SELECT password FROM "._DB_PREF_."_tblUser WHERE username='$username'";
	$db_result = dba_query($db_query);
	$db_row = dba_fetch_array($db_result);
	$res_password = trim($db_row['password']);
	if ($password && $res_password && ($password==$res_password)) {
		return true;
	}
	return false;
}

/**
 * Check if ticket is valid, that visitor has access or validated
 * @return boolean TRUE if valid
 */
function valid() {
	if ($_SESSION['username'] && $_SESSION['valid']) {
		return true;
	}
	return false;
}

/**
 * Check if visitor has admin access level
 * @return boolean TRUE if valid and visitor has admin access level
 */
function isadmin() {
	if (valid()) {
		if ($_SESSION['user']['status']==2) {
			return true;
		} else {
			return false;
		}
	}
}

/**
 * Force forward to noaccess page
 */
function forcenoaccess() {
	$error_string = _('You have no access to this page');
	$errid = logger_set_error_string($error_string);
	header("Location: index.php?app=page&inc=noaccess&errid=".$errid);
	exit();
}

/**
 * Process login
 *
 */
function auth_login() {
	global $core_config;
	$username = trim($_REQUEST['username']);
	$password = trim($_REQUEST['password']);
	if ($username && $password) {
		if (validatelogin($username,$password)) {
			$db_query = "UPDATE "._DB_PREF_."_tblUser SET c_timestamp='".mktime()."',ticket='1' WHERE username='$username'";
			if (@dba_affected_rows($db_query)) {
				$_SESSION['sid'] = session_id();
				$_SESSION['username'] = $username;
				$_SESSION['user'] = user_getdatabyusername($username);
				$_SESSION['valid'] = true;
				logger_print("u:".$username." status:".$_SESSION['user']['status']." sid:".$_SESSION['sid']." ip:".$_SERVER['REMOTE_ADDR'], 2, "login");
			} else {
				$error_string = _('Unable to update login session');
			}
		} else {
			$error_string = _('Invalid username or password');
		}
	}
	if (isset($error_string)) {
		$errid = logger_set_error_string($error_string);
		header("Location: ".$core_config['http_path']['base']."/?errid=".$errid);
	} else {
		header("Location: ".$core_config['http_path']['base']);
	}
	exit();
}

/**
 * Process logout
 *
 */
function auth_logout() {
	global $core_config;
	$db_query = "UPDATE "._DB_PREF_."_tblUser SET ticket='0' WHERE username='".$_SESSION['username']."'";
	$db_result = dba_query($db_query);
	logger_print("u:".$_SESSION['username']." status:".$_SESSION['user']['status']." sid:".$_SESSION['sid']." ip:".$_SERVER['REMOTE_ADDR'], 2, "logout");
	@session_destroy();
	$error_string = _('You have been logged out');
	$errid = logger_set_error_string($error_string);
	header("Location: ".$core_config['http_path']['base']."?errid=".$errid);
	exit();
}

/**
 * Process forgot password
 *
 */
function auth_forgot() {
	global $core_config;
	if ($core_config['main']['cfg_enable_forgot']) {
		$username = trim($_REQUEST['username']);
		$email = trim($_REQUEST['email']);
		$error_string = _('Fail to recover password');
		if ($username && $email) {
			$db_query = "SELECT password FROM "._DB_PREF_."_tblUser WHERE username='$username' AND email='$email'";
			$db_result = dba_query($db_query);
			if ($db_row = dba_fetch_array($db_result)) {
				if ($password = $db_row['password']) {
					$subject = "[SMSGW] "._('Password recovery');
					$body = $core_config['main']['cfg_web_title']."\n";
					$body .= $core_config['http_path']['base']."\n\n";
					$body .= _('Username')."\t: $username\n";
					$body .= _('Password')."\t: $password\n\n";
					$body .= $core_config['main']['cfg_email_footer']."\n\n";
					if (sendmail($core_config['main']['cfg_email_service'],$email,$subject,$body)) {
						$error_string = _('Password has been sent to your email');
					} else {
						$error_string = _('Fail to send email');
					}
					logger_print("u:".$username." email:".$email." ip:".$_SERVER['REMOTE_ADDR'], 2, "forgot");
				}
			}
		}
	} else {
		$error_string = _('Recover password disabled');
	}
	$errid = logger_set_error_string($error_string);
	header("Location: ".$core_config['http_path']['base']."?errid=".$errid);
	exit();
}

/**
 * Process register an account
 *
 */
function auth_register() {
	global $core_config;
	$ok = false;
	if ($core_config['main']['cfg_enable_register']) {
		$username = trim($_REQUEST['username']);
		$email = trim($_REQUEST['email']);
		$name = trim($_REQUEST['name']);
		$mobile = trim($_REQUEST['mobile']);
		$error_string = _('Fail to register an account');
		if ($username && $email && $name && $mobile) {
			$continue = true;
			
			// check username
			$db_query = "SELECT username FROM "._DB_PREF_."_tblUser WHERE username='$username'";
			$db_result = dba_query($db_query);
			if ($db_row = dba_fetch_array($db_result)) {
				$error_string = _('User is already exists')." ("._('username').": ".$username.")";
				$continue = false;
			} 
			
			// check email
			if ($continue) {
				$db_query = "SELECT username FROM "._DB_PREF_."_tblUser WHERE email='$email'";
				$db_result = dba_query($db_query);
				if ($db_row = dba_fetch_array($db_result)) {
					$error_string = _('User is already exists')." ("._('email').": ".$email.")";
					$continue = false;
				}
			}
			
			// check mobile
			if ($continue) {
				$db_query = "SELECT username FROM "._DB_PREF_."_tblUser WHERE mobile='$mobile'";
				$db_result = dba_query($db_query);
				if ($db_row = dba_fetch_array($db_result)) {
					$error_string = _('User is already exists')." ("._('mobile').": ".$mobile.")";
					$continue = false;
				}
			}
			
			if ($continue) {
				$password = substr(md5(time()),0,6);
				$sender = ' - '.$username;
				if (ereg("^(.+)(.+)\\.(.+)$",$email,$arr)) {
					// by default the status is 3 (normal user)
					$db_query = "
						INSERT INTO "._DB_PREF_."_tblUser (status,username,password,name,mobile,email,sender,credit)
						VALUES ('3','$username','$password','$name','$mobile','$email','$sender','".$core_config['main']['cfg_default_credit']."')
					";
					if ($new_uid = @dba_insert_id($db_query)) {
						$ok = true;
					}
				}
			}
			if ($ok) {
				logger_print("u:".$username." email:".$email." ip:".$_SERVER['REMOTE_ADDR'], 2, "register");
				$subject = "[SMSGW] "._('New account registration');
				$body = $core_config['main']['cfg_web_title']."\n";
				$body .= $core_config['http_path']['base']."\n\n";
				$body .= _('Username')."\t: $username\n";
				$body .= _('Password')."\t: $password\n\n";
				$body .= $core_config['main']['cfg_email_footer']."\n\n";
				$error_string = _('User has been added')." ("._('username').": ".$username.")";
				$error_string .= "<br />";
				if (sendmail($core_config['main']['cfg_email_service'],$email,$subject,$body)) {
					$error_string .= _('Password has been sent to your email');
				} else {
					$error_string .= _('Fail to send email');
				}
			}
		}
	} else {
		$error_string = _('Public registration disabled');
	}
	$errid = logger_set_error_string($error_string);
	header("Location: ".$core_config['http_path']['base']."?errid=".$errid);
	exit();
}

?>
