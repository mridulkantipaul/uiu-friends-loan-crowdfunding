<?php
/**
 * UIU Friends Loan & Crowdfunding
 * Database connection (PDO / MySQL)
 * ---------------------------------
 * Update the constants below to match your local MySQL setup
 * (XAMPP / WAMP / LAMP / a hosted MySQL instance).
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'uiu_friends');
define('DB_USER', 'root');
define('DB_PASS', '');
define('SITE_NAME', 'UIU Friends Loan & Crowdfunding');
define('SITE_SHORT', 'UIU Friends');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed. Please import database/schema.sql and check config/db.php. (" . $e->getMessage() . ")");
}
