import { Controller } from '@hotwired/stimulus';

/*
 * Liste des fiches.
 *
 * Les filtres passent par le serveur : lignes, badges et compte sortent d'un
 * seul prédicat, et les recalculer ici les ferait diverger. Le contrôleur ne
 * garde que ce qui est purement local — les panneaux, la sélection de lignes
 * et le repli du rail.
 */
export default class extends Controller {
    static targets = ['panneau', 'replie', 'ouvert', 'picker', 'plus', 'vues',
        'bandeau', 'vueFiltres', 'vueActions', 'compteSelection', 'ligne', 'caseTete'];

    connect() {
        this.surTouche = (evenement) => {
            if ('Escape' !== evenement.key) {
                return;
            }

            if (!this.vuesTarget.hidden) {
                this.vuesTarget.hidden = true;
            } else if (!this.pickerTarget.hidden) {
                this.pickerTarget.hidden = true;
            } else if (!this.plusTarget.hidden) {
                this.plusTarget.hidden = true;
            }
        };
        window.addEventListener('keydown', this.surTouche);

        // Un clic hors des menus les referme, comme les autres écrans.
        this.surClic = (evenement) => {
            if (!this.element.contains(evenement.target)) {
                return;
            }

            if (!this.pickerTarget.parentElement.contains(evenement.target)) {
                this.pickerTarget.hidden = true;
            }

            if (!this.plusTarget.parentElement.contains(evenement.target)) {
                this.plusTarget.hidden = true;
            }
        };
        document.addEventListener('click', this.surClic);
    }

    disconnect() {
        window.removeEventListener('keydown', this.surTouche);
        document.removeEventListener('click', this.surClic);
    }

    /* Le repli est porté par les largeurs Tailwind, pas par une classe d'écran. */
    basculerPanneau() {
        const replie = this.panneauTarget.classList.toggle('w-[60px]');

        this.panneauTarget.classList.toggle('w-[284px]', !replie);
        this.replieTarget.classList.toggle('flex', replie);
        this.replieTarget.classList.toggle('hidden', !replie);
        this.ouvertTarget.classList.toggle('flex', !replie);
        this.ouvertTarget.classList.toggle('hidden', replie);
    }

    basculerPicker(evenement) {
        const ouvert = this.pickerTarget.hidden;
        this.pickerTarget.hidden = !ouvert;
        this.plusTarget.hidden = true;
        evenement.currentTarget.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
    }

    basculerPlus(evenement) {
        const ouvert = this.plusTarget.hidden;
        this.plusTarget.hidden = !ouvert;
        this.pickerTarget.hidden = true;
        evenement.currentTarget.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
    }

    basculerVues() {
        this.vuesTarget.hidden = !this.vuesTarget.hidden;
        this.pickerTarget.hidden = true;
    }

    /*
     * Une facette cochée ici ne recalcule rien : c'est le serveur qui tient le
     * prédicat. Le clic marque l'intention, le rechargement viendra du jour où
     * les filtres seront dans l'URL.
     */
    basculerFacette(evenement) {
        const facette = evenement.currentTarget;
        facette.setAttribute('aria-checked', this.coche(facette) ? 'false' : 'true');
    }

    basculerLigne(evenement) {
        const bouton = evenement.currentTarget;
        const cochee = !this.coche(bouton);

        bouton.setAttribute('aria-checked', cochee ? 'true' : 'false');
        bouton.closest('[data-liste-target="ligne"]').classList.toggle('bg-primary-4', cochee);

        this.majBandeau();
    }

    /* Le bandeau passe en mode sélection dès qu'une ligne est cochée. */
    majBandeau() {
        const cochees = this.ligneTargets.filter((l) => l.classList.contains('bg-primary-4'));
        const enSelection = cochees.length > 0;

        this.bandeauTarget.classList.toggle('bg-primary-4', enSelection);
        this.bandeauTarget.classList.toggle('inset-ring-primary', enSelection);
        this.bandeauTarget.classList.toggle('bg-neutral-100', !enSelection);
        this.bandeauTarget.classList.toggle('inset-ring-neutral-200', !enSelection);

        this.vueFiltresTarget.classList.toggle('hidden', enSelection);
        this.vueFiltresTarget.classList.toggle('flex', !enSelection);
        this.vueActionsTarget.classList.toggle('flex', enSelection);
        this.vueActionsTarget.classList.toggle('hidden', !enSelection);

        if (enSelection) {
            /*
             * Le total vient d'un attribut, pas d'un découpage du libellé : les
             * milliers y sont séparés par une espace fine, « 15 906 » se serait
             * lu « 15 ».
             */
            const total = this.bandeauTarget.dataset.total.replace(/\s*fiches?$/, '');
            this.compteSelectionTarget.textContent = cochees.length + ' sélectionnées sur ' + total;
        }
    }

    coche(element) {
        return 'true' === element.getAttribute('aria-checked');
    }
}
