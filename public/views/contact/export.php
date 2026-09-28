<?php
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}
setPageVariables('Export des contacts', 'contact');
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <h4 class="py-4">Export des contacts</h4>

    <div class="card" style="max-width: 500px;">
        <div class="card-body">
            <form action="<?php echo BASE_URL; ?>contacts/exportCsv" method="GET">
                <div class="mb-3">
                    <label for="client_id" class="form-label">Client</label>
                    <select class="form-select" id="client_id" name="client_id">
                        <option value="">Tous les clients</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>"><?php echo h($client['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="vip_only" name="vip_only" value="1">
                    <label class="form-check-label" for="vip_only">
                        <i class="bi bi-star-fill text-warning me-1"></i> Uniquement les contacts VIP
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-download me-1"></i> Exporter en CSV
                </button>
            </form>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>