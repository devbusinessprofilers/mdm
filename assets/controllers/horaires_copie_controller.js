import { Controller } from '@hotwired/stimulus';

/*
 * Copie des horaires d'un jour vers les jours suivants (demande métier, bible
 * row 13) : pur client, avant soumission. Les jours suivants déjà ouverts
 * reçoivent les heures ; si aucun ne l'est, tous les suivants sont ouverts et
 * remplis — l'utilisateur referme ensuite ceux qui ne s'appliquent pas.
 *
 * Cibles : chaque colonne de jour est une cible `jour` (dans l'ordre de la
 * LOV), portant son interrupteur (checkbox) et ses deux champs `time`.
 */
export default class extends Controller {
    static targets = ['jour'];

    copier(event) {
        const source = event.currentTarget.closest('[data-horaires-copie-target~="jour"]');
        const index = this.jourTargets.indexOf(source);
        if (index < 0) {
            return;
        }
        const heures = this.heures(source);
        const suivants = this.jourTargets.slice(index + 1);
        let cibles = suivants.filter((jour) => this.interrupteur(jour)?.checked);
        if (0 === cibles.length) {
            cibles = suivants;
        }
        cibles.forEach((jour) => {
            const interrupteur = this.interrupteur(jour);
            if (interrupteur && !interrupteur.checked) {
                interrupteur.checked = true;
                interrupteur.dispatchEvent(new Event('change', { bubbles: true }));
            }
            const champs = this.heures(jour);
            if (champs.ouverture && heures.ouverture) {
                champs.ouverture.value = heures.ouverture.value;
            }
            if (champs.fermeture && heures.fermeture) {
                champs.fermeture.value = heures.fermeture.value;
            }
        });
    }

    interrupteur(jour) {
        return jour.querySelector('input[type="checkbox"]');
    }

    heures(jour) {
        const champs = jour.querySelectorAll('input[type="time"]');

        return { ouverture: champs[0] ?? null, fermeture: champs[1] ?? null };
    }
}
