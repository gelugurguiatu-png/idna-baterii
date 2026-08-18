<?php
// Logout operator.
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/auth.php';

auth_porneste_sesiune();
$_SESSION = array();
session_destroy();
echo json_encode(array('ok' => true));
