<?php
// Vérifier que les données sont passées
if (!isset($clientLocations) || empty($clientLocations)) {
    echo '<p class="text-muted">Aucune localisation disponible pour ce client.</p>';
    return;
}

// Récupérer les localisations existantes si on édite un utilisateur
$existingLocations = [];
if (isset($existingUserLocations) && !empty($existingUserLocations)) {
    foreach ($existingUserLocations as $location) {
        if ($location['client_id'] && !$location['site_id'] && !$location['room_id']) {
            $existingLocations['client_full'] = $location['client_id'];
        } elseif ($location['site_id'] && !$location['room_id']) {
            $existingLocations['sites'][] = $location['site_id'];
        } elseif ($location['room_id']) {
            $existingLocations['rooms'][] = $location['room_id'];
        }
    }
}
?>

<label class="form-label">Localisations Client</label>
<div class="locations-container">
    <!-- Option d'accès complet au client -->
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="client_full_access" name="locations[client_full]" value="1"
               <?php echo (isset($existingLocations['client_full'])) ? 'checked' : ''; ?>>
        <label class="form-check-label" for="client_full_access">
            <strong>Accès complet au client</strong> (toutes les localisations)
        </label>
    </div>
    
    <hr>
    
    <!-- Sites et salles avec le design des contrats -->
    <?php foreach ($clientLocations as $siteIndex => $site): ?>
        <div class="mb-3">
            <!-- En-tête du site -->
            <div class="d-flex align-items-center mb-2">
                <div class="form-check me-3">
                    <input class="form-check-input site-checkbox" type="checkbox" 
                           id="site_<?php echo $site['id']; ?>" 
                           name="locations[sites][]" 
                           value="<?php echo $site['id']; ?>" 
                           data-site-id="<?php echo $site['id']; ?>"
                           <?php echo (isset($existingLocations['sites']) && in_array($site['id'], $existingLocations['sites'])) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="site_<?php echo $site['id']; ?>"></label>
                </div>
                <i class="bi bi-building text-primary me-2 me-1"></i>
                <strong class="text-primary"><?php echo h($site['name']); ?></strong>
            </div>
            
            <!-- Salles du site -->
            <?php if (!empty($site['rooms'])): ?>
                <div class="ms-4">
                    <?php foreach ($site['rooms'] as $room): ?>
                        <div class="form-check mb-1">
                            <input class="form-check-input room-checkbox" type="checkbox" 
                                   id="room_<?php echo $room['id']; ?>" 
                                   name="locations[rooms][]" 
                                   value="<?php echo $room['id']; ?>" 
                                   data-site-id="<?php echo $site['id']; ?>"
                                   <?php echo (isset($existingLocations['rooms']) && in_array($room['id'], $existingLocations['rooms'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="room_<?php echo $room['id']; ?>">
                                <i class="bi bi-door-open text-muted me-1 me-1"></i>
                                <?php echo h($room['name']); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="ms-4">
                    <p class="text-muted mb-0">Aucune salle disponible pour ce site.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Accès complet au client
    const clientFullAccess = document.getElementById('client_full_access');
    if (clientFullAccess) {
        clientFullAccess.addEventListener('change', function() {
            const allCheckboxes = document.querySelectorAll('.site-checkbox, .room-checkbox');
            allCheckboxes.forEach(checkbox => {
                checkbox.disabled = this.checked;
                if (this.checked) {
                    checkbox.checked = false;
                }
            });
        });
    }

    // Sites : cocher un site coche toutes ses salles, décocher un site décoche toutes ses salles
    const siteCheckboxes = document.querySelectorAll('.site-checkbox');
    siteCheckboxes.forEach(siteCheckbox => {
        siteCheckbox.addEventListener('change', function() {
            const siteId = this.dataset.siteId;
            const roomCheckboxes = document.querySelectorAll(`.room-checkbox[data-site-id="${siteId}"]`);
            if (this.checked) {
                roomCheckboxes.forEach(roomCheckbox => {
                    roomCheckbox.checked = true;
                });
            } else {
                roomCheckboxes.forEach(roomCheckbox => {
                    roomCheckbox.checked = false;
                });
            }
        });
    });

    // (Optionnel) Si toutes les salles d'un site sont cochées manuellement, cocher le site
    const roomCheckboxes = document.querySelectorAll('.room-checkbox');
    roomCheckboxes.forEach(roomCheckbox => {
        roomCheckbox.addEventListener('change', function() {
            const siteId = this.dataset.siteId;
            const siteCheckbox = document.getElementById(`site_${siteId}`);
            const rooms = document.querySelectorAll(`.room-checkbox[data-site-id="${siteId}"]`);
            const allChecked = Array.from(rooms).every(cb => cb.checked);
            if (siteCheckbox) {
                siteCheckbox.checked = allChecked;
            }
        });
    });

    // Appliquer l'état initial pour accès complet
    if (clientFullAccess && clientFullAccess.checked) {
        const allCheckboxes = document.querySelectorAll('.site-checkbox, .room-checkbox');
        allCheckboxes.forEach(checkbox => {
            checkbox.disabled = true;
        });
    }
});
</script> 