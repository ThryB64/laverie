<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour réserver une machine.'
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
$machine_type = isset($_POST['machine_type']) ? $_POST['machine_type'] : '';
$duration = isset($_POST['duration']) ? intval($_POST['duration']) : 0;
$program = isset($_POST['program']) ? $_POST['program'] : '';
$temperature = isset($_POST['temperature']) ? $_POST['temperature'] : '';
$user_id = $_SESSION['user_id'];

// Valider les données
if ($machine_id <= 0 || empty($machine_type) || $duration <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Données invalides. Veuillez réessayer.'
    ]);
    exit();
}

// Vérifier si la machine existe et est disponible
$query = "SELECT * FROM machines WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $machine_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Machine non trouvée.'
    ]);
    exit();
}

$machine = $result->fetch_assoc();

// Vérifier si la machine est déjà réservée ou en marche
$query = "SELECT * FROM reservations WHERE machine_id = ? AND (status = 'active' OR status = 'running')";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $machine_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Cette machine est déjà réservée ou en marche.'
    ]);
    exit();
}

// Calculer le prix
$basePricePerMinute = [
    'lave-linge' => 0.10,
    'seche-linge' => 0.08
];
$basePrice = isset($basePricePerMinute[$machine_type]) ? $basePricePerMinute[$machine_type] : 0.10;
$amount = $basePrice * $duration;

// Calculer les dates de début et de fin
$start_time = date('Y-m-d H:i:s');
$end_time = date('Y-m-d H:i:s', strtotime("+{$duration} minutes"));

// Insérer la réservation dans la base de données
$query = "INSERT INTO reservations (user_id, machine_id, start_time, end_time, duration, program, temperature, amount, status) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')";
$stmt = $conn->prepare($query);
$stmt->bind_param("iisssissd", $user_id, $machine_id, $start_time, $end_time, $duration, $program, $temperature, $amount);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Réservation effectuée avec succès. Veuillez procéder au paiement.',
        'reservation_id' => $stmt->insert_id
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la réservation. Veuillez réessayer.'
    ]);
}
?> 