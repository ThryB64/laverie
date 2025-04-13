    </div> <!-- Fin du container principal -->

    <footer class="bg-dark text-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>Atelier du Blanchisseur</h5>
                    <p>Votre laverie self-service 24/7</p>
                </div>
                <div class="col-md-4">
                    <h5>Liens utiles</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-light">Accueil</a></li>
                        <li><a href="services.php" class="text-light">Services</a></li>
                        <li><a href="contact.php" class="text-light">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact</h5>
                    <address>
                        <p>123 Rue de la Laverie<br>
                        75000 Paris<br>
                        Tél: 01 23 45 67 89</p>
                    </address>
                </div>
            </div>
            <div class="text-center mt-3">
                <p>&copy; <?php echo date('Y'); ?> Atelier du Blanchisseur. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script src="assets/js/main.js"></script>
    
    <!-- Script spécifique pour le tableau de bord -->
    <?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php'): ?>
    <script src="assets/js/dashboard.js"></script>
    <?php endif; ?>
</body>
</html> 