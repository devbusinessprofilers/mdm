import { Controller } from '@hotwired/stimulus';

/*
 * Conditions de paiement annulation (maquette portail) : la frise colorée
 * résume les pourcentages par tranche ; « Modifier » la remplace par les
 * neuf champs de saisie.
 */
export default class extends Controller {
    static targets = ['bouton', 'apercu', 'edition'];

    modifier() {
        this.boutonTarget.remove();
        this.apercuTarget.remove();
        this.editionTarget.classList.remove('hidden');
    }
}
