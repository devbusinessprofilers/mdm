import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */

/*
 * Visionneuse de logs (/admin/performance) : déplie / replie le contexte JSON
 * d'une ligne au clic sur le message.
 */
export default class extends Controller {
    static targets = ['contenu', 'indicateur'];

    toggle() {
        this.contenuTarget.classList.toggle('hidden');
        if (this.hasIndicateurTarget) {
            this.indicateurTarget.classList.toggle('rotate-180');
        }
    }
}
