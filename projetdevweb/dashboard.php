<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    redirect('/pages/login.php');
}

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Récupérer les machines groupées par type
$stmt = $pdo->query("
    SELECT m.*, r.end_time, u.username, u.grade 
    FROM machines m 
    LEFT JOIN reservations r ON m.id = r.machine_id AND r.status = 'running'
    LEFT JOIN users u ON r.user_id = u.id 
    ORDER BY m.type, m.numero
");
$machines = $stmt->fetchAll();
$machinesByType = [];
foreach ($machines as $machine) {
    $machinesByType[$machine['type']][] = $machine;
}

// Récupérer les réservations actives de l'utilisateur
$stmt = $pdo->prepare("SELECT r.*, m.type, m.numero, m.name, u.username, u.grade 
                       FROM reservations r 
                       JOIN machines m ON r.machine_id = m.id 
                       LEFT JOIN users u ON r.user_id = u.id 
                       WHERE r.user_id = ? AND r.status IN ('active', 'running', 'pending_payment')");
$stmt->execute([$user_id]);
$reservations = $stmt->fetchAll();

// Définir le titre de la page
$page_title = "Tableau de bord - " . SITE_NAME;

// Inclure l'en-tête
include 'includes/header.php';
?>

<style>
.user-info {
    border-top: 1px solid #eee;
    padding-top: 0.5rem;
}

.user-info small {
    display: block;
    line-height: 1.4;
}

.timer {
    font-weight: bold;
    color: #0056b3;
}

/* Styles pour les différents états des machines */
.machine-card {
    transition: all 0.3s ease;
}

.machine-card.status-disponible {
    border-color: #28a745;
}

.machine-card.status-reserve {
    border-color: #ffc107;
}

.machine-card.status-en_marche {
    border-color: #007bff;
    animation: pulse 2s infinite;
}

.machine-card.status-maintenance {
    border-color: #dc3545;
    background-color: #f8d7da;
    opacity: 0.8;
}

.machine-card.status-maintenance .card-header {
    background-color: #dc3545;
    color: white;
}

.machine-card.status-maintenance .card-body {
    position: relative;
}

.machine-card.status-maintenance .card-body::before {
    content: "⚠️ En maintenance";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-15deg);
    font-size: 1.5rem;
    font-weight: bold;
    color: #dc3545;
    text-align: center;
    white-space: nowrap;
    z-index: 1;
}

.machine-card.status-maintenance .card-body > * {
    opacity: 0.5;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(0, 123, 255, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(0, 123, 255, 0);
    }
}
</style>

<div class="container dashboard-container">
    <h1 class="mb-4"><?php echo SITE_NAME; ?></h1>
    
    <!-- Informations utilisateur -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h2 class="h5 mb-0">Votre profil</h2>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Nom d'utilisateur:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                    <p><strong>Grade:</strong> <?php echo htmlspecialchars($user['grade']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Points:</strong> <?php echo htmlspecialchars($user['points']); ?></p>
                    <?php if ($user['points'] >= 100): ?>
                        <p class="text-success">Vous pouvez bénéficier d'une réduction sur votre prochaine utilisation !</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Section des réservations actives -->
    <?php if (!empty($reservations)): ?>
    <div class="card mb-4 reservations-section">
        <div class="card-header bg-primary text-white">
            <h2 class="h5 mb-0">Vos réservations actives</h2>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($reservations as $reservation): ?>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="h6 mb-0">
                                <?php echo htmlspecialchars($reservation['name']); ?> #<?php echo htmlspecialchars($reservation['numero']); ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if ($reservation['status'] === 'running'): ?>
                                <div class="timer" data-end-time="<?php echo strtotime($reservation['end_time']); ?>" data-reservation-id="<?php echo $reservation['id']; ?>">
                                    Temps restant: <span class="countdown"></span>
                                </div>
                                <div class="user-info mt-2">
                                    <small class="text-muted">
                                        Utilisé par: <?php echo htmlspecialchars($reservation['username']); ?>
                                        <br>
                                        Grade: <?php echo htmlspecialchars($reservation['grade']); ?>
                                    </small>
                                </div>
                            <?php elseif ($reservation['status'] === 'pending_payment'): ?>
                                <p>En attente de paiement</p>
                                <button class="btn btn-success btn-pay" data-reservation-id="<?php echo $reservation['id']; ?>">
                                    Payer (<?php echo number_format($reservation['amount'], 2); ?> €)
                                </button>
                                
                                <button class="btn btn-danger btn-cancel mt-2" data-reservation-id="<?php echo $reservation['id']; ?>">
                                    Annuler
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Section des machines par type -->
    <?php foreach ($machinesByType as $type => $typesMachines): ?>
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h2 class="h5 mb-0"><?php echo ucfirst(str_replace('-', ' ', $type)); ?>s</h2>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($typesMachines as $machine): ?>
                <div class="col-md-4 mb-3">
                    <div class="card machine-card status-<?php echo $machine['status']; ?>" data-machine-id="<?php echo $machine['id']; ?>">
                        <div class="card-header">
                            <h3 class="h6 mb-0">
                                <?php echo htmlspecialchars($machine['name']); ?> #<?php echo htmlspecialchars($machine['numero']); ?>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if ($machine['status'] === 'maintenance'): ?>
                                <p class="text-danger mb-0">Machine en maintenance</p>
                            <?php elseif ($machine['status'] === 'en_marche'): ?>
                                <?php if (!empty($machine['end_time'])): ?>
                                <div class="timer" data-end-time="<?php echo strtotime($machine['end_time']); ?>">
                                    En cours - Temps restant: <span class="countdown"></span>
                                </div>
                                <?php if (!empty($machine['username'])): ?>
                                <div class="user-info mt-2">
                                    <small class="text-muted">
                                        Utilisé par: <?php echo htmlspecialchars($machine['username']); ?>
                                        <?php if (!empty($machine['grade'])): ?>
                                        <br>
                                        Grade: <?php echo htmlspecialchars($machine['grade']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                                <?php else: ?>
                                <p class="text-info mb-0">En cours d'utilisation</p>
                                <?php endif; ?>
                            <?php elseif ($machine['status'] === 'reserve'): ?>
                                <p class="text-warning mb-0">Machine réservée</p>
                            <?php else: ?>
                                <p class="text-success mb-0">Machine disponible</p>
                                <form class="reserve-form mt-2" method="POST" action="reserve.php">
                                    <input type="hidden" name="machine_id" value="<?php echo $machine['id']; ?>">
                                    <?php if ($user['points'] >= 100): ?>
                                        <div class="mb-2">
                                            <span class="text-decoration-line-through text-muted"><?php echo number_format($machine['price'], 2); ?> €</span>
                                            <span class="text-success"><?php echo number_format($machine['price'] * 0.9, 2); ?> €</span>
                                            <small class="text-success">(-10%)</small>
                                        </div>
                                    <?php else: ?>
                                        <div class="mb-2"><?php echo number_format($machine['price'], 2); ?> €</div>
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-primary">Réserver</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- Section des instructions -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h2 class="h5 mb-0">Comment ça marche</h2>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-check fa-3x mb-3 text-primary"></i>
                            <h3 class="h5">1. Réservez une machine</h3>
                            <p>Choisissez une machine disponible et réservez-la pour la durée souhaitée.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-credit-card fa-3x mb-3 text-success"></i>
                            <h3 class="h5">2. Effectuez le paiement</h3>
                            <p>Payez le montant indiqué. Des réductions sont disponibles selon votre nombre de points !</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-tshirt fa-3x mb-3 text-info"></i>
                            <h3 class="h5">3. Lancez votre cycle</h3>
                            <p>Chargez votre linge, sélectionnez le programme et lancez le cycle.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Fonction pour mettre à jour les timers
function updateTimers() {
    document.querySelectorAll('.timer').forEach(timer => {
        const endTime = parseInt(timer.dataset.endTime) * 1000;
        const now = new Date().getTime();
        const distance = endTime - now;
        const reservationId = timer.dataset.reservationId;
        
        const countdown = timer.querySelector('.countdown');
        if (distance <= 0) {
            countdown.innerHTML = "Terminé";
            // Appeler l'API pour mettre à jour le statut
            fetch('cancel_timer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'reservation_id=' + reservationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Supprimer la carte de réservation de la section des réservations actives
                    const reservationCard = timer.closest('.col-md-4');
                    if (reservationCard) {
                        reservationCard.remove();
                    }

                    // Mettre à jour le statut de la machine dans la section des machines
                    const machineId = data.machine_id;
                    const machineCard = document.querySelector(`[data-machine-id="${machineId}"]`);
                    if (machineCard) {
                        const cardBody = machineCard.querySelector('.card-body');
                        cardBody.innerHTML = `
                            <form class="reserve-form" method="POST" action="reserve.php">
                                <input type="hidden" name="machine_id" value="${machineId}">
                                <button type="submit" class="btn btn-primary">Réserver</button>
                            </form>
                        `;
                    }

                    // Si c'était la dernière réservation active, masquer la section des réservations actives
                    const activeReservations = document.querySelectorAll('.reservations-section .col-md-4');
                    if (activeReservations.length === 0) {
                        const reservationsSection = document.querySelector('.reservations-section');
                        if (reservationsSection) {
                            reservationsSection.style.display = 'none';
                        }
                    }
                }
            });
        } else {
            const seconds = Math.floor((distance / 1000) % 60);
            countdown.innerHTML = seconds + "s";
        }
    });
}

// Mettre à jour les timers toutes les secondes
setInterval(updateTimers, 1000);
updateTimers();

// Gestionnaire pour le bouton de paiement
document.querySelectorAll('.btn-pay').forEach(button => {
    button.addEventListener('click', function() {
        const reservationId = this.dataset.reservationId;
        fetch('process_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'reservation_id=' + reservationId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Erreur lors du paiement');
            }
        });
    });
});

// Gestionnaire pour le bouton d'annulation
document.querySelectorAll('.btn-cancel').forEach(button => {
    button.addEventListener('click', function() {
        if (confirm('Voulez-vous vraiment annuler cette réservation ?')) {
            const reservationId = this.dataset.reservationId;
            fetch('cancel_reservation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'reservation_id=' + reservationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Erreur lors de l\'annulation');
                }
            });
        }
    });
});
</script>

<?php
// Inclure le pied de page
include 'includes/footer.php';
?> 