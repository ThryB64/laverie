<?php
include 'includes/header.php';
?>

<div class="container mt-4">
    <h1 class="mb-4">Contactez-nous</h1>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Nos coordonnées</h5>
                    <div class="mb-3">
                        <h6>Adresse</h6>
                        <p>123 Rue de la Laverie<br>75000 Paris</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Téléphone</h6>
                        <p>01 23 45 67 89</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Horaires</h6>
                        <p>Ouvert 24h/24, 7j/7</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">Envoyez-nous un message</h5>
                    <form id="contactForm" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="name" class="form-label">Nom</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="invalid-feedback">Veuillez entrer votre nom</div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                            <div class="invalid-feedback">Veuillez entrer un email valide</div>
                        </div>

                        <div class="mb-3">
                            <label for="subject" class="form-label">Sujet</label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                            <div class="invalid-feedback">Veuillez entrer un sujet</div>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                            <div class="invalid-feedback">Veuillez entrer votre message</div>
                        </div>

                        <button type="submit" class="btn btn-primary">Envoyer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('contactForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validation du formulaire
    if (!this.checkValidity()) {
        e.stopPropagation();
        this.classList.add('was-validated');
        return;
    }

    // Récupération des données du formulaire
    const formData = new FormData(this);

    // Envoi du message
    fetch('pages/process_contact.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            this.reset();
            this.classList.remove('was-validated');
        } else {
            showToast(data.message, 'danger');
        }
    })
    .catch(error => {
        showToast('Une erreur est survenue lors de l\'envoi du message', 'danger');
    });
});

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed top-0 end-0 m-3`;
    toast.style.zIndex = '1050';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>

<?php
include 'includes/footer.php';
?> 