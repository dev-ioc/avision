<?php
header('Content-Type: application/json');

if (!isset($_GET['site_id']) || empty($_GET['site_id'])) {
    echo json_encode([]);
    exit;
}

$siteId = (int)$_GET['site_id'];

// Cette vue sera gérée par le contrôleur DocumentationClientController
// qui filtre les salles selon les localisations autorisées
echo json_encode([]);
?> 