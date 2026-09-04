import { Controller } from '@hotwired/stimulus';

/*
 * Affichage conditionnel générique de l'éditeur de fiche.
 *
 * Posé sur le formulaire de la fiche (`change->affichage-conditionnel#maj`).
 * Une cible (`data-affichage-conditionnel-target="cible"`) porte :
 *  - data-source   : l'id du widget source (groupe de radios/cases, ou <select>) ;
 *  - data-valeurs  : les valeurs qui la montrent, séparées par « | » ;
 *  - data-vider    : si présent, ses champs sont vidés quand elle se masque ;
 *  - data-desactiver : si présent, ses champs sont désactivés tant qu'elle
 *    est masquée (rien n'est soumis) et réactivés quand elle se montre.
 * Le masquage passe par `data-masque` (classe `data-[masque]:hidden`), sans
 * toucher au `hidden` que le contrôleur des onglets pose sur les volets.
 */
export default class extends Controller {
    static targets = ['cible'];

    connect() {
        this.maj();
    }

    cibleTargetConnected() {
        this.maj();
    }

    maj() {
        this.cibleTargets.forEach((cible) => {
            const valeurs = this.valeursSource(cible.dataset.source);
            const attendues = (cible.dataset.valeurs || '').split('|');
            const visible = attendues.some((valeur) => valeurs.includes(valeur));
            if ('desactiver' in cible.dataset) {
                cible.querySelectorAll('input, select, textarea').forEach((champ) => { champ.disabled = !visible; });
            }
            if (visible) {
                delete cible.dataset.masque;
                return;
            }
            const deja = 'masque' in cible.dataset;
            cible.dataset.masque = '';
            if (!deja && 'vider' in cible.dataset) {
                this.vider(cible);
            }
        });
    }

    valeursSource(id) {
        if (!id) {
            return [];
        }
        const source = this.element.querySelector(`#${CSS.escape(id)}`);
        if (!source) {
            return [];
        }
        if (source.matches('select')) {
            return Array.from(source.selectedOptions).map((option) => option.value);
        }
        if (source.matches('input')) {
            return source.checked || !['checkbox', 'radio'].includes(source.type) ? [source.value] : [];
        }

        return Array.from(source.querySelectorAll('input:checked')).map((input) => input.value);
    }

    vider(cible) {
        cible.querySelectorAll('input, select, textarea').forEach((champ) => {
            if (champ.matches('input[type="radio"]')) {
                // Oui/Non : retour à « Non » (valeur 0) plutôt qu'à rien de coché.
                champ.checked = champ.value === '0';
            } else if (champ.matches('input[type="checkbox"]')) {
                champ.checked = false;
            } else if (champ.matches('select')) {
                Array.from(champ.options).forEach((option) => { option.selected = false; });
            } else if (!champ.matches('input[type="hidden"]')) {
                champ.value = '';
            }
        });
    }
}
