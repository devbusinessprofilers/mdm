import { Controller } from '@hotwired/stimulus';

/*
 * Formulaire de création de fiche : affiche uniquement les sections
 * pertinentes pour la gamme choisie (chaque section liste ses gammes dans
 * data-types). Les champs d'une section masquée sont réinitialisés pour
 * rester cohérents avec la neutralisation côté serveur.
 */
export default class extends Controller {
    static targets = ['type', 'section', 'voile'];

    /* Voile de la maquette : posé au départ du POST de création. */
    patienter() {
        if (this.hasVoileTarget) {
            this.voileTarget.hidden = false;
        }
    }

    connect() {
        this.refresh();
    }

    refresh() {
        const type = this.hasTypeTarget ? this.typeTarget.value : '';
        for (const section of this.sectionTargets) {
            const visible = (section.dataset.types ?? '').split(' ').includes(type);
            section.hidden = !visible;
            if (!visible) {
                this.reset(section);
            }
        }
    }

    reset(section) {
        for (const field of section.querySelectorAll('select')) {
            for (const option of field.options) {
                option.selected = false;
            }
        }
        for (const field of section.querySelectorAll('input[type="checkbox"], input[type="radio"]')) {
            field.checked = false;
        }
    }
}
