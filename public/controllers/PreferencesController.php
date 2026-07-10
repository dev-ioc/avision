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

        // On restreint les clés acceptées à un format prévisible
        // (préfixe "datatable_" + tableId + setting, sans underscore interne)
        if (!preg_match('/^datatable_[a-zA-Z0-9]+_[a-zA-Z0-9]+$/', $key)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Clé de préférence invalide']);
            exit;
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