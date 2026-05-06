<?php
/**
 * Fonctions de gestion des localisations
 * Gestion des accès basés sur les localisations (client, site, salle)
 */

/**
 * Vérifie si l'utilisateur a accès à une localisation spécifique
 * @param int $clientId ID du client
 * @param int|null $siteId ID du site (optionnel)
 * @param int|null $roomId ID de la salle (optionnel)
 * @return bool true si l'utilisateur a accès
 */
function hasLocationAccess($clientId, $siteId = null, $roomId = null)
{
    $user = $_SESSION['user'] ?? null;

    if (!$user)
        return false;

    // Les administrateurs ont accès à tout
    if (isAdmin())
        return true;

    // Vérifier les localisations de l'utilisateur
    if (isset($user['permissions']) && is_array($user['permissions'])) {
        $locations = $user['permissions']['locations'] ?? [];

        foreach ($locations as $location) {
            if ($location['client_id'] == $clientId) {
                if ($siteId === null) {
                    return true; // Accès au client entier
                }
                if ($location['site_id'] == $siteId) {
                    if ($roomId === null) {
                        return true; // Accès au site entier
                    }
                    if ($location['room_id'] == $roomId) {
                        return true; // Accès à la salle spécifique
                    }
                }
            }
        }
    }

    return false;
}

/**
 * Récupère les localisations autorisées de l'utilisateur
 * @return array Liste des localisations (format original pour buildLocationWhereClause)
 */
function getUserLocations()
{
    $user = $_SESSION['user'] ?? null;
    if (!$user)
        return [];

    // Toujours charger depuis la base pour éviter les permissions de session obsolètes
    global $db;
    try {
        $stmt = $db->prepare(
            "SELECT client_id, site_id,building_id, room_id FROM user_locations WHERE user_id = :user_id"
        );
        $stmt->execute(['user_id' => $user['id']]);
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $locations ?: [];
    } catch (Exception $e) {
        custom_log("Erreur lors du chargement des localisations : " . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * Récupère les localisations autorisées de l'utilisateur formatées pour les contrôleurs
 * @return array Liste des localisations indexée par client_id
 */
function getUserLocationsFormatted()
{
    $user = $_SESSION['user'] ?? null;
    if (!$user)
        return [];

    // Toujours charger depuis la base pour éviter les permissions de session obsolètes
    global $db;
    try {
        $stmt = $db->prepare(
            "SELECT client_id, site_id, room_id FROM user_locations WHERE user_id = :user_id"
        );
        $stmt->execute(['user_id' => $user['id']]);
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formattedLocations = [];
        foreach ($locations as $location) {
            $clientId = $location['client_id'];
            if (!isset($formattedLocations[$clientId])) {
                $formattedLocations[$clientId] = [];
            }
            $formattedLocations[$clientId][] = $location;
        }

        return $formattedLocations;
    } catch (Exception $e) {
        custom_log("Erreur lors du chargement des localisations : " . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * Vérifie si l'utilisateur peut voir les données d'un client spécifique
 * @param int $clientId ID du client
 * @return bool true si l'utilisateur peut voir les données
 */
function canViewClientData($clientId)
{
    // Les staff peuvent voir toutes les données
    if (isStaff())
        return true;

    // Les clients ne peuvent voir que leurs propres données
    if (isClient()) {
        $userClientId = $_SESSION['user']['client_id'] ?? null;
        return $userClientId == $clientId;
    }

    return false;
}
/**
 * Construit une clause WHERE pour filtrer par localisations autorisées
 * 
 * @param array $userLocations Les localisations autorisées de l'utilisateur
 * @param string $clientColumn Nom de la colonne client_id
 * @param string $siteColumn Nom de la colonne site_id
 * @param string $buildingColumn Nom de la colonne building_id
 * @param string $roomColumn Nom de la colonne room_id
 * @return string Clause WHERE SQL
 */
function buildLocationWhereClause($userLocations, $clientColumn = 'client_id', $siteColumn = 'site_id', $buildingColumn = 'building_id', $roomColumn = 'room_id')
{
    if (empty($userLocations)) {
        return '1=0'; // Aucun accès
    }

    $conditions = [];

    foreach ($userLocations as $location) {
        $clientId = $location['client_id'] ?? null;
        $siteId = $location['site_id'] ?? null;
        $buildingId = $location['building_id'] ?? null;
        $roomId = $location['room_id'] ?? null;

        if ($clientId === null) {
            // Accès global (administrateur)
            $conditions[] = '1=1';
            break;
        }

        $subConditions = ["$clientColumn = " . intval($clientId)];

        if ($siteId !== null) {
            $subConditions[] = "$siteColumn = " . intval($siteId);
        }

        if ($buildingId !== null) {
            $subConditions[] = "$buildingColumn = " . intval($buildingId);
        }

        if ($roomId !== null) {
            $subConditions[] = "$roomColumn = " . intval($roomId);
        }

        $conditions[] = '(' . implode(' AND ', $subConditions) . ')';
    }

    return '(' . implode(' OR ', $conditions) . ')';
}