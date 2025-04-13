<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté et admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$error = '';
$success = '';

// Récupérer tous les utilisateurs
$stmt = $pdo->query("SELECT * FROM users ORDER BY username");
$users = $stmt->fetchAll();

// Statistiques globales
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_reservations' => $pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn(),
    'total_points' => $pdo->query("SELECT SUM(points) FROM users")->fetchColumn(),
    'machines_usage' => $pdo->query("
        SELECT m.name, COUNT(r.id) as usage_count 
        FROM machines m 
        LEFT JOIN reservations r ON m.id = r.machine_id 
        GROUP BY m.id, m.name 
        ORDER BY usage_count DESC
    ")->fetchAll()
];

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_machine':
                $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
                $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
                $numero = filter_input(INPUT_POST, 'numero', FILTER_SANITIZE_NUMBER_INT);
                $price = filter_input(INPUT_POST, 'price', FILTER_SANITIZE_NUMBER_INT);

                // Vérifier que le type est valide
                $validTypes = ['lave-linge', 'seche-linge', 'fer-a-repasser'];
                if (!in_array($type, $validTypes)) {
                    $error = "Type de machine invalide.";
                    break;
                }

                try {
                    $stmt = $pdo->prepare("INSERT INTO machines (name, type, numero, price) VALUES (?, ?, ?, ?)");
                    if ($stmt->execute([$name, $type, $numero, $price])) {
                        $success = "Machine ajoutée avec succès.";
                    } else {
                        $error = "Erreur lors de l'ajout de la machine.";
                    }
                } catch (PDOException $e) {
                    $error = "Erreur lors de l'ajout de la machine : " . $e->getMessage();
                }
                break;
                
            case 'delete_machine':
                $machineId = filter_input(INPUT_POST, 'machine_id', FILTER_SANITIZE_NUMBER_INT);
                try {
                    // Vérifier s'il y a des réservations actives
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) FROM reservations 
                        WHERE machine_id = ? AND status IN ('active', 'running')
                    ");
                    $stmt->execute([$machineId]);
                    $activeReservations = $stmt->fetchColumn();

                    if ($activeReservations > 0) {
                        $error = "Impossible de supprimer la machine : il y a des réservations actives.";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM machines WHERE id = ?");
                        if ($stmt->execute([$machineId])) {
                            $success = "Machine supprimée avec succès.";
                        } else {
                            $error = "Erreur lors de la suppression de la machine.";
                        }
                    }
                } catch (PDOException $e) {
                    $error = "Erreur lors de la suppression de la machine : " . $e->getMessage();
                }
                break;
                
            case 'toggle_admin':
                $userId = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
                $stmt = $pdo->prepare("UPDATE users SET is_admin = NOT is_admin WHERE id = ?");
                $stmt->execute([$userId]);
                break;
                
            case 'delete_user':
                $userId = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                break;
                
            case 'reset_points':
                $userId = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);
                $stmt = $pdo->prepare("UPDATE users SET points = 0 WHERE id = ?");
                $stmt->execute([$userId]);
                updateUserGrade($pdo, $userId);
                break;

            case 'update_machine':
                $machineId = filter_input(INPUT_POST, 'machine_id', FILTER_SANITIZE_NUMBER_INT);
                $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
                
                // Vérifier que le statut est valide
                $validStatuses = ['disponible', 'reserve', 'en_marche', 'maintenance'];
                if (in_array($status, $validStatuses)) {
                    $stmt = $pdo->prepare("UPDATE machines SET status = ? WHERE id = ?");
                    if ($stmt->execute([$status, $machineId])) {
                        $success = "Le statut de la machine a été mis à jour.";
                    } else {
                        $error = "Erreur lors de la mise à jour du statut de la machine.";
                    }
                }
                break;

            case 'delete_product':
                $productId = filter_input(INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT);
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$productId]);
                break;

            case 'add_product':
                $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
                $price = filter_input(INPUT_POST, 'price', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                $stock = filter_input(INPUT_POST, 'stock', FILTER_SANITIZE_NUMBER_INT);
                $distributor_id = filter_input(INPUT_POST, 'distributor_id', FILTER_SANITIZE_NUMBER_INT);

                $stmt = $pdo->prepare("INSERT INTO products (name, price, stock, distributor_id) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$name, $price, $stock, $distributor_id])) {
                    $success = "Produit ajouté avec succès.";
                } else {
                    $error = "Erreur lors de l'ajout du produit.";
                }
                break;

            case 'mark_message_read':
                $messageId = filter_input(INPUT_POST, 'message_id', FILTER_SANITIZE_NUMBER_INT);
                $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ?");
                $stmt->execute([$messageId]);
                break;
        }
        
        header('Location: ' . SITE_URL . '/admin.php');
        exit;
    }
}

