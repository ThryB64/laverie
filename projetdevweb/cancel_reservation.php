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
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ? AND user_id = ? AND status IN ('pending_payment', 'active')");
    $stmt->execute([$reservation_id, $user_id]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        throw new Exception('Réservation invalide ou ne peut pas être annulée');
    }

    // Mettre à jour la réservation
    $stmt = $pdo->prepare("UPDATE reservations SET 
                          status = 'cancelled',
                          cancelled_at = NOW()
                          WHERE id = ?");
    $stmt->execute([$reservation_id]);

    // Remettre la machine en état disponible
    $stmt = $pdo->prepare("UPDATE machines SET status = 'disponible' WHERE id = ?");
    $stmt->execute([$reservation['machine_id']]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 