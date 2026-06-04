<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ekoidumotamarket');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application configuration
define('APP_DEBUG', true);
define('APP_NAME', 'Eko Idumota Market Web App');

// URL configuration
define( 'BASE_URL', 'https://github.com/jephymvc' );

// Path constants
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . '/app');
}
if (!defined('CORE_PATH')) {
    define('CORE_PATH', ROOT_PATH . '/core');
}
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', ROOT_PATH . '/config');
}