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
        SELECT id, status 
        FROM reservations 
        WHERE machine_id = ? 
        AND user_id = ? 
        AND status IN ('reserve', 'en_marche')
        AND end_time > NOW()
        ORDER BY start_time DESC
        LIMIT 1
    ");
    $stmt->execute([$machine_id, $user_id]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        throw new Exception('Aucune réservation active trouvée');
    }

    // Mise à jour de la réservation
    $stmt = $pdo->prepare("
        UPDATE reservations 
        SET status = 'annule', 
            end_time = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$reservation['id']]);

    // Mise à jour du statut de la machine
    $stmt = $pdo->prepare("UPDATE machines SET status = 'disponible' WHERE id = ?");
    $stmt->execute([$machine_id]);

    // Validation de la transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Réservation annulée avec succès'
    ]);

} catch (Exception $e) {
    // Annulation de la transaction en cas d'erreur
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 