// Fonction pour initialiser le tableau de bord
function initDashboard() {
    // Initialiser les timers pour les machines en marche
    initTimers();
    
    // Initialiser les boutons de réservation
    initReservationButtons();
    
    // Initialiser les boutons d'annulation
    initCancellationButtons();
    
    // Initialiser les boutons de paiement
    initPaymentButtons();
    
    // Mettre à jour le statut des machines toutes les 30 secondes
    setInterval(updateMachineStatus, 30000);
}

// Fonction pour initialiser les timers
function initTimers() {
    const machinesEnMarche = document.querySelectorAll('.machine-card.status-en_marche');
    
    machinesEnMarche.forEach(machine => {
        const machineId = machine.getAttribute('data-machine-id');
        const timerElement = machine.querySelector('.timer');
        
        if (timerElement) {
            // Récupérer le temps restant depuis l'attribut data
            const remainingTime = parseInt(timerElement.getAttribute('data-remaining-time') || '0');
            
            if (remainingTime > 0) {
                startTimer(timerElement, remainingTime, machineId);
            }
        }
    });
}

// Fonction pour démarrer un timer
function startTimer(element, seconds, machineId) {
    let remainingSeconds = seconds;
    
    // Mettre à jour l'affichage immédiatement
    element.textContent = formatTime(remainingSeconds);
    
    // Mettre à jour toutes les secondes
    const timerInterval = setInterval(() => {
        remainingSeconds--;
        
        if (remainingSeconds <= 0) {
            clearInterval(timerInterval);
            element.textContent = "Terminé";
            
            // Mettre à jour le statut de la machine
            updateMachineStatus();
        } else {
            element.textContent = formatTime(remainingSeconds);
        }
    }, 1000);
    
    // Stocker l'intervalle pour pouvoir le nettoyer si nécessaire
    element.setAttribute('data-timer-interval', timerInterval);
}

// Fonction pour initialiser les boutons de réservation
function initReservationButtons() {
    const reserveButtons = document.querySelectorAll('.btn-reserve');
    
    reserveButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const machineId = this.getAttribute('data-machine-id');
            const machineType = this.getAttribute('data-machine-type');
            
            // Afficher le modal de réservation
            showReservationModal(machineId, machineType);
        });
    });
}

// Fonction pour afficher le modal de réservation
function showReservationModal(machineId, machineType) {
    // Créer le modal s'il n'existe pas
    let modal = document.getElementById('reservationModal');
    
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'reservationModal';
        modal.className = 'modal fade';
        modal.setAttribute('tabindex', '-1');
        modal.setAttribute('aria-labelledby', 'reservationModalLabel');
        modal.setAttribute('aria-hidden', 'true');
        
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="reservationModalLabel">Réserver une machine</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="reservationForm" data-ajax="true">
                            <input type="hidden" name="action" value="reserve">
                            <input type="hidden" name="machine_id" id="reserveMachineId">
                            <input type="hidden" name="machine_type" id="reserveMachineType">
                            
                            <div class="mb-3">
                                <label for="duration" class="form-label">Durée (minutes)</label>
                                <select class="form-select" id="duration" name="duration" required>
                                    <option value="15">15 minutes</option>
                                    <option value="30">30 minutes</option>
                                    <option value="45">45 minutes</option>
                                    <option value="60">60 minutes</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="program" class="form-label">Programme</label>
                                <select class="form-select" id="program" name="program" required>
                                    <option value="normal">Normal</option>
                                    <option value="delicate">Délicat</option>
                                    <option value="intensive">Intensif</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="temperature" class="form-label">Température</label>
                                <select class="form-select" id="temperature" name="temperature" required>
                                    <option value="cold">Froid</option>
                                    <option value="30">30°C</option>
                                    <option value="40">40°C</option>
                                    <option value="60">60°C</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Prix estimé: <span id="estimatedPrice">0.00</span> €</label>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Réserver</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Initialiser le modal Bootstrap
        const modalInstance = new bootstrap.Modal(modal);
        
        // Gérer la soumission du formulaire
        const form = modal.querySelector('#reservationForm');
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('pages/reserve_machine.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    modalInstance.hide();
                    showNotification(data.message, 'success');
                    updateMachineStatus();
                } else {
                    showNotification(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showNotification('Une erreur est survenue', 'danger');
            });
        });
        
        // Calculer le prix estimé lors du changement de durée
        const durationSelect = modal.querySelector('#duration');
        durationSelect.addEventListener('change', calculateEstimatedPrice);
    }
    
    // Mettre à jour les valeurs du modal
    document.getElementById('reserveMachineId').value = machineId;
    document.getElementById('reserveMachineType').value = machineType;
    
    // Calculer le prix estimé initial
    calculateEstimatedPrice();
    
    // Afficher le modal
    const modalInstance = new bootstrap.Modal(modal);
    modalInstance.show();
}

