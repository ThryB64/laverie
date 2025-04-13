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
    // Vérifier que la réservation existe et appartient à l'utilisateur
    $stmt = $pdo->prepare("SELECT r.*, m.id as machine_id 
                          FROM reservations r 
                          JOIN machines m ON r.machine_id = m.id 
                          WHERE r.id = ? AND r.user_id = ? AND r.status IN ('running', 'active')");
    $stmt->execute([$reservation_id, $user_id]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        echo json_encode(['success' => false, 'message' => 'Réservation invalide']);
        exit;
    }

    // Début de la transaction
    $pdo->beginTransaction();

    // Mettre à jour le statut de la réservation
    $stmt = $pdo->prepare("UPDATE reservations SET status = 'completed', end_time = NOW() WHERE id = ?");
    $stmt->execute([$reservation_id]);

    // Mettre à jour le statut de la machine
    $stmt = $pdo->prepare("UPDATE machines SET status = 'disponible' WHERE id = ?");
    $stmt->execute([$reservation['machine_id']]);

    // Valider la transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Réservation terminée avec succès',
        'machine_id' => $reservation['machine_id']
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue']);
} 