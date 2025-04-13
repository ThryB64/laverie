<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour annuler une réservation.'
    ]);
    exit();
}

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée.'
    ]);
    exit();
}

// Récupérer les données du formulaire
$machine_id = isset($_POST['machine_id']) ? intval($_POST['machine_id']) : 0;
$user_id = $_SESSION['user_id'];

// Valider les données
if ($machine_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Données invalides. Veuillez réessayer.'
    ]);
    exit();
}

// Vérifier si la réservation existe et appartient à l'utilisateur
$query = "SELECT * FROM reservations WHERE machine_id = ? AND user_id = ? AND (status = 'active' OR status = 'pending_payment')";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $machine_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Réservation non trouvée ou vous n\'êtes pas autorisé à l\'annuler.'
    ]);
    exit();
}

$reservation = $result->fetch_assoc();

// Mettre à jour le statut de la réservation
$query = "UPDATE reservations SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $reservation['id']);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Réservation annulée avec succès.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'annulation de la réservation. Veuillez réessayer.'
    ]);
}
?> 