import { Controller } from '@hotwired/stimulus';

/*
 * Compteur « x / N » sous une zone de texte simple (opt-in `data-compteur`
 * sur le champ, relayé par le thème de formulaire de la fiche). Le pendant
 * des champs riches vit dans le contrôleur wysiwyg (TinyMCE).
 */
export default class extends Controller {
    static targets = ['champ', 'affichage'];
    static values = { max: Number };

    connect() {
        this.actualiser();
    }

    actualiser() {
        if (!this.hasChampTarget || !this.hasAffichageTarget) {
            return;
        }
        const longueur = this.champTarget.value.length;
        this.affichageTarget.textContent = `${longueur} / ${this.maxValue}`;
        this.affichageTarget.classList.toggle('text-error', longueur > this.maxValue);
    }
}
