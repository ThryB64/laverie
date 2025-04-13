<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Vérifier si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit;
}

// Récupérer et nettoyer les données
$name = sanitize($_POST['name']);
$subject = sanitize($_POST['subject']);
$message = sanitize($_POST['message']);
$email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : null;
$user_id = isLoggedIn() ? $_SESSION['user_id'] : null;

try {
    // Insérer le message dans la base de données
    $stmt = $pdo->prepare("
        INSERT INTO messages (name, subject, message, email, user_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $subject, $message, $email, $user_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Votre message a été envoyé avec succès'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de l\'envoi du message'
    ]);
} 