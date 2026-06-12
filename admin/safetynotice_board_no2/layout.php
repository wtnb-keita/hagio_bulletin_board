<?php
$base = rtrim(str_replace('\\', '/', str_replace(rtrim($_SERVER['DOCUMENT_ROOT'],'/\\'), '', dirname(dirname(dirname(__FILE__))))), '/');
header('Location: ' . $base . '/admin/layout_editor.php?board=safety_board_2');
exit;
