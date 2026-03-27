<?php
// Vérifier que les permissions sont passées
if (!isset($availablePermissions) || empty($availablePermissions)) {
    echo '<p class="text-muted">Aucune permission disponible pour les clients.</p>';
    return;
}

// Grouper par catégorie
$groupedPermissions = [];
foreach ($availablePermissions as $permission) {
    $category = $permission['category'] ?? 'general';
    if (!isset($groupedPermissions[$category])) {
        $groupedPermissions[$category] = [];
    }
    $groupedPermissions[$category][] = $permission;
}
?>

<label class="form-label">Permissions Client</label>
<div class="permissions-container">
    <?php foreach ($groupedPermissions as $category => $permissions): ?>
        <div class="permission-category mb-3">
            <h6 class="text-muted mb-2"><?php echo ucfirst($category); ?></h6>
            <div class="ms-3">
                <?php foreach ($permissions as $permission): ?>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="permissions[]" 
                               value="<?php echo h($permission['id']); ?>" 
                               id="permission_<?php echo h($permission['id']); ?>"
                               <?php echo (isset($existingPermissionIds) && in_array($permission['id'], $existingPermissionIds)) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="permission_<?php echo h($permission['id']); ?>">
                            <?php echo h($permission['description']); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div> 