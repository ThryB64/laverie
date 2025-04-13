<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour effectuer un achat'
    ]);
    exit;
}

// Vérifier si l'ID du produit est fourni
if (!isset($_POST['produit_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID du produit manquant'
    ]);
    exit;
}

$produit_id = intval($_POST['produit_id']);
$user_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // Récupérer les informations du produit et vérifier le stock
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$produit_id]);
    $produit = $stmt->fetch();

    if (!$produit) {
        throw new Exception('Produit non trouvé');
    }

    if ($produit['stock'] <= 0) {
        throw new Exception('Produit en rupture de stock');
    }

    // Récupérer les points de l'utilisateur
    $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // Calculer le prix final (réduction de 10% si l'utilisateur a plus de 100 points)
    $price_paid = $user['points'] >= 100 ? $produit['price'] * 0.9 : $produit['price'];
    $points_earned = ceil($price_paid); // 1 point par euro dépensé

    // Créer la commande
    $stmt = $pdo->prepare("
        INSERT INTO orders (user_id, product_id, quantity, total_price)
        VALUES (?, ?, 1, ?)
    ");
    $stmt->execute([$user_id, $produit_id, $price_paid]);

    // Mettre à jour le stock
    $stmt = $pdo->prepare("UPDATE products SET stock = stock - 1 WHERE id = ?");
    $stmt->execute([$produit_id]);

    // Ajouter les points gagnés
    $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
    $stmt->execute([$points_earned, $user_id]);

    // Mettre à jour le grade de l'utilisateur
    updateUserGrade($pdo, $user_id);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Achat effectué avec succès',
        'price_paid' => $price_paid,
        'points_earned' => $points_earned
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 