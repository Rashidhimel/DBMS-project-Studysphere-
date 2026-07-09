<?php
require_once '../app/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['lang'])) {
    $lang = $_POST['lang'];
    Lang::getInstance()->setLanguage($lang);
}
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;