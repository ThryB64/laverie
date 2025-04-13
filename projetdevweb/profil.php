<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit;
}

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$error = '';
$success = '';

// Traitement du formulaire de modification du mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            $success = "Votre mot de passe a été mis à jour avec succès.";
        } else {
            $error = "Les nouveaux mots de passe ne correspondent pas.";
        }
    } else {
        $error = "Le mot de passe actuel est incorrect.";
    }
}

// Statistiques personnelles
// Historique des dépenses (7 derniers jours)
$stmt = $pdo->prepare("
    SELECT DATE(created_at) as date, SUM(amount) as total_spent
    FROM reservations 
    WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date
");
$stmt->execute([$user_id]);
$expensesStats = $stmt->fetchAll();

// Machines les plus utilisées
$stmt = $pdo->prepare("
    SELECT m.name, COUNT(*) as usage_count
    FROM reservations r
    JOIN machines m ON r.machine_id = m.id
    WHERE r.user_id = ?
    GROUP BY m.id, m.name
    ORDER BY usage_count DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$machineUsageStats = $stmt->fetchAll();

// Évolution des points (7 derniers jours)
$stmt = $pdo->prepare("
    SELECT DATE(date) as date, points
    FROM points_history
    WHERE user_id = ?
    AND date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY date
");
$stmt->execute([$user_id]);
$pointsStats = $stmt->fetchAll();

// Définir le titre de la page
$page_title = "Profil - " . SITE_NAME;

// Inclure l'en-tête
include 'includes/header.php';
?>

<div class="container">
    <h1 class="mb-4">Votre Profil</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h2 class="h5 mb-0">Informations du compte</h2>
                </div>
                <div class="card-body">
                    <p><strong>Nom d'utilisateur:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                    <p><strong>Grade:</strong> <?php echo htmlspecialchars($user['grade']); ?></p>
                    <p><strong>Points:</strong> <?php echo htmlspecialchars($user['points']); ?></p>
                    <p><strong>Date d'inscription:</strong> <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></p>
                    <?php if ($user['points'] >= 100): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-star"></i> Vous bénéficiez de 10% de réduction sur vos achats !
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h2 class="h5 mb-0">Changer le mot de passe</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mot de passe actuel</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Nouveau mot de passe</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-primary">Mettre à jour le mot de passe</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header bg-primary text-white">
            <h2 class="h5 mb-0">Historique des réservations</h2>
        </div>
        <div class="card-body">
            <?php
            $stmt = $pdo->prepare("
                SELECT r.*, m.name, m.type, m.numero 
                FROM reservations r 
                JOIN machines m ON r.machine_id = m.id 
                WHERE r.user_id = ? 
                ORDER BY r.created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$user_id]);
            $reservations = $stmt->fetchAll();

            if (empty($reservations)): ?>
                <p>Aucune réservation trouvée.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Machine</th>
                                <th>Type</th>
                                <th>Statut</th>
                                <th>Date</th>
                                <th>Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reservation['name']); ?> #<?php echo htmlspecialchars($reservation['numero']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['type']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['status']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($reservation['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['amount']); ?> points</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="h5 mb-0">Mes Statistiques</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Graphique des dépenses -->
                        <div class="col-md-12 mb-4">
                            <canvas id="expensesChart"></canvas>
                        </div>
                        <!-- Graphique des machines utilisées -->
                        <div class="col-md-12 mb-4">
                            <canvas id="machineUsageChart"></canvas>
                        </div>
                        <!-- Graphique de l'évolution des points -->
                        <div class="col-md-12 mb-4">
                            <canvas id="pointsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Données pour le graphique des dépenses
const expensesData = {
    labels: <?php echo json_encode(array_column($expensesStats, 'date')); ?>,
    datasets: [{
        label: 'Dépenses totales (€)',
        data: <?php echo json_encode(array_column($expensesStats, 'total_spent')); ?>,
        backgroundColor: 'rgba(54, 162, 235, 0.2)',
        borderColor: 'rgba(54, 162, 235, 1)',
        borderWidth: 1
    }]
};

// Données pour le graphique des machines utilisées
const machineUsageData = {
    labels: <?php echo json_encode(array_column($machineUsageStats, 'name')); ?>,
    datasets: [{
        label: 'Nombre d\'utilisations',
        data: <?php echo json_encode(array_column($machineUsageStats, 'usage_count')); ?>,
        backgroundColor: [
            'rgba(255, 99, 132, 0.2)',
            'rgba(54, 162, 235, 0.2)',
            'rgba(255, 206, 86, 0.2)',
            'rgba(75, 192, 192, 0.2)',
            'rgba(153, 102, 255, 0.2)'
        ],
        borderColor: [
            'rgba(255, 99, 132, 1)',
            'rgba(54, 162, 235, 1)',
            'rgba(255, 206, 86, 1)',
            'rgba(75, 192, 192, 1)',
            'rgba(153, 102, 255, 1)'
        ],
        borderWidth: 1
    }]
};

// Données pour le graphique des points
const pointsData = {
    labels: <?php echo json_encode(array_column($pointsStats, 'date')); ?>,
    datasets: [{
        label: 'Points',
        data: <?php echo json_encode(array_column($pointsStats, 'points')); ?>,
        backgroundColor: 'rgba(75, 192, 192, 0.2)',
        borderColor: 'rgba(75, 192, 192, 1)',
        borderWidth: 1
    }]
};

// Création des graphiques
new Chart(document.getElementById('expensesChart'), {
    type: 'line',
    data: expensesData,
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Mes dépenses des 7 derniers jours'
            }
        }
    }
});

new Chart(document.getElementById('machineUsageChart'), {
    type: 'pie',
    data: machineUsageData,
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Mes machines les plus utilisées'
            }
        }
    }
});

new Chart(document.getElementById('pointsChart'), {
    type: 'line',
    data: pointsData,
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Évolution de mes points'
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?> 