// Récupérer les données pour l'affichage
$machines = $pdo->query("SELECT * FROM machines ORDER BY type, numero")->fetchAll();
$products = $pdo->query("SELECT p.*, s.name as distributor_name FROM products p JOIN services s ON p.distributor_id = s.id")->fetchAll();
$distributors = $pdo->query("SELECT * FROM services WHERE type = 'distributeur'")->fetchAll();

// Récupération des statistiques
// Statistiques des réservations par jour (7 derniers jours)
$stmt = $pdo->query("
    SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM reservations 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date
");
$reservationsStats = $stmt->fetchAll();

// Revenus par type de machine
$stmt = $pdo->query("
    SELECT m.type, SUM(r.amount) as total_revenue
    FROM reservations r
    JOIN machines m ON r.machine_id = m.id
    GROUP BY m.type
");
$revenueStats = $stmt->fetchAll();

// Taux d'utilisation des machines
$stmt = $pdo->query("
    SELECT m.name, 
           COUNT(r.id) as usage_count,
           (COUNT(r.id) * 100.0 / (
               SELECT COUNT(*) 
               FROM reservations
           )) as usage_percentage
    FROM machines m
    LEFT JOIN reservations r ON m.id = r.machine_id
    GROUP BY m.id, m.name
    ORDER BY usage_count DESC
");
$machineUsageStats = $stmt->fetchAll();

// Récupérer les messages de contact non lus
$messages = $pdo->query("
    SELECT m.*, u.username 
    FROM messages m 
    LEFT JOIN users u ON m.user_id = u.id 
    WHERE m.is_read = 0 
    ORDER BY m.created_at DESC
")->fetchAll();

// Définir le titre de la page
$page_title = "Administration - " . SITE_NAME;

// Inclure l'en-tête
include 'includes/header.php';
?>

<div class="container">
    <h1 class="mb-4">Administration</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Gestion des machines -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h2 class="h5 mb-0">Gestion des machines</h2>
                </div>
                <div class="card-body">
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="action" value="add_machine">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="type" class="form-label">Type</label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="lave-linge">Lave-linge</option>
                                <option value="seche-linge">Sèche-linge</option>
                                <option value="fer-a-repasser">Fer à repasser</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="numero" class="form-label">Numéro</label>
                            <input type="number" class="form-control" id="numero" name="numero" required>
                        </div>
                        <div class="mb-3">
                            <label for="price" class="form-label">Prix (points)</label>
                            <input type="number" class="form-control" id="price" name="price" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Ajouter une machine</button>
                    </form>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Type</th>
                                    <th>Numéro</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($machines as $machine): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($machine['name']); ?></td>
                                        <td><?php echo htmlspecialchars($machine['type']); ?></td>
                                        <td><?php echo htmlspecialchars($machine['numero']); ?></td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="update_machine">
                                                <input type="hidden" name="machine_id" value="<?php echo $machine['id']; ?>">
                                                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                    <option value="disponible" <?php echo $machine['status'] === 'disponible' ? 'selected' : ''; ?>>Disponible</option>
                                                    <option value="reserve" <?php echo $machine['status'] === 'reserve' ? 'selected' : ''; ?>>Réservé</option>
                                                    <option value="en_marche" <?php echo $machine['status'] === 'en_marche' ? 'selected' : ''; ?>>En marche</option>
                                                    <option value="maintenance" <?php echo $machine['status'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette machine ?');">
                                                    <input type="hidden" name="action" value="delete_machine">
                                                    <input type="hidden" name="machine_id" value="<?php echo $machine['id']; ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-trash"></i> Supprimer
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gestion des produits -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h2 class="h5 mb-0">Gestion des produits</h2>
                </div>
                <div class="card-body">
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="action" value="add_product">
                        <div class="mb-3">
                            <label for="product_name" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="product_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="product_price" class="form-label">Prix</label>
                            <input type="number" step="0.01" class="form-control" id="product_price" name="price" required>
                        </div>
                        <div class="mb-3">
                            <label for="stock" class="form-label">Stock</label>
                            <input type="number" class="form-control" id="stock" name="stock" required>
                        </div>
                        <div class="mb-3">
                            <label for="distributor_id" class="form-label">Distributeur</label>
                            <select class="form-select" id="distributor_id" name="distributor_id" required>
                                <?php foreach ($distributors as $distributor): ?>
                                    <option value="<?php echo $distributor['id']; ?>">
                                        <?php echo htmlspecialchars($distributor['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Ajouter un produit</button>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-primary">
                                <tr>
                                    <th>Nom</th>
                                    <th>Prix</th>
                                    <th>Stock</th>
                                    <th>Distributeur</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td class="align-middle"><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td class="align-middle"><?php echo number_format($product['price'], 2); ?> €</td>
                                        <td class="align-middle">
                                            <?php if ($product['stock'] > 0): ?>
                                                <span class="badge bg-success"><?php echo $product['stock']; ?> unités</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Rupture de stock</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle"><?php echo htmlspecialchars($product['distributor_name']); ?></td>
                                        <td class="align-middle">
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?');">
                                                <input type="hidden" name="action" value="delete_product">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-trash"></i> Supprimer
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <style>
                    .table td {
                        vertical-align: middle;
                    }
                    .badge {
                        font-size: 0.9rem;
                        padding: 0.5em 0.7em;
                    }
                    </style>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des utilisateurs -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h2 class="h5 mb-0">Liste des utilisateurs</h2>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nom d'utilisateur</th>
                            <th>Points</th>
                            <th>Admin</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo $user['points']; ?></td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="action" value="toggle_admin">
                                    <button type="submit" class="btn btn-sm <?php echo $user['is_admin'] ? 'btn-success' : 'btn-secondary'; ?>">
                                        <?php echo $user['is_admin'] ? 'Admin' : 'Non admin'; ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="action" value="reset_points">
                                    <button type="submit" class="btn btn-warning btn-sm">Réinitialiser points</button>
                                </form>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="action" value="delete_user">
                                    <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h2 class="h5 mb-0">Statistiques de la laverie</h2>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Graphique des réservations -->
                <div class="col-md-6 mb-4">
                    <canvas id="reservationsChart"></canvas>
                </div>
                <!-- Graphique des revenus -->
                <div class="col-md-6 mb-4">
                    <canvas id="revenueChart"></canvas>
                </div>
                <!-- Graphique d'utilisation des machines -->
                <div class="col-md-6 mb-4">
                    <canvas id="machineUsageChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Caméras de sécurité -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="card-title">Caméra 1 - Zone Machines</h5>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-danger me-2 blink">REC</span>
                        <span class="text-muted" id="camera1-timestamp"></span>
                    </div>
                </div>
                <div class="camera-feed">
                    <img src="<?php echo SITE_URL; ?>/assets/images/laundry-cam1.jpg" class="img-fluid camera-effect" alt="Caméra 1">
                    <div class="camera-overlay"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="card-title">Caméra 2 - Zone Services</h5>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-danger me-2 blink">REC</span>
                        <span class="text-muted" id="camera2-timestamp"></span>
                    </div>
                </div>
                <div class="camera-feed">
                    <img src="<?php echo SITE_URL; ?>/assets/images/laundry-cam2.jpg" class="img-fluid camera-effect" alt="Caméra 2">
                    <div class="camera-overlay"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.camera-feed {
    position: relative;
    border: 2px solid #333;
    background: #000;
    padding: 10px;
    border-radius: 5px;
}

.camera-effect {
    filter: contrast(1.2) brightness(0.8) grayscale(0.3);
}

.camera-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(rgba(0,0,0,0.1) 50%, rgba(0,0,0,0.1) 50%);
    background-size: 100% 4px;
    pointer-events: none;
    animation: scan 8s linear infinite;
}

@keyframes scan {
    0% { transform: translateY(0); }
    100% { transform: translateY(100%); }
}

.blink {
    animation: blink 1.5s infinite;
}

@keyframes blink {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function updateMachineStatus(machineId, isActive) {
    fetch('update_machine_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `machine_id=${machineId}&active=${isActive ? 1 : 0}`
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('Erreur lors de la mise à jour du statut de la machine');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Erreur lors de la mise à jour du statut de la machine');
    });
}

// Mise à jour des timestamps des caméras
function updateTimestamps() {
    const now = new Date();
    const options = { 
        year: 'numeric', 
        month: '2-digit', 
        day: '2-digit', 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit'
    };
    const timestamp = now.toLocaleString('fr-FR', options);
    
    document.getElementById('camera1-timestamp').textContent = timestamp;
    document.getElementById('camera2-timestamp').textContent = timestamp;
}

// Mettre à jour toutes les secondes
setInterval(updateTimestamps, 1000);
updateTimestamps(); // Première mise à jour

// Données pour le graphique des réservations
const reservationsData = {
    labels: <?php echo json_encode(array_column($reservationsStats, 'date')); ?>,
    datasets: [{
        label: 'Nombre de réservations',
        data: <?php echo json_encode(array_column($reservationsStats, 'count')); ?>,
        backgroundColor: 'rgba(54, 162, 235, 0.2)',
        borderColor: 'rgba(54, 162, 235, 1)',
        borderWidth: 1
    }]
};

// Données pour le graphique des revenus
const revenueData = {
    labels: <?php echo json_encode(array_column($revenueStats, 'type')); ?>,
    datasets: [{
        label: 'Revenus par type de machine (€)',
        data: <?php echo json_encode(array_column($revenueStats, 'total_revenue')); ?>,
        backgroundColor: [
            'rgba(255, 99, 132, 0.2)',
            'rgba(54, 162, 235, 0.2)',
            'rgba(255, 206, 86, 0.2)'
        ],
        borderColor: [
            'rgba(255, 99, 132, 1)',
            'rgba(54, 162, 235, 1)',
            'rgba(255, 206, 86, 1)'
        ],
        borderWidth: 1
    }]
};

// Données pour le graphique d'utilisation des machines
const machineUsageData = {
    labels: <?php echo json_encode(array_column($machineUsageStats, 'name')); ?>,
    datasets: [{
        label: 'Taux d\'utilisation (%)',
        data: <?php echo json_encode(array_column($machineUsageStats, 'usage_percentage')); ?>,
        backgroundColor: 'rgba(75, 192, 192, 0.2)',
        borderColor: 'rgba(75, 192, 192, 1)',
        borderWidth: 1
    }]
};

// Création des graphiques
new Chart(document.getElementById('reservationsChart'), {
    type: 'line',
    data: reservationsData,
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Réservations des 7 derniers jours'
            }
        }
    }
});

new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: revenueData,
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Revenus par type de machine'
            }
        }
    }
});

new Chart(document.getElementById('machineUsageChart'), {
    type: 'bar',
    data: machineUsageData,
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Taux d\'utilisation des machines'
            }
        }
    }
});
</script>

<div class="container mt-4">
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Section Messages -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Messages de contact non lus</h5>
        </div>
        <div class="card-body">
            <?php if (empty($messages)): ?>
                <p class="text-muted">Aucun nouveau message</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Sujet</th>
                                <th>Message</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $msg): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($msg['created_at'])); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($msg['name']); ?>
                                    <?php if ($msg['user_id']): ?>
                                        <br><small class="text-muted">Utilisateur: <?php echo htmlspecialchars($msg['username']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $msg['email'] ? htmlspecialchars($msg['email']) : '-'; ?></td>
                                <td><?php echo htmlspecialchars($msg['subject']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($msg['message'])); ?></td>
                                <td>
                                    <form method="post" action="" class="d-inline">
                                        <input type="hidden" name="action" value="mark_message_read">
                                        <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success">Marquer comme lu</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 