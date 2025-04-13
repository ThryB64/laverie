<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!isset($user['is_admin']) || $user['is_admin'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer et valider les données
$machine_id = filter_input(INPUT_POST, 'machine_id', FILTER_VALIDATE_INT);
$active = filter_input(INPUT_POST, 'active', FILTER_VALIDATE_BOOLEAN);

if ($machine_id === false || $machine_id === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de machine invalide']);
    exit;
}

// Mettre à jour le statut de la machine
$stmt = $pdo->prepare("UPDATE machines SET actif = ? WHERE id = ?");
$success = $stmt->execute([$active ? 1 : 0, $machine_id]);

if ($success) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
} 