<?php
/**
 * Fonctions de formatage
 * Formatage de dates, montants, échappement HTML
 */

/**
 * Échappe une chaîne pour l'affichage HTML sécurisé
 * 
 * Version unifiée qui remplace h() et safeHtml()
 * Gère les valeurs null et vides avec une valeur par défaut optionnelle
 * 
 * @param string|null $string La chaîne à échapper
 * @param string $default La valeur par défaut si $string est null ou vide
 * @return string La chaîne échappée
 */
function h($string, $default = '') {
    if ($string === null || $string === '') {
        return htmlspecialchars($default, ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Échappe une chaîne pour l'affichage HTML sécurisé
 * 
 * @deprecated Utiliser h() à la place. Cette fonction est un alias pour compatibilité.
 * @param string|null $value La chaîne à échapper
 * @param string $default La valeur par défaut si $value est null ou vide
 * @return string La chaîne échappée
 */
function safeHtml($value, $default = '') {
    return h($value, $default);
}

/**
 * Formate une date pour l'affichage (format français d/m/Y)
 * 
 * Version unifiée et robuste qui remplace formatDate() et formatDateFrench()
 * Gère les valeurs vides et les erreurs de parsing
 * 
 * @param string|null $date La date à formater (peut être null ou vide)
 * @param string $format Le format de date (par défaut 'd/m/Y')
 * @return string La date formatée ou chaîne vide si date invalide
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) {
        return '';
    }
    
    try {
        $dateObj = new DateTime($date);
        return $dateObj->format($format);
    } catch (Exception $e) {
        // Retourner la date originale si erreur de parsing
        return $date;
    }
}

/**
 * Formate une date pour l'affichage (format français d/m/Y)
 * 
 * @deprecated Utiliser formatDate() à la place. Cette fonction est un alias pour compatibilité.
 * @param string|null $date La date à formater
 * @return string La date formatée
 */
function formatDateFrench($date) {
    return formatDate($date, 'd/m/Y');
}

/**
 * Formate un montant pour l'affichage
 * @param float $amount Le montant à formater
 * @return string Le montant formaté
 */
function formatAmount($amount) {
    return number_format($amount, 2, ',', ' ') . ' €';
}

/**
 * Récupère l'année courante
 * @return string L'année courante
 */
function getCurrentYear() {
   return date('Y');
}
