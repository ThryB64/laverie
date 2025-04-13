// Fonction pour valider les formulaires
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;

    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add('error');
        } else {
            field.classList.remove('error');
        }
    });

    return isValid;
}

// Fonction pour afficher/masquer les messages
function toggleMessage(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.style.display = element.style.display === 'none' ? 'block' : 'none';
    }
}

// Fonction pour mettre à jour le timer
function updateTimer(elementId, endTime) {
    const timerElement = document.getElementById(elementId);
    if (!timerElement) return;

    const timer = setInterval(() => {
        const now = new Date().getTime();
        const distance = endTime - now;

        if (distance < 0) {
            clearInterval(timer);
            timerElement.innerHTML = "Temps écoulé";
            return;
        }

        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        timerElement.innerHTML = `${hours}h ${minutes}m ${seconds}s`;
    }, 1000);
}

// Écouteurs d'événements au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des formulaires
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this.id)) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs requis');
            }
        });
    });

    // Gestion des messages d'alerte
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.display = 'none';
        }, 5000);
    });
}); 