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
        this.dispatch('opened');
    }

    close() {
        this.modalTarget.style.display = 'none';
        this.dispatch('closed');
    }

    // Clic sur le voile (et pas sur le panneau, dont les clics ne remontent
    // pas avec la cible du voile) : fermeture.
    backdrop(event) {
        if (event.target === this.modalTarget) {
            this.close();
        }
    }

    // Échap ferme la modale ouverte ; les modales fermées ignorent l'événement.
    // Deux modales peuvent être empilées (paramètres photo + recadrage) : seule
    // celle du dessus se ferme — à z-index égal, l'ordre du document fait foi.
    escape() {
        if (this.modalTarget.style.display !== 'flex') {
            return;
        }
        const ouvertes = Array.from(document.querySelectorAll('[data-modal]'))
            .filter(element => element.style.display === 'flex');
        if (ouvertes[ouvertes.length - 1] === this.modalTarget) {
            this.close();
        }
    }
}
