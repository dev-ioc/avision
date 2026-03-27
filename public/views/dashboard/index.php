<?php
/**
 * Point d'entrée du dashboard
 * Redirige vers le dashboard approprié selon le type d'utilisateur
 */

// Inclure les fonctions utilitaires
require_once __DIR__ . '/../../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Utiliser les fonctions helper pour déterminer le type d'utilisateur
if (isClient()) {
    // Inclure le dashboard client
    require_once __DIR__ . '/client.php';
} else {
    // Inclure le dashboard staff
    require_once __DIR__ . '/staff.php';
}
?> 