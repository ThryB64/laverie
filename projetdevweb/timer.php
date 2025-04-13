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
    $stmt = $pdo->prepare("SELECT r.*, m.type, m.numero 
                          FROM reservations r 
                          JOIN machines m ON r.machine_id = m.id 
                          WHERE r.id = ? AND r.user_id = ? AND r.status IN ('active', 'running')");
    $stmt->execute([$reservation_id, $user_id]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        echo json_encode(['success' => false, 'message' => 'Réservation invalide']);
        exit;
    }

    $now = new DateTime();
    $end_time = new DateTime($reservation['end_time']);
    $time_left = $end_time->getTimestamp() - $now->getTimestamp();

    if ($time_left <= 0) {
        // Le temps est écoulé, appeler cancel_timer.php
        require_once 'cancel_timer.php';
        echo json_encode(['success' => false, 'message' => 'Temps écoulé']);
        exit;
    }

    // Calculer le pourcentage de progression
    $total_time = 30; // 30 secondes
    $progress = (($total_time - $time_left) / $total_time) * 100;

    echo json_encode([
        'success' => true,
        'time_left' => $time_left,
        'progress' => round($progress, 2),
        'machine_type' => $reservation['type'],
        'machine_number' => $reservation['numero']
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue']);
} 