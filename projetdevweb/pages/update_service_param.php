<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté et a les droits
if (!isLoggedIn() || hasRestrictedServices()) {
    echo json_encode([
        'success' => false,
        'message' => 'Accès non autorisé'
    ]);
    exit;
}

// Vérifier les paramètres requis
if (!isset($_POST['id']) || !isset($_POST['key']) || !isset($_POST['value'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Paramètres manquants'
    ]);
    exit;
}

$service_id = intval($_POST['id']);
$key = $_POST['key'];
$value = $_POST['value'];

try {
    // Vérifier si le service existe
    $stmt = $pdo->prepare("SELECT id, type FROM services WHERE id = ?");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch();

    if (!$service) {
        throw new Exception('Service non trouvé');
    }

    // Valider les paramètres selon le type de service
    switch ($service['type']) {
        case 'lampe':
            if ($key === 'couleur') {
                if (!preg_match('/^#[0-9a-f]{6}$/i', $value)) {
                    throw new Exception('Couleur invalide');
                }
            } elseif ($key === 'intensite') {
                $value = max(0, min(100, intval($value)));
            }
            break;

        case 'tv':
            if ($key === 'chaine') {
                $value = max(1, min(10, intval($value)));
            } elseif ($key === 'volume') {
                $value = max(0, min(100, intval($value)));
            }
            break;

        case 'musique':
            if ($key === 'playlist' && !in_array($value, ['default', 'relax', 'energetic'])) {
                throw new Exception('Playlist invalide');
            }
            break;

        case 'climatisation':
            if ($key === 'temperature') {
                $value = max(16, min(30, intval($value)));
            } elseif ($key === 'mode' && !in_array($value, ['auto', 'chaud', 'froid'])) {
                throw new Exception('Mode invalide');
            }
            break;
    }

    // Initialiser le tableau des paramètres si nécessaire
    if (!isset($_SESSION['parametres'])) {
        $_SESSION['parametres'] = [];
    }
    if (!isset($_SESSION['parametres'][$service_id])) {
        $_SESSION['parametres'][$service_id] = [];
    }

    // Mettre à jour le paramètre
    $_SESSION['parametres'][$service_id][$key] = $value;

    echo json_encode([
        'success' => true,
        'message' => 'Paramètre mis à jour',
        'service_id' => $service_id,
        'key' => $key,
        'value' => $value
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 