<?php
/**
 * Fonctions de gestion des localisations
 * Gestion des accès basés sur les localisations (client, site, bâtiment, salle)
 */

/**
 * Vérifie si l'utilisateur a accès à une localisation spécifique
 * @param int $clientId ID du client
 * @param int|null $siteId ID du site (optionnel)
 * @param int|null $buildingId ID du bâtiment (optionnel)
 * @param int|null $roomId ID de la salle (optionnel)
 * @return bool true si l'utilisateur a accès
 */
function hasLocationAccess($clientId, $siteId = null, $buildingId = null, $roomId = null)
{
    $user = $_SESSION['user'] ?? null;

    if (!$user)
        return false;

    // Les administrateurs ont accès à tout
    if (isAdmin())
        return true;

    // Vérifier les localisations de l'utilisateur
    $locations = getUserLocations();

    foreach ($locations as $location) {
        if ($location['client_id'] == $clientId) {
            // Accès au client entier (si pas de restrictions supplémentaires)
            if ($location['site_id'] === null && $location['building_id'] === null && $location['room_id'] === null) {
                return true;
            }

            // Vérifier l'accès au site
            if ($siteId !== null && $location['site_id'] == $siteId) {
                if ($buildingId === null && $roomId === null) {
                    return true;
                }

                // Vérifier l'accès au bâtiment
                if ($buildingId !== null && $location['building_id'] == $buildingId) {
                    if ($roomId === null) {
                        return true;
                    }

                    // Vérifier l'accès à la salle
                    if ($roomId !== null && $location['room_id'] == $roomId) {
                        return true;
                    }
                }
            }
        }
    }

    return false;
}

/**
 * Récupère les localisations autorisées de l'utilisateur (format brut pour les requêtes SQL)
 * @return array Liste des localisations
 */
