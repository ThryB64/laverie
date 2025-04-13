<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérification de la connexion
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté']);
    exit();
}

// Vérification de la méthode de requête
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Vérification des paramètres
if (!isset($_POST['machine_id']) || !is_numeric($_POST['machine_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de machine invalide']);
    exit();
}

$machine_id = (int)$_POST['machine_id'];
$user_id = $_SESSION['user_id'];

try {
    // Début de la transaction
    $pdo->beginTransaction();

    // Vérification de la réservation active
    $stmt = $pdo->prepare("
        SELECT r.id, r.start_time, m.price 
        FROM reservations r
        JOIN machines m ON r.machine_id = m.id
        WHERE r.machine_id = ? 
        AND r.user_id = ? 
        AND r.status = 'reserve'
        AND r.end_time > NOW()
        ORDER BY r.start_time DESC
        LIMIT 1
    ");
    $stmt->execute([$machine_id, $user_id]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        throw new Exception('Aucune réservation active trouvée');
    }

    // Vérification des points de l'utilisateur
    $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user['points'] < $reservation['price']) {
        throw new Exception('Points insuffisants');
    }

    // Déduction des points
    $stmt = $pdo->prepare("UPDATE users SET points = points - ? WHERE id = ?");
    $stmt->execute([$reservation['price'], $user_id]);

    // Mise à jour de la réservation
    $start_time = date('Y-m-d H:i:s');
    $end_time = date('Y-m-d H:i:s', strtotime('+60 seconds'));

    $stmt = $pdo->prepare("
        UPDATE reservations 
        SET status = 'en_marche',
            start_time = ?,
            end_time = ?
        WHERE id = ?
    ");
    $stmt->execute([$start_time, $end_time, $reservation['id']]);

    // Mise à jour du statut de la machine
    $stmt = $pdo->prepare("UPDATE machines SET status = 'en_marche' WHERE id = ?");
    $stmt->execute([$machine_id]);

    // Création de la commande avec points gagnés (1 point par euro)
    $points_earned = ceil($reservation['price']);
    $stmt = $pdo->prepare("
        INSERT INTO orders (user_id, equipment_id, amount, points_earned)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $machine_id, $reservation['price'], $points_earned]);

    // Ajout des points gagnés
    $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
    $stmt->execute([$points_earned, $user_id]);

    // Mise à jour du grade de l'utilisateur
    updateUserGrade($pdo, $user_id);

    // Valider la transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Paiement effectué avec succès',
        'data' => [
            'start_time' => $start_time,
            'end_time' => $end_time
        ]
    ]);

} catch (Exception $e) {
    // Annulation de la transaction en cas d'erreur
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 