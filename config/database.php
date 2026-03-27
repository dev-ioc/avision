<?php
/**
 * Configuration de la base de données
 */

// Paramètres de connexion à la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'avision');
define('DB_USER', 'root');
define('DB_PASS', '');

// Options PDO par défaut
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
]);

// Création de la connexion PDO
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        DB_OPTIONS
    );
} catch (PDOException $e) {
    custom_log("Erreur de connexion à la base de données : " . $e->getMessage(), 'ERROR');
    die("Une erreur est survenue lors de la connexion à la base de données");
} 