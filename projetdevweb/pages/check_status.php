<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour vérifier le statut des machines.'
    ]);
    exit();
}

// Récupérer toutes les machines
$machines = [];
$query = "SELECT * FROM machines ORDER BY type, numero";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $machines[] = $row;
    }
}

// Récupérer les réservations actives et en cours
$user_id = $_SESSION['user_id'];
$active_reservations = [];
$query = "SELECT r.*, m.type, m.numero FROM reservations r 
          JOIN machines m ON r.machine_id = m.id 
          WHERE r.status = 'active' OR r.status = 'running'";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $active_reservations[] = $row;
    }
}

// Mettre à jour le statut des machines
$updated_machines = [];
foreach ($machines as $machine) {
    $machine_id = $machine['id'];
    $is_available = true;
    $is_running = false;
    $remaining_time = 0;
    $is_user_reservation = false;
    
    // Vérifier si la machine est en marche
    foreach ($active_reservations as $reservation) {
        if ($reservation['machine_id'] == $machine_id && $reservation['status'] == 'running') {
            $is_available = false;
            $is_running = true;
            
            // Calculer le temps restant
            $end_time = strtotime($reservation['end_time']);
            $current_time = time();
            $remaining_time = max(0, $end_time - $current_time);
            
            // Vérifier si c'est une réservation de l'utilisateur
            if ($reservation['user_id'] == $user_id) {
                $is_user_reservation = true;
            }
            
            break;
        }
    }
    
    // Vérifier si la machine est réservée
    if (!$is_running) {
        foreach ($active_reservations as $reservation) {
            if ($reservation['machine_id'] == $machine_id && $reservation['status'] == 'active') {
                $is_available = false;
                
                // Vérifier si c'est une réservation de l'utilisateur
                if ($reservation['user_id'] == $user_id) {
                    $is_user_reservation = true;
                }
                
                break;
            }
        }
    }
    
    // Déterminer le statut de la machine
    $status = 'disponible';
    if (!$is_available) {
        $status = $is_running ? 'en_marche' : 'reserve';
    }
    
    // Ajouter la machine mise à jour
    $updated_machines[] = [
        'id' => $machine_id,
        'type' => $machine['type'],
        'numero' => $machine['numero'],
        'etat' => $status,
        'remaining_time' => $remaining_time,
        'is_user_reservation' => $is_user_reservation
    ];
}

// Vérifier si des réservations sont terminées
$current_time = date('Y-m-d H:i:s');
$query = "UPDATE reservations SET status = 'completed' WHERE status = 'running' AND end_time <= ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $current_time);
$stmt->execute();

echo json_encode([
    'success' => true,
    'machines' => $updated_machines
]);
?> 