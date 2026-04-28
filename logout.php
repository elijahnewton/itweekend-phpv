<?php
session_start();
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/config.php';
verify_csrf();
session_destroy();
header('Location: /index.php');
exit;
