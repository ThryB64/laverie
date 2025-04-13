// Fonction pour afficher les notifications
function showNotification(message, type = 'success') {
    const notification = document.getElementById('notification');
    if (!notification) {
        const div = document.createElement('div');
        div.id = 'notification';
        div.className = `alert alert-${type} alert-dismissible fade show`;
        div.innerHTML = `
            ${message}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        `;
        document.body.appendChild(div);
    } else {
        notification.className = `alert alert-${type} alert-dismissible fade show`;
        notification.innerHTML = `
            ${message}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        `;
    }
    
    setTimeout(() => {
        const alert = document.querySelector('.alert');
        if (alert) {
            alert.classList.remove('show');
            setTimeout(() => alert.remove(), 150);
        }
    }, 5000);
}

// Fonction pour mettre à jour le statut des machines
function updateMachineStatus() {
    fetch('pages/check_status.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                data.machines.forEach(machine => {
                    const card = document.querySelector(`#machine-${machine.id}`);
                    if (card) {
                        card.className = `card machine-card status-${machine.etat}`;
                        const statusBadge = card.querySelector('.status-badge');
                        if (statusBadge) {
                            statusBadge.textContent = machine.etat;
                            statusBadge.className = `badge badge-${getStatusClass(machine.etat)} status-badge`;
                        }
                    }
                });
            }
        })
        .catch(error => console.error('Erreur:', error));
}

// Fonction pour obtenir la classe CSS selon le statut
function getStatusClass(status) {
    const statusClasses = {
        'disponible': 'success',
        'reserve': 'warning',
        'en_marche': 'info',
        'maintenance': 'danger'
    };
    return statusClasses[status] || 'secondary';
}

// Fonction pour formater le temps
function formatTime(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;
    
    return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
}

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    // Mise à jour automatique du statut toutes les 30 secondes
    setInterval(updateMachineStatus, 30000);
    
    // Gestion des formulaires AJAX
    document.querySelectorAll('form[data-ajax="true"]').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                } else {
                    showNotification(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showNotification('Une erreur est survenue', 'danger');
            });
        });
    });
}); 