<?php
/**
 * Fonctions de gestion des tickets/contrats
 * Vérification si un contrat utilise un système de tickets
 */

/**
 * Vérifie si un contrat est de type ticket selon son ID
 * 
 * @param int $contractId L'ID du contrat à vérifier
 * @return bool True si le contrat utilise un système de tickets
 */
function isContractTicketById($contractId) {
    if (empty($contractId) || !is_numeric($contractId)) {
        return false;
    }
    
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT isticketcontract FROM contracts WHERE id = ?");
        $stmt->execute([$contractId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (bool)$result['isticketcontract'] : false;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification du contrat ticket: " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifie si une intervention est liée à un contrat à tickets
 * 
 * @param int $interventionId L'ID de l'intervention à vérifier
 * @return bool True si l'intervention est liée à un contrat à tickets
 */
function isInterventionLinkedToTicketContract($interventionId) {
    if (empty($interventionId) || !is_numeric($interventionId)) {
        return false;
    }
    
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT c.isticketcontract 
            FROM interventions i 
            LEFT JOIN contracts c ON i.contract_id = c.id 
            WHERE i.id = ?
        ");
        $stmt->execute([$interventionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (bool)$result['isticketcontract'] : false;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification du contrat ticket pour l'intervention: " . $e->getMessage());
        return false;
    }
}
