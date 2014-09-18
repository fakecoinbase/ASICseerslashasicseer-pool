<?php
#
include_once('page.php');
#
global $dbg, $dbgstr;
$dbg = false;
$dbgstr = '';
#
function adddbg($str)
{
 global $dbg, $dbgstr;

 if ($dbg === true)
 {
	if ($dbgstr != '')
		$dbgstr .= "\n";
	$dbgstr .= $str;
 }
}
#
function btcfmt($amt)
{
 $amt /= 100000000;
 return number_format($amt, 8);
}
#
global $sipre;
# max of uint64 is ~1.845x10^19, 'Z' is above that (10^21)
# max of uint256 is ~1.158x10^77, which is well above 'Y' (10^24)
$sipre = array('', 'k', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y');
#
function siprefmt($amt)
{
 global $sipre;

 $dot = 2;
 $pref = floor(log10($amt)/3);
 if ($pref < 0)
	$pref = 0;
 if ($pref >= count($sipre))
	$pref = count($sipre)-1;

 $amt = round(100.0 * $amt / pow(10, $pref * 3)) / 100;
 if ($amt > 999.99 && $pref < (count($sipre)-1))
 {
  $amt /= 1000;
  $pref++;
 }

 if ($pref == 0)
  $dot = 0;
 return number_format($amt, $dot).$sipre[$pref];
}
#
function difffmt($amt)
{
 return siprefmt($amt);
}
#
function emailStr($str)
{
 $all = '/[^A-Za-z0-9_+\.@-]/'; // no space = trim
 $beg = '/^[\.@+-]+/';
 $fin = '/[\.@+_-]+$/';
 return preg_replace(array($all,$beg,$fin), '', $str);
}
#
function passrequires()
{
 return "Passwords require 6 or more characters, including<br>" .
	"at least one of each uppercase, lowercase and a digit, but not Tab";
}
#
function safepass($pass)
{
 if (strlen($pass) < 6)
	return false;

 # Invalid characters
 $p2 = preg_replace('/[\011]/', '', $pass);
 if ($p2 != $pass)
	return false;

 # At least one lowercase
 $p2 = preg_replace('/[a-z]/', '', $pass);
 if ($p2 == $pass)
	return false;

 # At least one uppercase
 $p2 = preg_replace('/[A-Z]/', '', $pass);
 if ($p2 == $pass)
	return false;

 # At least one digit
 $p2 = preg_replace('/[0-9]/', '', $pass);
 if ($p2 == $pass)
	return false;

 return true;
}
#
function loginStr($str)
{
 // Anything but . _ / Tab
 $all = '/[\._\/\011]/';
 return preg_replace($all, '', $str);
}
#
function trn($str)
{
 $rep = str_replace(array('<', '>'), array('&lt;', '&gt;'), $str);
 return $rep;
}
#
function htmler($str)
{
 $srch = array('<','>',"\r\n","\n","\r");
 $rep = array('&lt;','&gt;','<br>','<br>','<br>');
 return str_replace($srch, $rep, $str);
}
#
function cvtdbg()
{
 global $dbg, $dbgstr;

 if ($dbg === false || $dbgstr == '')
	$rep = '';
 else
	$rep = htmler($dbgstr).'<br>';

 return $rep;
}
#
function safeinput($txt, $len = 1024, $lf = true)
{
 $ret = trim($txt);
 if ($ret != '')
 {
	if ($lf === true)
		$ret = preg_replace("/[^ -~\r\n]/", '', $ret);
	else
		$ret = preg_replace('/[^ -~]/', '', $ret);

	if ($len > 0)
		$ret = substr($ret, 0, $len);
 }
 return trim($ret);
}
#
function safetext($txt, $len = 1024)
{
 $tmp = substr($txt, 0, $len);

 $res = '';
 for ($i = 0; $i < strlen($tmp); $i++)
 {
	$ch = substr($tmp, $i, 1);
	if ($ch >= ' ' && $ch <= '~')
		$res .= $ch;
	else
	{
		$c = ord($ch);
		$res .= sprintf('0x%02x', $c);
	}
 }

 if (strlen($txt) > $len)
	$res .= '...';

 return $res;
}
#
function dbd($data, $user)
{
 return "<span class=alert><br>Web site is currently down</span>";
}
#
function dbdown()
{
 gopage(NULL, 'dbd', 'dbd', def_menu(), '', '', true, false, false);
}
#
function syse($data, $user)
{
 return "<span class=err><br>System error</span>";
}
#
function syserror()
{
 gopage(NULL, 'syse', 'syse', def_menu(), '', '', true, false, false);
}
#
function f404($data)
{
 return "<span class=alert><br>404</span>";
}
#
function do404()
{
 gopage(NULL, 'f404', 'f404', def_menu(), '', '', true, false, false);
}
#
function showPage($page, $menu, $name, $user)
{
# If you are doing development, use without '@'
# Then switch to '@' when finished
# include_once("page_$page.php");
 @include_once("page_$page.php");

 $fun = 'show_' . $page;
 if (function_exists($fun))
	$fun($page, $menu, $name, $user);
 else
	do404();
}
#
function showIndex()
{
 showPage('index', def_menu(), '', false);
}
#
function offline()
{
 if (file_exists('./maintenance.txt'))
 {
	$ip = $_SERVER['REMOTE_ADDR'];
	if ($ip != '192.168.1.666')
		gopage(NULL, file_get_contents('./maintenance.txt'),
			'offline', NULL, '', '', false, false, false);
 }
}
#
offline();
#
session_start();
#
include_once('db.php');
#
function validUserPass($user, $pass)
{
 $rep = checkPass($user, $pass);
 if ($rep != null)
	 $ans = repDecode($rep);
 usleep(100000); // Max 10x per second
 if ($rep != null && $ans['STATUS'] == 'ok')
 {
	$key = 'ckp'.rand(1000000,9999999);
	$_SESSION['ckpkey'] = $key;
	$_SESSION[$key] = array('who' => $user, 'id' => $user);
 }
}
#
function logout()
{
 if (isset($_SESSION['ckpkey']))
 {
	$key = $_SESSION['ckpkey'];

	if (isset($_SESSION[$key]))
		unset($_SESSION[$key]);

	unset($_SESSION['ckpkey']);
 }
}
#
function requestRegister()
{
 $reg = getparam('Register', false);
 $reg2 = getparam('Reset', false);
 if ($reg !== NULL || $reg2 !== NULL)
 {
	logout();
	return true;
 }
 return false;
}
#
function tryLogInOut()
{
 // If already logged in, it will ignore User/Pass
 if (isset($_SESSION['ckpkey']))
 {
	$logout = getparam('Logout', false);
	if (!nuem($logout) && $logout == 'Logout')
		logout();
 }
 else
 {
	$user = getparam('User', false);
	if ($user !== NULL)
		$user = loginStr($user);
	if (nuem($user))
		return;

	$pass = getparam('Pass', false);
	if (nuem($pass))
		return;

	$login = getparam('Login', false);
	if (nuem($login))
		return;

	validUserPass($user, $pass);
 }
}
#
function validate()
{
 $who = '';
 $whoid = '';

 if (!isset($_SESSION['ckpkey']))
	return array(false, NULL);

 $key = $_SESSION['ckpkey'];
 if (!isset($_SESSION[$key]))
 {
	logout();
	return array(false, NULL);
 }

 if (!isset($_SESSION[$key]['who']))
 {
	logout();
	return array(false, NULL);
 }

 $who = $_SESSION[$key]['who'];

 if (!isset($_SESSION[$key]['id']))
 {
	logout();
	return array(false, NULL);
 }

 $whoid = $_SESSION[$key]['id'];

 return array($who, $whoid);
}
#
function loggedIn()
{
 list($who, $whoid) = validate();
 // false if not logged in
 return $who;
}
#
?>
