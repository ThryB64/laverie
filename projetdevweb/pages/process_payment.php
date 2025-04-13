<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour effectuer un paiement.'
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
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
$user_id = $_SESSION['user_id'];

// Valider les données
if ($machine_id <= 0 || $amount <= 0 || empty($payment_method)) {
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
        'message' => 'Réservation non trouvée ou vous n\'êtes pas autorisé à effectuer ce paiement.'
    ]);
    exit();
}

$reservation = $result->fetch_assoc();

// Vérifier si le montant correspond
if (abs($reservation['amount'] - $amount) > 0.01) {
    echo json_encode([
        'success' => false,
        'message' => 'Le montant du paiement ne correspond pas à la réservation.'
    ]);
    exit();
}

// Simuler le traitement du paiement
// Dans un environnement de production, vous utiliseriez une API de paiement réelle
$payment_successful = true;

if ($payment_successful) {
    // Mettre à jour le statut de la réservation
    $query = "UPDATE reservations SET status = 'running', paid_at = NOW(), payment_method = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $payment_method, $reservation['id']);
    
    if ($stmt->execute()) {
        // Ajouter des points à l'utilisateur (1 point par euro dépensé)
        $points_to_add = floor($amount);
        $query = "UPDATE users SET points = points + ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $points_to_add, $user_id);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Paiement effectué avec succès. Votre machine est maintenant en marche.',
            'points_earned' => $points_to_add
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour de la réservation. Veuillez réessayer.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Le paiement a échoué. Veuillez réessayer.'
    ]);
}
?> 