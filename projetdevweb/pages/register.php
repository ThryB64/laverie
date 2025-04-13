<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    redirect('/dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Vérifier si le nom d'utilisateur existe déjà
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $error = "Ce nom d'utilisateur est déjà pris.";
    }
    // Vérifier si les mots de passe correspondent
    elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    }
    // Créer le compte
    else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        
        try {
            $stmt->execute([$username, $hashed_password]);
            $success = "Compte créé avec succès ! Vous pouvez maintenant vous connecter.";
        } catch (PDOException $e) {
            $error = "Une erreur est survenue lors de la création du compte.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main>
        <h1>Inscription</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="register-form">
            <div class="form-group">
                <label for="username">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn btn-primary">S'inscrire</button>
        </form>

        <p class="form-footer">
            Déjà un compte ? <a href="login.php">Connectez-vous</a>
        </p>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="../assets/js/script.js"></script>
</body>
</html> 