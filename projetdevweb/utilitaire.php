<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Initialiser les états des services s'ils n'existent pas
try {
    $stmt = $pdo->query("SELECT id FROM services");
    $services_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($services_ids as $service_id) {
        $check = $pdo->prepare("SELECT id FROM etats_services WHERE service_id = ?");
        $check->execute([$service_id]);
        
        if (!$check->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO etats_services (service_id, etat, parametres, derniere_modification)
                VALUES (?, 0, '{}', NOW())
            ");
            $stmt->execute([$service_id]);
        }
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
}

// Récupération des produits du distributeur
try {
    $stmt = $pdo->query("SELECT * FROM produits_distributeur WHERE stock > 0");
    $produits = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $produits = [];
}

// Récupérer les services et leurs états
try {
    $stmt = $pdo->query("
        SELECT s.id, s.nom, s.description, s.type, es.etat, es.parametres 
        FROM services s 
        LEFT JOIN etats_services es ON s.id = es.service_id 
        ORDER BY s.nom
    ");
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $services = [];
}

// Traitement de la commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['commander'])) {
    $produit_id = $_POST['produit_id'] ?? 0;
    $quantite = $_POST['quantite'] ?? 0;

    if ($produit_id > 0 && $quantite > 0) {
        try {
            // Vérification du stock
            $stmt = $pdo->prepare("SELECT stock, prix FROM produits_distributeur WHERE id = ?");
            $stmt->execute([$produit_id]);
            $produit = $stmt->fetch();

            if ($produit && $produit['stock'] >= $quantite) {
                // Création de la commande
                $stmt = $pdo->prepare("INSERT INTO commandes_produits (utilisateur_id, produit_id, quantite, statut) VALUES (?, ?, ?, 'en_attente')");
                $stmt->execute([$_SESSION['user_id'], $produit_id, $quantite]);
                
                // Mise à jour du stock
                $stmt = $pdo->prepare("UPDATE produits_distributeur SET stock = stock - ? WHERE id = ?");
                $stmt->execute([$quantite, $produit_id]);
                
                $success = "Votre commande a été enregistrée avec succès !";
            } else {
                $error = "Stock insuffisant pour ce produit.";
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = "Une erreur est survenue lors de la commande.";
        }
    }
}

// Traitement des actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        switch ($_POST['action']) {
            case 'toggle_service':
                $service_id = $_POST['service_id'];
                $nouvel_etat = $_POST['etat'] === 'actif' ? 0 : 1;
                
                $stmt = $pdo->prepare("
                    UPDATE etats_services 
                    SET etat = ?, derniere_modification = NOW() 
                    WHERE service_id = ?
                ");
                $stmt->execute([$nouvel_etat, $service_id]);
                
                $response['success'] = true;
                $response['message'] = "Service mis à jour avec succès";
                break;
                
            case 'update_lampe':
                $service_id = $_POST['service_id'];
                $couleur = $_POST['couleur'];
                $intensite = $_POST['intensite'];
                $etat = $_POST['etat'] === 'actif' ? 1 : 0;
                
                $stmt = $pdo->prepare("
                    UPDATE etats_services 
                    SET parametres = ?, etat = ?, derniere_modification = NOW() 
                    WHERE service_id = ?
                ");
                $stmt->execute([json_encode(['couleur' => $couleur, 'intensite' => $intensite]), $etat, $service_id]);
                
                $response['success'] = true;
                $response['message'] = "Lampe mise à jour avec succès";
                break;
                
            case 'update_musique':
                $service_id = $_POST['service_id'];
                $titre = $_POST['titre'];
                $etat = $_POST['etat'] === 'actif' ? 1 : 0;
                
                $stmt = $pdo->prepare("
                    UPDATE etats_services 
                    SET parametres = ?, etat = ?, derniere_modification = NOW() 
                    WHERE service_id = ?
                ");
                $stmt->execute([json_encode(['titre' => $titre]), $etat, $service_id]);
                
                $response['success'] = true;
                $response['message'] = "Musique mise à jour avec succès";
                break;
                
            case 'toggle_aspirateur':
                $service_id = $_POST['service_id'];
                $etat = $_POST['etat'] === 'actif' ? 1 : 0;
                
                $stmt = $pdo->prepare("
                    UPDATE etats_services 
                    SET etat = ?, derniere_modification = NOW() 
                    WHERE service_id = ?
                ");
                $stmt->execute([$etat, $service_id]);
                
                $response['success'] = true;
                $response['message'] = "Aspirateur mis à jour avec succès";
                break;
                
            case 'update_television':
                $service_id = $_POST['service_id'];
                $chaine = $_POST['chaine'];
                $etat = $_POST['etat'] === 'actif' ? 1 : 0;
                
                $stmt = $pdo->prepare("
                    UPDATE etats_services 
                    SET parametres = ?, etat = ?, derniere_modification = NOW() 
                    WHERE service_id = ?
                ");
                $stmt->execute([json_encode(['chaine' => $chaine]), $etat, $service_id]);
                
                $response['success'] = true;
                $response['message'] = "Télévision mise à jour avec succès";
                break;
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $response['message'] = "Une erreur est survenue";
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

include '../includes/header.php';
?>

<div class="container">
    <h1>Utilitaires</h1>
    
    <div class="card">
        <h2>Services disponibles</h2>
        <div class="services-grid">
            <?php foreach ($services as $service): ?>
                <div class="service-card <?php echo $service['etat'] ? 'actif' : 'inactif'; ?>" data-service-id="<?php echo $service['id']; ?>">
                    <h3><?php echo htmlspecialchars($service['nom']); ?></h3>
                    
                    <?php if ($service['nom'] === 'Lampe'): ?>
                        <div class="lampe-controls">
                            <input type="color" class="couleur-lampe" value="<?php echo json_decode($service['parametres'])->couleur ?? '#ffffff'; ?>">
                            <input type="range" class="intensite-lampe" min="0" max="100" value="<?php echo json_decode($service['parametres'])->intensite ?? 100; ?>">
                            <button class="btn toggle-service" data-etat="<?php echo $service['etat'] ? 'actif' : 'inactif'; ?>">
                                <?php echo $service['etat'] ? 'Éteindre' : 'Allumer'; ?>
                            </button>
                        </div>
                    <?php elseif ($service['nom'] === 'Musique'): ?>
                        <div class="musique-controls">
                            <input type="text" class="titre-musique" placeholder="Nom de la musique" value="<?php echo json_decode($service['parametres'])->titre ?? ''; ?>">
                            <button class="btn toggle-service" data-etat="<?php echo $service['etat'] ? 'actif' : 'inactif'; ?>">
                                <?php echo $service['etat'] ? 'Arrêter' : 'Lancer'; ?>
                            </button>
                        </div>
                    <?php elseif ($service['nom'] === 'Robot aspirateur'): ?>
                        <div class="aspirateur-controls">
                            <button class="btn toggle-aspirateur" data-etat="<?php echo $service['etat'] ? 'actif' : 'inactif'; ?>">
                                <?php echo $service['etat'] ? 'Arrêter' : 'Démarrer'; ?>
                            </button>
                            <div class="status-indicator"></div>
                        </div>
                    <?php elseif ($service['nom'] === 'Télévision'): ?>
                        <div class="television-controls">
                            <input type="text" class="chaine-tv" placeholder="Nom de la chaîne" value="<?php echo json_decode($service['parametres'])->chaine ?? ''; ?>">
                            <button class="btn toggle-service" data-etat="<?php echo $service['etat'] ? 'actif' : 'inactif'; ?>">
                                <?php echo $service['etat'] ? 'Éteindre' : 'Allumer'; ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <h2>Distributeur</h2>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="distributeur-grid">
            <?php if (empty($produits)): ?>
                <p>Aucun produit disponible pour le moment.</p>
            <?php else: ?>
                <?php foreach ($produits as $produit): ?>
                    <div class="produit-card">
                        <img src="<?php echo htmlspecialchars($produit['image_url']); ?>" alt="<?php echo htmlspecialchars($produit['nom']); ?>" class="produit-image">
                        <h3><?php echo htmlspecialchars($produit['nom']); ?></h3>
                        <p class="description"><?php echo htmlspecialchars($produit['description']); ?></p>
                        <p class="prix"><?php echo number_format($produit['prix'], 2); ?> €</p>
                        <p class="stock">En stock : <?php echo $produit['stock']; ?></p>
                        
                        <form method="POST" action="" class="commande-form" onsubmit="return handleCommande(event)">
                            <input type="hidden" name="produit_id" value="<?php echo $produit['id']; ?>">
                            <div class="form-group">
                                <label for="quantite_<?php echo $produit['id']; ?>">Quantité :</label>
                                <input type="number" id="quantite_<?php echo $produit['id']; ?>" name="quantite" min="1" max="<?php echo $produit['stock']; ?>" value="1" required>
                            </div>
                            <button type="submit" name="commander" class="btn">Commander</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des services
    document.querySelectorAll('.toggle-service, .toggle-aspirateur').forEach(button => {
        button.addEventListener('click', function() {
            const card = this.closest('.service-card');
            const serviceId = card.dataset.serviceId;
            const currentEtat = this.dataset.etat;
            const newEtat = currentEtat === 'actif' ? 'inactif' : 'actif';
            const isAspirateur = this.classList.contains('toggle-aspirateur');
            
            let data = {
                action: isAspirateur ? 'toggle_aspirateur' : 'toggle_service',
                service_id: serviceId,
                etat: currentEtat
            };
            
            // Ajouter des données spécifiques selon le service
            if (card.querySelector('.couleur-lampe')) {
                data.action = 'update_lampe';
                data.couleur = card.querySelector('.couleur-lampe').value;
                data.intensite = card.querySelector('.intensite-lampe').value;
            } else if (card.querySelector('.titre-musique')) {
                data.action = 'update_musique';
                data.titre = card.querySelector('.titre-musique').value;
            } else if (card.querySelector('.chaine-tv')) {
                data.action = 'update_television';
                data.chaine = card.querySelector('.chaine-tv').value;
            }
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    card.className = `service-card ${newEtat}`;
                    this.dataset.etat = newEtat;
                    this.textContent = newEtat === 'actif' ? 'Arrêter' : 'Démarrer';
                    
                    // Gestion spécifique de l'aspirateur
                    if (isAspirateur && newEtat === 'actif') {
                        const statusIndicator = card.querySelector('.status-indicator');
                        statusIndicator.textContent = 'En cours de nettoyage...';
                        
                        setTimeout(() => {
                            fetch('', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: new URLSearchParams({
                                    action: 'toggle_aspirateur',
                                    service_id: serviceId,
                                    etat: 'inactif'
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    card.className = 'service-card inactif';
                                    this.dataset.etat = 'inactif';
                                    this.textContent = 'Démarrer';
                                    statusIndicator.textContent = 'Nettoyage terminé';
                                    setTimeout(() => {
                                        statusIndicator.textContent = '';
                                    }, 3000);
                                }
                            });
                        }, 120000); // 2 minutes
                    }
                }
            });
        });
    });
    
    // Mise à jour en temps réel des contrôles de la lampe
    document.querySelectorAll('.couleur-lampe, .intensite-lampe').forEach(input => {
        input.addEventListener('change', function() {
            const card = this.closest('.service-card');
            const serviceId = card.dataset.serviceId;
            const currentEtat = card.querySelector('.toggle-service').dataset.etat;
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'update_lampe',
                    service_id: serviceId,
                    couleur: card.querySelector('.couleur-lampe').value,
                    intensite: card.querySelector('.intensite-lampe').value,
                    etat: currentEtat
                })
            });
        });
    });
});

