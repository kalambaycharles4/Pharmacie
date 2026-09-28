<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
}

session_destroy();
header('Location: login.php');
exit;
?>