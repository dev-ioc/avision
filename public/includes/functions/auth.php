<?php
/**
 * Fonctions d'authentification et de session
 * Gestion de l'identité utilisateur
 */

/**
 * Vérifie si l'utilisateur est connecté
 * Redirige vers la page de connexion si non connecté
 */
function checkLogin() {
    if (!isset($_SESSION['user'])) {
        header('Location: ' . BASE_URL . 'auth/login');
        exit();
    }
    return true;
}

/**
 * Récupère le type d'utilisateur actuel
 * @return string|null Le type d'utilisateur ou null
 */
function getUserType() {
    return $_SESSION['user']['user_type'] ?? null;
}

/**
 * Récupère le groupe d'utilisateur actuel
 * @return string|null Le groupe d'utilisateur ou null
 */
function getUserGroup() {
    return $_SESSION['user']['user_group'] ?? null;
}

/**
 * Vérifie si l'utilisateur est d'un type spécifique
 * @param string $type Le type à vérifier
 * @return bool true si l'utilisateur est du type spécifié
 */
function isUserType($type) {
    return getUserType() === $type;
}

/**
 * Vérifie si l'utilisateur est d'un groupe spécifique
 * @param string $group Le groupe à vérifier
 * @return bool true si l'utilisateur est du groupe spécifié
 */
function isUserGroup($group) {
    return getUserGroup() === $group;
}

/**
 * Vérifie si l'utilisateur est administrateur
 * @return bool true si l'utilisateur est administrateur
 */
function isAdmin() {
    $user = $_SESSION['user'] ?? null;
    if (!$user) return false;
    
    // Admin = case à cocher is_admin uniquement
    return isset($user['is_admin']) && $user['is_admin'];
}

/**
 * Vérifie si l'utilisateur est client (groupe Externe)
 * @return bool true si l'utilisateur est client
 */
function isClient() {
    return isUserGroup('Externe');
}

// Autres fonctions de rôles supprimées - le système utilise maintenant groupe + permissions + admin

/**
 * Vérifie si l'utilisateur est staff (membre du personnel)
 * @return bool true si l'utilisateur fait partie du staff
 */
function isStaff() {
    // Staff = groupe Staff uniquement
    return isUserGroup('Staff');
}

/**
 * Récupère l'ID de l'utilisateur actuel
 * @return int|null L'ID de l'utilisateur ou null
 */
function getUserId() {
    return $_SESSION['user']['id'] ?? null;
}
