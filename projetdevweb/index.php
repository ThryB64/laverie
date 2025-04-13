<?php
require_once 'includes/header.php';
?>

<div class="container">
    <h1>Bienvenue à la Laverie Connectée</h1>
    
    <div class="card">
        <h2>Présentation du service</h2>
        <p>Notre laverie automatique connectée vous offre une expérience de lavage moderne et pratique. 
           Gérez vos lessives en ligne, suivez l'état de vos machines et profitez de nombreux avantages selon votre niveau d'abonnement.</p>
    </div>

    <div class="card">
        <h2>Guide d'utilisation rapide</h2>
        <ol>
            <li>Créez votre compte</li>
            <li>Choisissez votre machine</li>
            <li>Effectuez votre réservation</li>
            <li>Suivez l'état de votre lessive</li>
        </ol>
    </div>

    <div class="card">
        <h2>Nos services</h2>
        <ul>
            <li>Machine à laver connectée</li>
            <li>Sèche-linge intelligent</li>
            <li>Fer à repasser connecté</li>
            <li>Distributeur automatique</li>
            <li>Services utilitaires (TV, musique, etc.)</li>
        </ul>
    </div>

    <?php if(!isset($_SESSION['user_id'])): ?>
        <div class="card">
            <h2>Rejoignez-nous</h2>
            <p>Créez votre compte pour accéder à tous nos services.</p>
            <a href="/pages/register.php" class="btn">S'inscrire</a>
        </div>
    <?php endif; ?>
</div>

<?php
require_once 'includes/footer.php';
?> 