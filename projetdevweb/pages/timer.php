<?php
session_start();
require_once 'config/database.php';
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

// Récupération de la réservation active
$stmt = $pdo->prepare("
    SELECT r.*, m.price 
    FROM reservations r
    JOIN machines m ON r.machine_id = m.id
    WHERE r.machine_id = ? 
    AND r.user_id = ? 
    AND r.status IN ('reserve', 'en_marche')
    AND r.end_time > NOW()
    ORDER BY r.start_time DESC
    LIMIT 1
");
$stmt->execute([$machine_id, $user_id]);
$reservation = $stmt->fetch();

if (!$reservation) {
    echo json_encode(['success' => false, 'message' => 'Aucune réservation active']);
    exit();
}

// Calcul du temps restant
$now = new DateTime();
$end = new DateTime($reservation['end_time']);
$interval = $now->diff($end);

// Formatage du temps restant
$timeLeft = '';
if ($interval->invert == 0) { // Si le temps n'est pas écoulé
    if ($interval->h > 0) {
        $timeLeft .= $interval->h . 'h ';
    }
    if ($interval->i > 0) {
        $timeLeft .= $interval->i . 'm ';
    }
    $timeLeft .= $interval->s . 's';
} else {
    $timeLeft = '0s';
}

// Calcul du pourcentage de progression
$total = strtotime($reservation['end_time']) - strtotime($reservation['start_time']);
$elapsed = strtotime('now') - strtotime($reservation['start_time']);
$progress = min(100, max(0, ($elapsed / $total) * 100));

echo json_encode([
    'success' => true,
    'data' => [
        'timeLeft' => $timeLeft,
        'progress' => round($progress, 2),
        'status' => $reservation['status'],
        'price' => $reservation['price']
    ]
]); 