// Fonction pour calculer le prix estimé
function calculateEstimatedPrice() {
    const duration = parseInt(document.getElementById('duration').value);
    const machineType = document.getElementById('reserveMachineType').value;
    
    // Prix de base par minute selon le type de machine
    const basePricePerMinute = {
        'lave-linge': 0.10,
        'seche-linge': 0.08
    };
    
    const basePrice = basePricePerMinute[machineType] || 0.10;
    const estimatedPrice = (basePrice * duration).toFixed(2);
    
    document.getElementById('estimatedPrice').textContent = estimatedPrice;
}

// Fonction pour initialiser les boutons d'annulation
function initCancellationButtons() {
    const cancelButtons = document.querySelectorAll('.btn-cancel');
    
    cancelButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const machineId = this.getAttribute('data-machine-id');
            
            if (confirm('Êtes-vous sûr de vouloir annuler cette réservation ?')) {
                cancelReservation(machineId);
            }
        });
    });
}

// Fonction pour annuler une réservation
function cancelReservation(machineId) {
    const formData = new FormData();
    formData.append('action', 'cancel');
    formData.append('machine_id', machineId);
    
    fetch('pages/cancel_reservation.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            updateMachineStatus();
        } else {
            showNotification(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Une erreur est survenue', 'danger');
    });
}

// Fonction pour initialiser les boutons de paiement
function initPaymentButtons() {
    const paymentButtons = document.querySelectorAll('.btn-pay');
    
    paymentButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const machineId = this.getAttribute('data-machine-id');
            const amount = this.getAttribute('data-amount');
            
            // Afficher le modal de paiement
            showPaymentModal(machineId, amount);
        });
    });
}

// Fonction pour afficher le modal de paiement
function showPaymentModal(machineId, amount) {
    // Créer le modal s'il n'existe pas
    let modal = document.getElementById('paymentModal');
    
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'paymentModal';
        modal.className = 'modal fade';
        modal.setAttribute('tabindex', '-1');
        modal.setAttribute('aria-labelledby', 'paymentModalLabel');
        modal.setAttribute('aria-hidden', 'true');
        
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="paymentModalLabel">Paiement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="paymentForm" data-ajax="true">
                            <input type="hidden" name="action" value="pay">
                            <input type="hidden" name="machine_id" id="payMachineId">
                            <input type="hidden" name="amount" id="payAmount">
                            
                            <div class="mb-3">
                                <label class="form-label">Montant à payer: <span id="paymentAmount">0.00</span> €</label>
                            </div>
                            
                            <div class="mb-3">
                                <label for="paymentMethod" class="form-label">Méthode de paiement</label>
                                <select class="form-select" id="paymentMethod" name="payment_method" required>
                                    <option value="card">Carte bancaire</option>
                                    <option value="cash">Espèces</option>
                                    <option value="app">Application mobile</option>
                                </select>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Payer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Initialiser le modal Bootstrap
        const modalInstance = new bootstrap.Modal(modal);
        
        // Gérer la soumission du formulaire
        const form = modal.querySelector('#paymentForm');
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('pages/process_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    modalInstance.hide();
                    showNotification(data.message, 'success');
                    updateMachineStatus();
                } else {
                    showNotification(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showNotification('Une erreur est survenue', 'danger');
            });
        });
    }
    
    // Mettre à jour les valeurs du modal
    document.getElementById('payMachineId').value = machineId;
    document.getElementById('payAmount').value = amount;
    document.getElementById('paymentAmount').textContent = amount;
    
    // Afficher le modal
    const modalInstance = new bootstrap.Modal(modal);
    modalInstance.show();
}

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    // Vérifier si nous sommes sur la page du tableau de bord
    if (document.querySelector('.dashboard-container')) {
        initDashboard();
    }
}); 