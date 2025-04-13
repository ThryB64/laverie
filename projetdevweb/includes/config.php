<?php
// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'cytech0001');
define('DB_NAME', 'laverie');

// Configuration du site
define('SITE_NAME', 'Atelier du Blanchisseur');
define('SITE_URL', 'http://localhost:8080');
define('BASE_PATH', dirname(__DIR__));

// Configuration des sessions
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

// Gestion des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Démarrer la session si elle n'est pas déjà active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?> 
