<?php
// Vérification de l'accès direct
if (!defined('BASE_URL')) {
    header('Location: ' . BASE_URL);
    exit;
}

// Redirection vers la page de connexion après déconnexion
header('Location: ' . BASE_URL . 'auth/login');
exit; 