<?php
/**
 * Contrôleur pour les statistiques d'interventions
 */
class StatsController
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function index()
    {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        $filters = [
            'type' => $_GET['type'] ?? 'curatives',
            'technician_id' => $_GET['technician_id'] ?? null,
            'status_id' => $_GET['status_id'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
            'priority_id' => $_GET['priority_id'] ?? null,
        ];

        if (!in_array($filters['type'], ['curatives', 'preventives', 'all'])) {
            $filters['type'] = 'curatives';
        }
        $priorities = $this->getPriorities();
        $technicians = $this->getTechnicians();
        $statuses = $this->getStatuses();

        $statsByTab = [
            'curatives' => $this->getTabCount(['type' => 'curatives']),
            'preventives' => $this->getTabCount(['type' => 'preventives']),
            'all' => $this->getTabCount(['type' => 'all']),
        ];

        $interventionsStats = $this->getInterventionsStats($filters);
        $curativesOnSite = 0;
        $curativesRemote = 0;
        $preventivesOnSite = 0;
        $preventivesRemote = 0;

        foreach ($interventionsStats as $interv) {
            if ((int) ($interv['is_preventive'] ?? 0) === 0) {
                $curativesOnSite += $interv['on_site_minutes'];
                $curativesRemote += $interv['remote_minutes'];
            } else {
                $preventivesOnSite += $interv['on_site_minutes'];
                $preventivesRemote += $interv['remote_minutes'];
            }
        }

        $totalOnSiteMinutes = $curativesOnSite + $preventivesOnSite;
        $totalRemoteMinutes = $curativesRemote + $preventivesRemote;

        extract([
            'filters' => $filters,
            'technicians' => $technicians,
            'statuses' => $statuses,
            'statsByTab' => $statsByTab,
            'interventionsStats' => $interventionsStats,
            'totalOnSiteMinutes' => $totalOnSiteMinutes,
            'totalRemoteMinutes' => $totalRemoteMinutes,
            'curativesOnSite' => $curativesOnSite,
            'curativesRemote' => $curativesRemote,
            'preventivesOnSite' => $preventivesOnSite,
            'preventivesRemote' => $preventivesRemote,
        ]);

        require_once __DIR__ . '/../views/stats/index.php';
    }

    private function getTabCount(array $tabFilters): array
    {
        $where = ['i.status_id != 0'];
        $params = [];

        $this->applyTypeCondition($tabFilters['type'] ?? 'all', $where, $params);

        $sql = "SELECT COUNT(DISTINCT i.id) AS total
                FROM interventions i
                WHERE " . implode(' AND ', $where);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return ['total' => (int) ($row['total'] ?? 0)];
    }

    private function getInterventionsStats(array $filters): array
    {
        $where = ['i.id IS NOT NULL'];
        $params = [];
        $this->applyTypeCondition($filters['type'] ?? 'all', $where, $params);
        if (!empty($filters['technician_id'])) {
            $where[] = 'EXISTS (
                SELECT 1 FROM intervention_techniciens it2 
                WHERE it2.intervention_id = i.id 
                AND it2.technicien_id = :technician_id
            )';
            $params[':technician_id'] = (int) $filters['technician_id'];
        }
        if (!empty($filters['status_id'])) {
            $where[] = 'i.status_id = :status_id';
            $params[':status_id'] = (int) $filters['status_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(i.created_at) >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(i.created_at) <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }
        if (!empty($filters['priority_id'])) {
            $where[] = 'i.priority_id = :priority_id';
            $params[':priority_id'] = (int) $filters['priority_id'];
        }
        $whereClause = implode(' AND ', $where);

        $sql = "
    SELECT
        i.id,
        i.reference,
        i.title,
        i.description,
        i.created_at,
        i.closed_at,
        i.is_preventive,
        itype.name AS type_name,
        istatus.name AS status_name,
        istatus.color AS status_color,
        ipriority.name AS priority_name,
        ipriority.color AS priority_color,
        
        -- Client
        c.name AS client_name,
        
        -- Site
        s.name AS site_name,
        s.address AS site_address,
        
        -- Salle
        r.name AS room_name,
        
        -- Date planifiée (premier start_time du technicien)
        (
            SELECT start_time
            FROM intervention_techniciens it2
            WHERE it2.intervention_id = i.id
            LIMIT 1
        ) AS planned_start_time,
        -- Temps passé
        (
            SELECT temps_passe
            FROM intervention_techniciens it2
            WHERE it2.intervention_id = i.id
            LIMIT 1
        ) AS temps_passe,
        
        -- Temps sur site (requires_travel = 1)
        COALESCE(SUM(CASE WHEN COALESCE(t.requires_travel, 0) = 1
                          THEN COALESCE(it.temps_passe, 0) END), 0) AS on_site_minutes,
        
        -- Temps remote (requires_travel = 0 ou NULL)
        COALESCE(SUM(CASE WHEN COALESCE(t.requires_travel, 0) = 0
                          THEN COALESCE(it.temps_passe, 0) END), 0) AS remote_minutes,
        
        -- Liste des techniciens
        (
            SELECT GROUP_CONCAT(CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ')
            FROM intervention_techniciens it2
            INNER JOIN users u ON it2.technicien_id = u.id
            WHERE it2.intervention_id = i.id
        ) AS technicians_list

    FROM interventions i
    LEFT JOIN intervention_types        itype ON i.type_id        = itype.id
    LEFT JOIN intervention_statuses     istatus ON i.status_id    = istatus.id
    LEFT JOIN intervention_priorities   ipriority ON i.priority_id = ipriority.id
    LEFT JOIN clients                   c ON i.client_id         = c.id
    LEFT JOIN sites                     s ON i.site_id           = s.id
    LEFT JOIN rooms                     r ON i.room_id           = r.id
    LEFT JOIN intervention_techniciens  it ON i.id               = it.intervention_id
    LEFT JOIN intervention_types        t ON i.type_id           = t.id

    WHERE $whereClause

    GROUP BY i.id, i.reference, i.title, i.description, i.created_at, i.closed_at, i.is_preventive,
             itype.name, istatus.name, istatus.color,
             ipriority.name, ipriority.color,
             c.name, s.name, s.address, r.name,
             planned_start_time
    ORDER BY i.created_at DESC
";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row): array {
            $onSite = (int) $row['on_site_minutes'];
            $remote = (int) $row['remote_minutes'];
            return [
                'id' => (int) $row['id'],
                'reference' => (string) ($row['reference'] ?? '-'),
                'title' => (string) ($row['title'] ?? 'Sans titre'),
                'description' => (string) ($row['description'] ?? ''),
                'type_name' => (string) ($row['type_name'] ?? '-'),
                'status_name' => (string) ($row['status_name'] ?? '-'),
                'status_color' => (string) ($row['status_color'] ?? '#6c757d'),
                'priority_name' => (string) ($row['priority_name'] ?? 'Normale'),
                'priority_color' => (string) ($row['priority_color'] ?? '#6c757d'),
                'client_name' => (string) ($row['client_name'] ?? '-'),
                'site_name' => (string) ($row['site_name'] ?? '-'),
                'site_address' => (string) ($row['site_address'] ?? ''),
                'room_name' => (string) ($row['room_name'] ?? '-'),
                'created_at' => (string) $row['created_at'],
                'closed_at' => (string) ($row['closed_at'] ?? ''),
                'is_preventive' => (int) ($row['is_preventive'] ?? 0),
                'on_site_minutes' => $onSite,
                'remote_minutes' => $remote,
                'total_minutes' => $onSite + $remote,
                'technicians_list' => (string) ($row['technicians_list'] ?? '-'),
                'planned_start_time' => $row['planned_start_time'] ?? null,
                'temps_passe' => $row['temps_passe'] ?? null,
            ];
        }, $rows);
    }

    private function applyTypeCondition(string $type, array &$where, array &$params): void
    {
        if ($type === 'curatives') {
            $where[] = 'i.is_preventive = :is_preventive';
            $params[':is_preventive'] = 0;
        } elseif ($type === 'preventives') {
            $where[] = 'i.is_preventive = :is_preventive';
            $params[':is_preventive'] = 1;
        }
    }

    private function getTechnicians(): array
    {
        $sql = "SELECT id, first_name, last_name
                FROM   users
                WHERE  user_type_id = 1
                   AND status = 1
                ORDER  BY last_name, first_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getStatuses(): array
    {
        $exclude = ['Nouveau', 'En cours', 'En attente client', 'Résolu', 'Annulé', 'Préventif'];
        $placeholders = implode(',', array_fill(0, count($exclude), '?'));

        $sql = "SELECT id, name, color
            FROM   intervention_statuses
            WHERE  name NOT IN ($placeholders)
            ORDER  BY id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($exclude);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    private function getPriorities(): array
    {
        $sql = "SELECT id, name, color
            FROM   intervention_priorities
            WHERE  name != 'Préventif'
            ORDER  BY id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}