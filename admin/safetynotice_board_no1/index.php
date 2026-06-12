<?php
$base = rtrim(str_replace('\\', '/', str_replace(rtrim($_SERVER['DOCUMENT_ROOT'],'/\\'), '', dirname(dirname(__FILE__)))), '/');
header('Location: ' . $base . '/board_admin.php?board=safety_board_1');
exit;
