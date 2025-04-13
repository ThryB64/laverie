<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['reservation_id'])) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$reservation_id = (int)$_POST['reservation_id'];
$user_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // Vérifier que la réservation existe et appartient à l'utilisateur
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ? AND user_id = ? AND status = 'pending_payment'");
    $stmt->execute([$reservation_id, $user_id]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        throw new Exception('Réservation invalide');
    }

    // Simuler le paiement (toujours réussi pour l'instant)
    $start_time = date('Y-m-d H:i:s');
    $end_time = date('Y-m-d H:i:s', strtotime('+30 seconds')); // Timer de 30 secondes

    // Mettre à jour la réservation
    $stmt = $pdo->prepare("UPDATE reservations SET 
                          status = 'running',
                          start_time = ?,
                          end_time = ?,
                          paid_at = NOW()
                          WHERE id = ?");
    $stmt->execute([$start_time, $end_time, $reservation_id]);

    // Mettre à jour le statut de la machine
    $stmt = $pdo->prepare("UPDATE machines SET status = 'en_marche' WHERE id = ?");
    $stmt->execute([$reservation['machine_id']]);

    // Ajouter des points à l'utilisateur (1 point par euro dépensé)
    $points_earned = ceil($reservation['amount']);
    $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
    $stmt->execute([$points_earned, $user_id]);
    updateUserGrade($pdo, $user_id);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 