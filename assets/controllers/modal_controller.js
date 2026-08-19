import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal'];
    static values = { display: Boolean };

    connect() {
        if (this.displayValue) {
            this.open();
        }
    }

    open() {
        this.modalTarget.style.display = 'flex';
    }

    close() {
        this.modalTarget.style.display = 'none';
    }

    // Clic sur le voile (et pas sur le panneau, dont les clics ne remontent
    // pas avec la cible du voile) : fermeture.
    backdrop(event) {
        if (event.target === this.modalTarget) {
            this.close();
        }
    }

    // Échap ferme la modale ouverte ; les modales fermées ignorent l'événement.
    escape() {
        if (this.modalTarget.style.display === 'flex') {
            this.close();
        }
    }
}
