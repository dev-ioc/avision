<?php
require_once __DIR__ . '/../classes/Models/BaseModel.php';

class ExcelModel extends BaseModel
{
    protected $primaryKey = 'id';

    public function __construct($db)
    {
        log_debug("Initialisation d'ExcelModel");
        parent::__construct($db);
        $this->table = 'materiel';
        log_debug("Table configurée: {$this->table}");
    }

    /**
     * Met à jour plusieurs lignes de matériel
     * @param array $data Données à mettre à jour (tableau de lignes)
     * @return array Résultat avec compteurs et erreurs
     */
    public function updateMultipleMateriel($data)
    {
        log_debug("updateMultipleMateriel - Début du traitement", [
            'total_items' => count($data),
            'is_associative' => $this->isAssociative($data)
        ]);
        if ($this->isAssociative($data)) {
            log_debug("Traitement d'une seule ligne");
            $data = [$data];
        }

        $updated = 0;
        $errors = [];

        foreach ($data as $index => $row) {
            log_debug("Traitement de la ligne " . ($index + 1), array_keys($row));
            if (empty($row['id'])) {
                $errorMsg = "Ligne " . ($index + 1) . ": ID manquant";
                $errors[] = $errorMsg;
                log_error($errorMsg, $row);
                continue;
            }

            $id = (int) $row['id'];
            if ($id <= 0) {
                $errorMsg = "Ligne " . ($index + 1) . ": ID invalide ({$id})";
                $errors[] = $errorMsg;
                log_error($errorMsg);
                continue;
            }

            try {
                $updateData = $this->prepareUpdateData($row);

                log_debug("Ligne {$id} - Données préparées", [
                    'fields_count' => count($updateData),
                    'fields' => array_keys($updateData)
                ]);

                if (empty($updateData)) {
                    $errorMsg = "Ligne " . ($index + 1) . " (ID {$id}): Aucune donnée à mettre à jour";
                    $errors[] = $errorMsg;
                    log_debug($errorMsg);
                    continue;
                }
                if ($this->update($id, $updateData)) {
                    $updated++;
                    log_debug("Ligne {$id} - Mise à jour réussie");
                } else {
                    $errorMsg = "Ligne " . ($index + 1) . " (ID {$id}): Échec de la mise à jour";
                    $errors[] = $errorMsg;
                    log_error($errorMsg);
                }

            } catch (Exception $e) {
                $errorMsg = "Ligne " . ($index + 1) . " (ID {$id}): " . $e->getMessage();
                $errors[] = $errorMsg;
                log_error($errorMsg, [
                    'exception' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            }
        }

        $result = [
            'updated' => $updated,
            'errors' => $errors,
            'total' => count($data)
        ];

        log_debug("updateMultipleMateriel - Résultat final", $result);

        return $result;
    }

    /**
     * Vérifie si un tableau est associatif
     * @param array $array Tableau à vérifier
     * @return bool
     */
    private function isAssociative($array)
    {
        if (empty($array)) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Prépare les données pour la mise à jour
     * @param array $row Ligne de données
     * @return array Données formatées
     */
    private function prepareUpdateData($row)
    {
        $updateData = [];
        $fields = [
            'marque',
            'modele',
            'type_materiel',
            'numero_serie',
            'version_firmware',
            'adresse_ip',
            'adresse_mac',
            'date_fin_maintenance',
            'reference',
            'usage_materiel',
            'ancien_firmware',
            'masque',
            'passerelle',
            'login',
            'password',
            'ip_primaire',
            'mac_primaire',
            'ip_secondaire',
            'mac_secondaire',
            'stream_aes67_recu',
            'stream_aes67_transmis',
            'ssid',
            'type_cryptage',
            'password_wifi',
            'libelle_pa_salle',
            'numero_port_switch',
            'vlan',
            'date_fin_garantie',
            'date_derniere_inter',
            'commentaire',
            'url_github'
        ];

        foreach ($fields as $field) {
            if (isset($row[$field]) && $row[$field] !== '' && $row[$field] !== null) {
                // Formater les dates si nécessaire
                if (in_array($field, ['date_fin_maintenance', 'date_fin_garantie', 'date_derniere_inter'])) {
                    $formattedDate = $this->formatDateForDb($row[$field]);
                    if ($formattedDate) {
                        $updateData[$field] = $formattedDate;
                    }
                } else {
                    $updateData[$field] = trim($row[$field]);
                }
            }
        }

        return $updateData;
    }

    /**
     * Formate une date pour la base de données
     * @param string $date Date à formater
     * @return string|null Date formatée ou null
     */
    private function formatDateForDb($date)
    {
        if (empty($date))
            return null;

        $date = trim($date);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))
            return $date;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $m)) {
            return checkdate($m[2], $m[1], $m[3]) ? "{$m[3]}-{$m[2]}-{$m[1]}" : null;
        }

        // Format DD-MM-YYYY
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $m)) {
            return checkdate($m[2], $m[1], $m[3]) ? "{$m[3]}-{$m[2]}-{$m[1]}" : null;
        }

        return null;
    }
}
?>