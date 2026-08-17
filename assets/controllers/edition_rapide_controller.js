import { Controller } from '@hotwired/stimulus';

/*
 * Modale « Édition rapide » de la liste des fiches.
 *
 * Écart avec la maquette (qui remplissait une modale factice côté client) :
 * la coquille recouvre la liste et une turbo-frame y charge la vraie page
 * d'édition rapide — formulaire Symfony, enregistrement et navigation entre
 * fiches compris. Le crayon reste un lien : sans JavaScript, il ouvre la
 * page en plein écran, et les boutons Fermer/Annuler de la frame naviguent
 * vers la liste (le contrôleur les intercepte quand la modale est là).
 */
export default class extends Controller {
    static targets = ['modale', 'cadre'];

    connect() {
        this.surTouche = (evenement) => {
            if ('Escape' === evenement.key && !this.modaleTarget.hidden) {
                this.fermer();
            }
        };
        window.addEventListener('keydown', this.surTouche);
    }

    disconnect() {
        window.removeEventListener('keydown', this.surTouche);
    }

    ouvrir(evenement) {
        evenement.preventDefault();
        this.cadreTarget.src = evenement.currentTarget.href;
        this.modaleTarget.hidden = false;
    }

    /* Ferme sans recharger : la liste reste telle quelle sous la modale. */
    fermer(evenement) {
        evenement?.preventDefault();
        this.modaleTarget.hidden = true;
        this.cadreTarget.removeAttribute('src');
        this.cadreTarget.innerHTML = '';
    }
}
