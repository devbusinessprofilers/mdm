import { Controller } from '@hotwired/stimulus';

/*
 * Bascule d'affichage du mot de passe.
 *
 * La maquette prévoit l'icône œil dans le champ sans en décrire le
 * comportement : on se limite au strict nécessaire côté intégration,
 * afficher ou masquer la saisie, en tenant le libellé accessible à jour.
 */
export default class extends Controller {
    static targets = ['input', 'bouton'];

    toggle() {
        const affiche = this.inputTarget.type === 'text';

        this.inputTarget.type = affiche ? 'password' : 'text';

        if (this.hasBoutonTarget) {
            this.boutonTarget.setAttribute(
                'aria-label',
                affiche ? 'Afficher le mot de passe' : 'Masquer le mot de passe',
            );
        }
    }
}
