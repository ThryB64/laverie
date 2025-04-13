<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['machine_id'])) {
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$machine_id = (int)$_POST['machine_id'];
$user_id = $_SESSION['user_id'];

try {
    // Vérifier si la machine existe et est disponible
    $stmt = $pdo->prepare("SELECT * FROM machines WHERE id = ? AND status = 'disponible'");
    $stmt->execute([$machine_id]);
    $machine = $stmt->fetch();

    if (!$machine) {
        echo json_encode(['success' => false, 'message' => 'Cette machine n\'est pas disponible']);
        exit;
    }

    // Vérifier si l'utilisateur a déjà une réservation active
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE user_id = ? AND status IN ('active', 'running', 'pending_payment')");
    $stmt->execute([$user_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Vous avez déjà une réservation active']);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue']);
} 