// Gestion des commandes
function handleCommande(event) {
    event.preventDefault();
    const form = event.target;
    const produitId = form.querySelector('[name="produit_id"]').value;
    const quantite = form.querySelector('[name="quantite"]').value;
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            commander: '1',
            produit_id: produitId,
            quantite: quantite
        })
    })
    .then(response => response.text())
    .then(html => {
        // Créer un DOM temporaire pour extraire les messages
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const success = doc.querySelector('.alert-success');
        const error = doc.querySelector('.alert-danger');
        
        // Supprimer les anciens messages
        document.querySelectorAll('.alert').forEach(alert => alert.remove());
        
        // Afficher le nouveau message
        if (success) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success';
            alertDiv.textContent = success.textContent;
            form.closest('.card').insertBefore(alertDiv, form.closest('.distributeur-grid'));
            
            // Mettre à jour le stock affiché
            const produitCard = form.closest('.produit-card');
            const stockElement = produitCard.querySelector('.stock');
            const currentStock = parseInt(stockElement.textContent.match(/\d+/)[0]);
            stockElement.textContent = `En stock : ${currentStock - parseInt(quantite)}`;
            
            // Mettre à jour la quantité max du formulaire
            const quantiteInput = form.querySelector('[name="quantite"]');
            quantiteInput.max = currentStock - parseInt(quantite);
            if (parseInt(quantiteInput.value) > quantiteInput.max) {
                quantiteInput.value = quantiteInput.max;
            }
        }
        
        if (error) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger';
            alertDiv.textContent = error.textContent;
            form.closest('.card').insertBefore(alertDiv, form.closest('.distributeur-grid'));
        }
    });
    
    return false;
}
</script>

<?php include '../includes/footer.php'; ?> 