function getUserLocations()
{
    $user = $_SESSION['user'] ?? null;
    if (!$user) {
        return [];
    }

    // Vérifier si les localisations sont déjà en cache en session
    if (isset($_SESSION['user_locations_cache']) && is_array($_SESSION['user_locations_cache'])) {
        return $_SESSION['user_locations_cache'];
    }

    // Charger depuis la base de données
    try {
        // S'assurer que $db est disponible
        global $db;
        if (!$db) {
            $config = Config::getInstance();
            $db = $config->getDb();
        }

        $stmt = $db->prepare(
            "SELECT client_id, site_id, building_id, room_id FROM user_locations WHERE user_id = :user_id"
        );
        $stmt->execute(['user_id' => $user['id']]);
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Nettoyer les valeurs (ignorer 0, null, chaînes vides)
        $cleaned = [];
        foreach ($locations as $loc) {
            $clientId = !empty($loc['client_id']) ? (int) $loc['client_id'] : null;
            $siteId = !empty($loc['site_id']) ? (int) $loc['site_id'] : null;
            $buildingId = !empty($loc['building_id']) ? (int) $loc['building_id'] : null;
            $roomId = !empty($loc['room_id']) ? (int) $loc['room_id'] : null;

            if ($clientId !== null) {
                $cleaned[] = [
                    'client_id' => $clientId,
                    'site_id' => $siteId,
                    'building_id' => $buildingId,
                    'room_id' => $roomId
                ];
            }
        }

        // Si aucune localisation trouvée, essayer de récupérer le client_id depuis la session
        if (empty($cleaned) && !empty($user['client_id'])) {
            $cleaned[] = [
                'client_id' => (int) $user['client_id'],
                'site_id' => null,
                'building_id' => null,
                'room_id' => null
            ];
        }

        // Mettre en cache en session
        $_SESSION['user_locations_cache'] = $cleaned;

        return $cleaned;
    } catch (Exception $e) {
        custom_log("Erreur lors du chargement des localisations : " . $e->getMessage(), 'ERROR');

        // Fallback : utiliser le client_id de la session
        if (!empty($user['client_id'])) {
            return [
                [
                    'client_id' => (int) $user['client_id'],
                    'site_id' => null,
                    'building_id' => null,
                    'room_id' => null
                ]
            ];
        }
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
    if (!$user) {
        return [];
    }

    // Vérifier si les localisations formatées sont déjà en cache en session
    if (isset($_SESSION['user_locations_formatted_cache']) && is_array($_SESSION['user_locations_formatted_cache'])) {
        return $_SESSION['user_locations_formatted_cache'];
    }

    // Charger depuis la base de données
    try {
        global $db;
        if (!$db) {
            $config = Config::getInstance();
            $db = $config->getDb();
        }

        $stmt = $db->prepare(
            "SELECT client_id, site_id, building_id, room_id FROM user_locations WHERE user_id = :user_id"
        );
        $stmt->execute(['user_id' => $user['id']]);
        $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formattedLocations = [];
        foreach ($locations as $location) {
            $clientId = !empty($location['client_id']) ? (int) $location['client_id'] : null;
            if ($clientId === null) {
                continue;
            }

            if (!isset($formattedLocations[$clientId])) {
                $formattedLocations[$clientId] = [];
            }

            // Ne garder que les valeurs > 0
            $cleanedLocation = [];
            $cleanedLocation['site_id'] = !empty($location['site_id']) ? (int) $location['site_id'] : null;
            $cleanedLocation['building_id'] = !empty($location['building_id']) ? (int) $location['building_id'] : null;
            $cleanedLocation['room_id'] = !empty($location['room_id']) ? (int) $location['room_id'] : null;

            $formattedLocations[$clientId][] = $cleanedLocation;
        }

        // Si aucune localisation trouvée, utiliser le client_id de la session
        if (empty($formattedLocations) && !empty($user['client_id'])) {
            $clientId = (int) $user['client_id'];
            $formattedLocations[$clientId] = [
                ['site_id' => null, 'building_id' => null, 'room_id' => null]
            ];
        }

        // Mettre en cache en session
        $_SESSION['user_locations_formatted_cache'] = $formattedLocations;

        return $formattedLocations;
    } catch (Exception $e) {
        custom_log("Erreur lors du chargement des localisations : " . $e->getMessage(), 'ERROR');

        // Fallback : utiliser le client_id de la session
        if (!empty($user['client_id'])) {
            $clientId = (int) $user['client_id'];
            return [$clientId => [['site_id' => null, 'building_id' => null, 'room_id' => null]]];
        }
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
    if (isStaff()) {
        return true;
    }

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
 * @param string $clientAlias Alias de la table clients (ex: 'c.id' ou 'client_id')
 * @param string $siteAlias Alias de la table sites (ex: 's.id' ou 'site_id')
 * @param string $buildingAlias Alias de la table buildings (ex: 'b.id' ou 'building_id')
 * @param string $roomAlias Alias de la table rooms (ex: 'r.id' ou 'room_id')
 * @return string Clause WHERE SQL
 */
function buildLocationWhereClause($userLocations, $clientAlias = 'client_id', $siteAlias = 'site_id', $buildingAlias = 'building_id', $roomAlias = 'room_id')
{
    if (empty($userLocations)) {
        return '1=0'; // Aucun accès
    }

    $conditions = [];

    foreach ($userLocations as $location) {
        // Ne garder que les valeurs > 0 (ignorer 0, null, chaînes vides)
        $clientId = isset($location['client_id']) && $location['client_id'] > 0 ? (int) $location['client_id'] : null;
        $siteId = isset($location['site_id']) && $location['site_id'] > 0 ? (int) $location['site_id'] : null;
        $buildingId = isset($location['building_id']) && $location['building_id'] > 0 ? (int) $location['building_id'] : null;
        $roomId = isset($location['room_id']) && $location['room_id'] > 0 ? (int) $location['room_id'] : null;

        // Si aucun client_id valide, ignorer cette localisation
        if ($clientId === null) {
            continue;
        }

        $subConditions = [];

        // Condition client
        $subConditions[] = "{$clientAlias} = {$clientId}";

        // Condition site (optionnelle)
        if ($siteId !== null) {
            $subConditions[] = "{$siteAlias} = {$siteId}";
        }

        // Condition bâtiment (optionnelle)
        if ($buildingId !== null) {
            $subConditions[] = "{$buildingAlias} = {$buildingId}";
        }

        // Condition salle (optionnelle)
        if ($roomId !== null) {
            $subConditions[] = "{$roomAlias} = {$roomId}";
        }

        $conditions[] = '(' . implode(' AND ', $subConditions) . ')';
    }

    if (empty($conditions)) {
        return '1=0';
    }

    return '(' . implode(' OR ', $conditions) . ')';
}

/**
 * Vide le cache des localisations en session
 * Utile après modification des permissions
 */
function clearUserLocationsCache()
{
    unset($_SESSION['user_locations_cache']);
    unset($_SESSION['user_locations_formatted_cache']);
}

/**
 * Récupère l'ID du client à partir des localisations de l'utilisateur
 * @return int|null ID du client ou null si plusieurs clients
 */
function getPrimaryClientIdFromLocations()
{
    $locations = getUserLocations();

    if (empty($locations)) {
        return null;
    }

    // Récupérer les IDs de clients uniques
    $clientIds = [];
    foreach ($locations as $location) {
        if ($location['client_id'] !== null && !in_array($location['client_id'], $clientIds)) {
            $clientIds[] = $location['client_id'];
        }
    }

    // Si un seul client, retourner son ID
    if (count($clientIds) === 1) {
        return $clientIds[0];
    }

    // Sinon, retourner null (accès à plusieurs clients ou aucun)
    return null;
}