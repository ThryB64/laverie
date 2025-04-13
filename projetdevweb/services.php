<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur a accès à cette page
if (!hasPageAccess($pdo, 'services')) {
    $_SESSION['error'] = "Vous n'avez pas accès à cette page avec votre grade actuel.";
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

// Initialiser les paramètres des services s'ils n'existent pas
if (!isset($_SESSION['parametres'])) {
    $_SESSION['parametres'] = [];
}

// Récupérer les services selon les restrictions
if (!hasRestrictedServices()) {
    // Utilisateur avec accès complet (Chevalier du Détergent ou Baron)
    $stmt = $pdo->prepare("SELECT * FROM services WHERE type IN ('lampe','tv','musique','robot','fenetre','climatisation')");
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les distributeurs (accessible à tous ceux qui ont accès à la page)
$stmt = $pdo->prepare("SELECT * FROM services WHERE type = 'distributeur'");
$stmt->execute();
$distributeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$produitsParDistributeur = [];
foreach ($distributeurs as $distributeur) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE distributor_id = ?");
    $stmt->execute([$distributeur['id']]);
    $produitsParDistributeur[$distributeur['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les points de l'utilisateur
$user_points = 0;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $user_points = $user['points'];
}

$page_title = "Services - " . SITE_NAME;
include 'includes/header.php';
?>

<div class="container mt-4">
    <?php if (!hasRestrictedServices()): ?>
    <!-- Services généraux - visible uniquement pour Chevalier du Détergent et Baron -->
    <h2 class="mb-4">Services disponibles</h2>
    <div class="row g-4">
        <?php foreach ($services as $service):
            $etat = $service['status'];
            $params = $_SESSION['parametres'][$service['id']] ?? [];
        ?>
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($service['name']) ?></h5>
                    <?php if ($service['type'] !== 'distributeur'): ?>
                        <p class="mb-1"><strong>Type:</strong> <?= htmlspecialchars($service['type']) ?></p>
                        <p class="<?= $etat ? 'text-success' : 'text-danger' ?>">
                            <?= $etat ? 'Actif' : 'Inactif' ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($service['type'] === 'lampe'): ?>
                        <label>Couleur :</label>
                        <input type="color" class="form-control mb-2" onchange="updateParam(<?= $service['id'] ?>, 'couleur', this.value)" value="<?= $params['couleur'] ?? '#ffffff' ?>">
                        <label>Intensité :</label>
                        <input type="range" class="form-range" min="0" max="100" onchange="updateParam(<?= $service['id'] ?>, 'intensite', this.value)" value="<?= $params['intensite'] ?? 100 ?>">
                    
                    <?php elseif ($service['type'] === 'tv'): ?>
                        <label>Chaîne :</label>
                        <select class="form-select mb-2" onchange="updateParam(<?= $service['id'] ?>, 'chaine', this.value)">
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?= $i ?>" <?= ($params['chaine'] ?? 1) == $i ? 'selected' : '' ?>>Chaîne <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                        <label>Volume :</label>
                        <input type="range" class="form-range" min="0" max="100" onchange="updateParam(<?= $service['id'] ?>, 'volume', this.value)" value="<?= $params['volume'] ?? 50 ?>">
                    
                    <?php elseif ($service['type'] === 'musique'): ?>
                        <label>Playlist :</label>
                        <select class="form-select" onchange="updateParam(<?= $service['id'] ?>, 'playlist', this.value)">
                            <option value="default" <?= ($params['playlist'] ?? '') === 'default' ? 'selected' : '' ?>>Par défaut</option>
                            <option value="relax" <?= ($params['playlist'] ?? '') === 'relax' ? 'selected' : '' ?>>Relax</option>
                            <option value="energetic" <?= ($params['playlist'] ?? '') === 'energetic' ? 'selected' : '' ?>>Énergique</option>
                        </select>

                    <?php elseif ($service['type'] === 'climatisation'): ?>
                        <label>Température :</label>
                        <input type="number" class="form-control mb-2" min="16" max="30" onchange="updateParam(<?= $service['id'] ?>, 'temperature', this.value)" value="<?= $params['temperature'] ?? 21 ?>">
                        <label>Mode :</label>
                        <select class="form-select" onchange="updateParam(<?= $service['id'] ?>, 'mode', this.value)">
                            <option value="auto" <?= ($params['mode'] ?? '') === 'auto' ? 'selected' : '' ?>>Auto</option>
                            <option value="chaud" <?= ($params['mode'] ?? '') === 'chaud' ? 'selected' : '' ?>>Chaud</option>
                            <option value="froid" <?= ($params['mode'] ?? '') === 'froid' ? 'selected' : '' ?>>Froid</option>
                        </select>
                    <?php endif; ?>

                    <button class="btn btn-outline-primary mt-3 w-100 toggle-service" data-id="<?= $service['id'] ?>">
                        <?= $etat ? 'Désactiver' : 'Activer' ?>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Distributeurs - visible pour tous ceux qui ont accès à la page -->
    <h3 class="mt-5 mb-3">Distributeurs</h3>
    <div class="row g-4">
        <?php foreach ($distributeurs as $dist): ?>
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($dist['name']) ?></h5>
                    <p><strong>Type:</strong> Distributeur</p>
                    <ul class="list-unstyled">
                        <?php foreach ($produitsParDistributeur[$dist['id']] as $produit): ?>
                            <li class="d-flex justify-content-between align-items-center my-2">
                                <span>
                                    <?= htmlspecialchars($produit['name']) ?> - 
                                    <?php if ($user_points >= 100): ?>
                                        <span class="text-decoration-line-through text-muted"><?= number_format($produit['price'], 2) ?> €</span>
                                        <span class="text-success"><?= number_format($produit['price'] * 0.9, 2) ?> €</span>
                                        <small class="text-success">(-10%)</small>
                                    <?php else: ?>
                                        <?= number_format($produit['price'], 2) ?> €
                                    <?php endif; ?>
                                </span>
                                <button class="btn btn-sm btn-success acheter-produit" data-id="<?= $produit['id'] ?>">
                                    Acheter
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
// Gestion des services
document.querySelectorAll('.toggle-service').forEach(button => {
    button.addEventListener('click', function() {
        const serviceId = this.dataset.id;
        const button = this;
        button.disabled = true;

        fetch('pages/toggle_service.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `service_id=${serviceId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mettre à jour le statut et le bouton
                const statusText = button.parentElement.querySelector('p[class^="text-"]');
                const newStatus = data.new_status;
                
                statusText.className = newStatus ? 'text-success' : 'text-danger';
                statusText.textContent = newStatus ? 'Actif' : 'Inactif';
                button.textContent = newStatus ? 'Désactiver' : 'Activer';
                
                // Afficher une notification
                showToast(data.message || 'Service mis à jour avec succès', 'success');
            } else {
                showToast(data.message || 'Erreur lors de la mise à jour', 'danger');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showToast('Erreur de connexion', 'danger');
        })
        .finally(() => {
            button.disabled = false;
        });
    });
});

// Fonction pour mettre à jour les paramètres des services
function updateParam(serviceId, key, value) {
    fetch('pages/update_service_param.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `id=${serviceId}&key=${key}&value=${value}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Paramètre mis à jour', 'success');
        } else {
            showToast(data.message || 'Erreur lors de la mise à jour', 'danger');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showToast('Erreur de connexion', 'danger');
    });
}

// Gestion des achats de produits
document.querySelectorAll('.acheter-produit').forEach(button => {
    button.addEventListener('click', function() {
        const productId = this.dataset.id;
        const button = this;
        button.disabled = true;

        fetch('pages/acheter_produit.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `produit_id=${productId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(`Achat réussi ! Prix payé : ${data.price_paid}€ - Points gagnés : ${data.points_earned}`, 'success');
                setTimeout(() => window.location.reload(), 2000);
            } else {
                showToast(data.message || 'Erreur lors de l\'achat', 'danger');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showToast('Erreur de connexion', 'danger');
        })
        .finally(() => {
            button.disabled = false;
        });
    });
});

// Fonction pour afficher les notifications
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed top-0 end-0 m-3`;
    toast.style.zIndex = '1050';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>

<?php include 'includes/footer.php'; ?>
 