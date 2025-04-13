<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    die("Vous devez être connecté pour accéder à cette page.");
}

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

echo "Informations de l'utilisateur :<br>";
echo "ID: " . $user['id'] . "<br>";
echo "Username: " . htmlspecialchars($user['username']) . "<br>";
echo "Grade: " . htmlspecialchars($user['grade']) . "<br>";
echo "Is Admin: " . ($user['is_admin'] ? 'Oui' : 'Non') . "<br>";

// Si l'utilisateur n'est pas admin, on le met à jour
if (!$user['is_admin']) {
    $stmt = $pdo->prepare("UPDATE users SET is_admin = 1 WHERE id = ?");
    if ($stmt->execute([$user_id])) {
        echo "<br>Le statut administrateur a été activé avec succès.";
    } else {
        echo "<br>Erreur lors de la mise à jour du statut administrateur.";
    }
} 