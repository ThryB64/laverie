<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('/pages/login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['machine_id'])) {
    redirect('/dashboard.php');
}

$machine_id = (int)$_POST['machine_id'];
$user_id = $_SESSION['user_id'];

// Vérifier si la machine existe et est disponible
$stmt = $pdo->prepare("SELECT * FROM machines WHERE id = ? AND status = 'disponible'");
$stmt->execute([$machine_id]);
$machine = $stmt->fetch();

if (!$machine) {
    $_SESSION['error'] = "Cette machine n'est pas disponible.";
    redirect('/dashboard.php');
}

// Récupérer les points de l'utilisateur
$stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

try {
    $pdo->beginTransaction();

    // Créer la réservation
    $start_time = date('Y-m-d H:i:s');
    $end_time = date('Y-m-d H:i:s', strtotime('+30 seconds')); // Timer de 30 secondes
    
    // Calculer le prix avec réduction si l'utilisateur a plus de 100 points
    $price = $machine['price'];
    if ($user['points'] >= 100) {
        $price = $price * 0.9; // Réduction de 10%
    }
    
    $stmt = $pdo->prepare("INSERT INTO reservations (user_id, machine_id, status, start_time, end_time, duration, program, temperature, amount) 
                          VALUES (?, ?, 'pending_payment', ?, ?, 30, 'Standard', 'Normal', ?)");
    $stmt->execute([$user_id, $machine_id, $start_time, $end_time, $price]);

    // Mettre à jour le statut de la machine
    $stmt = $pdo->prepare("UPDATE machines SET status = 'reserve' WHERE id = ?");
    $stmt->execute([$machine_id]);

    $pdo->commit();
    redirect('/dashboard.php');
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Une erreur est survenue lors de la réservation.";
    redirect('/dashboard.php');
} 