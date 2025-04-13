<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$root_path = dirname(__DIR__);
require_once $root_path . '/includes/config.php';
require_once $root_path . '/includes/functions.php';

// Vérification de la connexion pour les pages protégées
$protected_pages = ['dashboard.php', 'services.php', 'profil.php', 'admin.php'];
$current_page = basename($_SERVER['PHP_SELF']);

if (in_array($current_page, $protected_pages) && !isLoggedIn()) {
    header('Location: ' . SITE_URL . '/pages/login.php');
    exit();
}

// Récupérer les informations de l'utilisateur si connecté
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Laverie Automatique</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">
    <link rel="shortcut icon" type="image/png" href="/assets/images/logo.png"/>
</head>
<body class="bg-light">
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>/index.php">Laverie</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/index.php">Accueil</a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/dashboard.php">Machines</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/services.php">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/profil.php">Profil</a>
                    </li>
                    <?php if (isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/admin.php">Admin</a>
                    </li>
                    <?php endif; ?>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/contact.php">Contact</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <?php if (isLoggedIn()): ?>
                        <span class="text-light me-3">
                            <?php 
                            if (isset($user['grade'])) {
                                echo htmlspecialchars($user['grade']);
                            }
                            ?>
                        </span>
                        <span class="text-light me-3">
                            <?php 
                            if (isset($user['points'])) {
                                echo htmlspecialchars($user['points']) . ' points';
                            }
                            ?>
                        </span>
                        <a href="<?php echo SITE_URL; ?>/pages/logout.php" class="btn btn-outline-light">Déconnexion</a>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/pages/login.php" class="btn btn-outline-light me-2">Connexion</a>
                        <a href="<?php echo SITE_URL; ?>/pages/register.php" class="btn btn-light">Inscription</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Notification Zone -->
    <div id="notification" class="container mt-3" style="display: none;">
        <div class="alert" role="alert"></div>
    </div>

    <!-- Main Content -->
    <div class="container mt-4"> 