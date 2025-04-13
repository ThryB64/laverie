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

// Vérification de l'ID de la machine
if (!isset($_POST['machine_id']) || !is_numeric($_POST['machine_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de machine invalide']);
    exit();
}

$machine_id = (int)$_POST['machine_id'];
$user_id = $_SESSION['user_id'];

try {
    // Début de la transaction
    $pdo->beginTransaction();

    // Vérification du statut de la machine
    $stmt = $pdo->prepare("SELECT status, price FROM machines WHERE id = ? AND actif = 1 FOR UPDATE");
    $stmt->execute([$machine_id]);
    $machine = $stmt->fetch();

    if (!$machine || $machine['status'] !== 'disponible') {
        throw new Exception('Machine non disponible');
    }

    // Vérification du nombre de réservations actives
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM reservations 
        WHERE user_id = ? 
        AND status IN ('reserve', 'en_marche') 
        AND end_time > NOW()
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();

    if ($result['count'] >= 2) {
        throw new Exception('Vous avez déjà 2 réservations actives');
    }

    // Création de la réservation
    $start_time = date('Y-m-d H:i:s');
    $end_time = date('Y-m-d H:i:s', strtotime('+30 seconds'));

    $stmt = $pdo->prepare("
        INSERT INTO reservations (user_id, machine_id, status, start_time, end_time) 
        VALUES (?, ?, 'reserve', ?, ?)
    ");
    $stmt->execute([$user_id, $machine_id, $start_time, $end_time]);
    $reservation_id = $pdo->lastInsertId();

    // Mise à jour du statut de la machine
    $stmt = $pdo->prepare("UPDATE machines SET status = 'reserve' WHERE id = ?");
    $stmt->execute([$machine_id]);

    // Validation de la transaction
    $pdo->commit();

    // Réponse avec les informations nécessaires pour le timer
    echo json_encode([
        'success' => true,
        'message' => 'Réservation créée avec succès',
        'data' => [
            'reservation_id' => $reservation_id,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'price' => $machine['price']
        ]
    ]);

} catch (Exception $e) {
    // Annulation de la transaction en cas d'erreur
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 