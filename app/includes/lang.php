<?php

class Lang {
    private static $instance = null;
    private $translations = [];
    private $current_lang = 'en';

    private function __construct() {
        $this->loadFromSession();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadFromSession() {
        if (isset($_SESSION['lang'])) {
            $this->current_lang = $_SESSION['lang'];
        } elseif (isset($_SESSION['user_id'])) {
            // Fetch from database
            $db = new Database();
            $user = $db->fetchOne("SELECT preferred_language FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
            if ($user && !empty($user['preferred_language'])) {
                $this->current_lang = $user['preferred_language'];
                $_SESSION['lang'] = $this->current_lang;
            }
        }
        $this->loadFile($this->current_lang);
    }

    private function loadFile($lang) {
        $file = dirname(dirname(__DIR__)) . "/app/lang/$lang.php";
        if (file_exists($file)) {
            $this->translations = require $file;
        } else {
            // Fallback to English
            $fallback = dirname(dirname(__DIR__)) . "/app/lang/en.php";
            $this->translations = require $fallback;
        }
    }

    public function get($key, $default = null) {
        return $this->translations[$key] ?? ($default ?? $key);
    }

    public function setLanguage($lang) {
        if (preg_match('/^[a-z]{2}$/', $lang)) {
            $this->current_lang = $lang;
            $_SESSION['lang'] = $lang;
            $this->loadFile($lang);
            // Update database if user is logged in
            if (isset($_SESSION['user_id'])) {
                $db = new Database();
                $db->update('users', ['preferred_language' => $lang], "user_id = " . $_SESSION['user_id']);
            }
        }
    }

    public function getCurrentLang() {
        return $this->current_lang;
    }
}

// Global helper function
function __($key, $default = null) {
    return Lang::getInstance()->get($key, $default);
}