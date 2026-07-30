import { Controller } from '@hotwired/stimulus';

/*
 * Ouverture du menu déroulant d'un champ de type liste.
 *
 * La maquette fournit la molécule « Menu dropdown » mais pas son
 * comportement : on se limite au strict nécessaire côté intégration —
 * ouvrir, fermer au clic extérieur ou à Échap, et tenir `aria-expanded` à jour.
 * Aucune sélection n'est enregistrée.
 */
export default class extends Controller {
    static targets = ['menu', 'declencheur'];

    connect() {
        this.fermerAuClicExterieur = (evenement) => {
            if (!this.element.contains(evenement.target)) {
                this.fermer();
            }
        };

        this.fermerEchap = (evenement) => {
            if ('Escape' === evenement.key) {
                this.fermer();
            }
        };

        document.addEventListener('click', this.fermerAuClicExterieur);
        document.addEventListener('keydown', this.fermerEchap);
    }

    disconnect() {
        document.removeEventListener('click', this.fermerAuClicExterieur);
        document.removeEventListener('keydown', this.fermerEchap);
    }

    basculer(evenement) {
        evenement.preventDefault();
        this.menuTarget.hidden ? this.ouvrir() : this.fermer();
    }

    ouvrir() {
        this.menuTarget.hidden = false;
        this.majEtat(true);
    }

    fermer() {
        if (this.menuTarget.hidden) {
            return;
        }

        this.menuTarget.hidden = true;
        this.majEtat(false);
    }

    majEtat(ouvert) {
        if (this.hasDeclencheurTarget) {
            this.declencheurTarget.setAttribute('aria-expanded', String(ouvert));
        }
    }
}
