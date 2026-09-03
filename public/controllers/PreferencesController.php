<?php

/**
 * Contrôleur générique pour les préférences utilisateur persistées en base
 * (ex: pageLength d'un DataTable, etc.)
 *
 * Note CSRF : la vérification est déjà assurée en amont par le middleware
 * global (checkCsrfOrFail() dans index.php, appelé avant le routage pour
 * toute requête modifiante). Pas besoin de la revalider ici.
 */
class PreferencesController
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? ($GLOBALS['db'] ?? null);
    }

    /**
     * POST preferences/save
     * Corps JSON attendu : { "key": "datatable_xxx_yyy", "value": "..." }
     */
    public function save()
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_SESSION['user'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Non authentifié']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $key = $input['key'] ?? '';
        $value = $input['value'] ?? '';

        // Clé attendue : "datatable_" suivi d'un ou plusieurs segments
        // alphanumériques séparés par des underscores.
        // Ex : datatable_usersTable_v4_pageLength, datatable_usersTable_v4_order, ...
        // (assoupli pour supporter les identifiants de table versionnés,
        // ex "usersTable_v4", qui ajoutent des underscores supplémentaires)
        if (!preg_match('/^datatable_[a-zA-Z0-9_]+$/', $key)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Clé de préférence invalide']);
            exit;
        }

        // CORRECTIF : si le client envoie un objet/tableau (ex: la visibilité
        // des colonnes du tableau interventions, un objet {colonne: bool}),
        // on le sérialise en JSON plutôt que de laisser (string) le
        // transformer silencieusement en "Array".
        if (is_array($value)) {
            $value = json_encode($value);
        }

        $value = (string) $value;
        if (strlen($value) > 255) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valeur trop longue']);
            exit;
        }

        $ok = setUserPreference($key, $value);

        echo json_encode(['success' => $ok]);
        exit;
    }
}