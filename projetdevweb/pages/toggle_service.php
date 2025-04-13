<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté et a les droits
if (!isLoggedIn() || hasRestrictedServices()) {
    echo json_encode([
        'success' => false,
        'message' => 'Accès non autorisé'
    ]);
    exit;
}

// Vérifier si l'ID du service est fourni
if (!isset($_POST['service_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID du service manquant'
    ]);
    exit;
}

$service_id = intval($_POST['service_id']);

try {
    // Récupérer l'état actuel du service
    $stmt = $pdo->prepare("SELECT status FROM services WHERE id = ?");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        throw new Exception('Service non trouvé');
    }

    // Inverser l'état du service (convertir explicitement en entier)
    $new_status = $service['status'] ? 0 : 1;
    
    // Mettre à jour le statut avec une valeur entière explicite
    $stmt = $pdo->prepare("UPDATE services SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $service_id]);

    echo json_encode([
        'success' => true,
        'new_status' => (bool)$new_status,
        'message' => $new_status ? 'Service activé' : 'Service désactivé'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 