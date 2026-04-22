<?php
require_once 'myparam.inc.php';
$conn = oci_connect(MYUSER, MYPASS, MYHOST);
if (!$conn) {
    $e = oci_error();
    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
}
?>
