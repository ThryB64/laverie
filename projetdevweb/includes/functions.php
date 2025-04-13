<?php
// Fonction pour vérifier si l'utilisateur est connecté
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Fonction pour nettoyer les entrées utilisateur
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Fonction pour rediriger
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Fonction pour générer un token CSRF
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Fonction pour vérifier le token CSRF
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

// Fonction pour afficher un message d'erreur
function displayError($message) {
    return "<div class='alert alert-danger'>" . htmlspecialchars($message) . "</div>";
}

// Fonction pour afficher un message de succès
function displaySuccess($message) {
    return "<div class='alert alert-success'>" . htmlspecialchars($message) . "</div>";
}

// Fonction pour vérifier si l'utilisateur est admin
function isAdmin() {
    global $pdo;
    if (!isLoggedIn()) {
        return false;
    }
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user && $user['is_admin'] == 1;
}

/**
 * Met à jour le grade d'un utilisateur en fonction de ses points
 */
function updateUserGrade($pdo, $user_id) {
    // Récupérer les points actuels de l'utilisateur
    $stmt = $pdo->prepare("SELECT points, is_admin FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return false;
    }

    // Si l'utilisateur est admin, son grade est toujours "Baron du Blanchiment"
    if ($user['is_admin'] == 1) {
        $grade = "Baron du Blanchiment";
    } else {
        // Déterminer le grade en fonction des points
        $points = $user['points'];
        if ($points < 100) {
            $grade = "Chiffon Recrue";
        } elseif ($points < 1000) {
            $grade = "Écuyer de l'Essorage";
        } else {
            $grade = "Chevalier du Détergent";
        }
    }

    // Mettre à jour le grade dans la base de données
    $stmt = $pdo->prepare("UPDATE users SET grade = ? WHERE id = ?");
    return $stmt->execute([$grade, $user_id]);
}

// Fonction pour ajouter des points à un utilisateur
function addPoints($pdo, $user_id, $points) {
    $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
    $stmt->execute([$points, $user_id]);
    updateUserGrade($pdo, $user_id);
}

// Fonction pour vérifier si une machine est disponible
function isMachineAvailable($pdo, $machine_id) {
    $stmt = $pdo->prepare("SELECT status FROM machines WHERE id = ? AND actif = 1");
    $stmt->execute([$machine_id]);
    $machine = $stmt->fetch();
    return $machine && $machine['status'] === 'disponible';
}

// Fonction pour obtenir le nombre de réservations actives d'un utilisateur
function getActiveReservationsCount($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM reservations 
        WHERE user_id = ? 
        AND status IN ('reserve', 'en_marche') 
        AND end_time > NOW()
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result['count'];
}

// Fonction pour formater le temps restant
function formatTimeLeft($end_time) {
    $now = new DateTime();
    $end = new DateTime($end_time);
    $interval = $now->diff($end);

    $timeLeft = '';
    if ($interval->invert == 0) {
        if ($interval->h > 0) {
            $timeLeft .= $interval->h . 'h ';
        }
        if ($interval->i > 0) {
            $timeLeft .= $interval->i . 'm ';
        }
        $timeLeft .= $interval->s . 's';
    } else {
        $timeLeft = '0s';
    }
    return $timeLeft;
}

// Fonction pour calculer la progression
function calculateProgress($start_time, $end_time) {
    $total = strtotime($end_time) - strtotime($start_time);
    $elapsed = strtotime('now') - strtotime($start_time);
    return min(100, max(0, ($elapsed / $total) * 100));
}

// Fonction pour afficher un message de notification
function showNotification($message, $type = 'success') {
    $_SESSION['notification'] = [
        'message' => $message,
        'type' => $type
    ];
}

// Fonction pour récupérer et effacer la notification
function getNotification() {
    if (isset($_SESSION['notification'])) {
        $notification = $_SESSION['notification'];
        unset($_SESSION['notification']);
        return $notification;
    }
    return null;
}

// Fonction pour sécuriser les entrées
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

/**
 * Vérifie si l'utilisateur a accès à une page spécifique selon son grade
 */
function hasPageAccess($pdo, $page) {
    if (!isLoggedIn()) {
        return false;
    }

    // Pages accessibles à tous les utilisateurs connectés
    $public_pages = ['dashboard', 'accueil', 'contact', 'profil'];
    if (in_array($page, $public_pages)) {
        return true;
    }

    // Récupérer le grade de l'utilisateur
    $stmt = $pdo->prepare("SELECT grade, is_admin FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Le Baron du Blanchiment (admin) a accès à tout
    if ($user['is_admin'] == 1 || $user['grade'] === 'Baron du Blanchiment') {
        return true;
    }

    switch ($user['grade']) {
        case 'Chiffon Recrue':
            // N'a accès qu'aux pages publiques
            return false;

        case 'Écuyer de l\'Essorage':
            // A accès aux distributeurs dans la page services
            if ($page === 'services') {
                $_SESSION['restricted_services'] = true; // Pour limiter l'affichage aux distributeurs
                return true;
            }
            return false;

        case 'Chevalier du Détergent':
            // A accès à toute la page services
            if ($page === 'services') {
                $_SESSION['restricted_services'] = false;
                return true;
            }
            return false;

        default:
            return false;
    }
}

/**
 * Vérifie si l'utilisateur a accès restreint aux services (seulement distributeurs)
 */
function hasRestrictedServices() {
    return isset($_SESSION['restricted_services']) && $_SESSION['restricted_services'] === true;
}
?> 