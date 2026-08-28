import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    decocherTout() {
        this.#decocher(this.element);
    }

    decocherOnglet(event) {
        this.#decocher(event.currentTarget.closest('[data-export-colonnes-onglet]'));
    }

    #decocher(zone) {
        zone.querySelectorAll('input[type="checkbox"]:not(:disabled)').forEach((caseColonne) => {
            caseColonne.checked = false;
        });
    }
}
