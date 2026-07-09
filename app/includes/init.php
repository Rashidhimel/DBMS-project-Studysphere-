<?php

session_start();
ob_start(); // Start output buffering

require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(dirname(__DIR__)));
$dotenv->load();

error_reporting(E_ALL);
ini_set('display_errors', 1);


define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');


require_once APP_PATH . '/includes/db.php';
require_once APP_PATH . '/includes/auth.php';
require_once APP_PATH . '/includes/helpers.php';
require_once APP_PATH . '/includes/lang.php';
require_once APP_PATH . '/includes/mail_helper.php';
require_once APP_PATH . '/includes/match_engine.php';
Lang::getInstance();


spl_autoload_register(function($class) {
    
    $filename1 = strtolower($class) . '.php';
    $file1 = APP_PATH . '/includes/' . $filename1;
    
    if (file_exists($file1)) {
        require_once $file1;
        return;
    }
    
    
    $filename2 = strtolower(str_replace('_', '', $class)) . '.php';
    $file2 = APP_PATH . '/includes/' . $filename2;
    if (file_exists($file2)) {
        require_once $file2;
        return;
    }
    
    
    $manual_files = [
        'uploadhelper.php',
        'upload_helper.php',
        'match_engine.php',
        'helpers.php',
        'db.php',
        'auth.php'
    ];
    
    foreach ($manual_files as $manual_file) {
        $manual_path = APP_PATH . '/includes/' . $manual_file;
        if (file_exists($manual_path)) {
            require_once $manual_path;
            break;
        }
    }
});


function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /studysphere/public/login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header('Location: /studysphere/public/index.php');
        exit();
    }
}

function requireStudent() {
    requireLogin();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
        header('Location: /studysphere/public/index.php');
        exit();
    }
}

function requireCounselor() {
    requireLogin();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'counselor') {
        header('Location: /studysphere/public/index.php');
        exit();
    }
}
?>