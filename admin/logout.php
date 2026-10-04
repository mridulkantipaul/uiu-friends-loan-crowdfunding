<?php
require_once __DIR__ . '/../includes/functions.php';
unset($_SESSION['admin_id']);
header('Location: login.php');
exit;
