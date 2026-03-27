<?php
require_once __DIR__ . '/../classes/Models/BaseModel.php';

/**
 * Modèle pour la gestion des durées d'intervention
 */
class DurationModel extends BaseModel {
    public function __construct($db) {
        parent::__construct($db);
        $this->table = 'intervention_durations';
    }

    /**
     * Récupère toutes les durées disponibles
     */
    public function getAll() {
        return $this->findAll([], ['orderBy' => 'duration']);
    }
} 