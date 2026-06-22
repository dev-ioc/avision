<?php
/**
 * Point d'entrée principal de l'application
 * Redirige vers le dossier public
 */

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

header('Location: ' . $protocol . '://' . $host . $path . '/public/');
exit;