import { Controller } from '@hotwired/stimulus';

/*
 * Rail du tableau de bord : il suit le défilement du contenu.
 *
 * Reprise du comportement de la maquette, y compris sa règle de fin de liste :
 * arrivé en bas, le repère suit la DERNIÈRE zone au moins partiellement
 * visible. Une règle « la plus grande surface visible » exclurait
 * structurellement une zone finale courte, et le clic qui amène jusqu'ici
 * serait écrasé par ce même gestionnaire.
 */
export default class extends Controller {
    static targets = ['contenu', 'zone', 'entree'];

    connect() {
        this.surDefilement = () => this.suivre();
        this.contenuTarget.addEventListener('scroll', this.surDefilement, { passive: true });
        this.suivre();
    }

    disconnect() {
        this.contenuTarget.removeEventListener('scroll', this.surDefilement);
    }

    /* Clic sur une entrée du rail : on amène la zone en haut du contenu. */
    aller(evenement) {
        const rang = Number(evenement.params.zone) || 0;
        const zone = this.zoneTargets[rang];

        if (!zone) {
            return;
        }

        this.contenuTarget.scrollTop = Math.max(0, zone.offsetTop - 12);
        this.marquer(rang);
    }

    suivre() {
        const contenu = this.contenuTarget;
        const enBas = contenu.scrollTop + contenu.clientHeight >= contenu.scrollHeight - 4;
        let retenue = 0;

        this.zoneTargets.forEach((zone, rang) => {
            const haut = zone.offsetTop - contenu.scrollTop;

            if (enBas) {
                const visible = Math.min(haut + zone.offsetHeight, contenu.clientHeight) - Math.max(haut, 0);

                if (visible > 0) {
                    retenue = rang;
                }

                return;
            }

            if (haut <= 140) {
                retenue = rang;
            }
        });

        this.marquer(retenue);
    }

    marquer(rang) {
        this.entreeTargets.forEach((entree, i) => {
            entree.setAttribute('aria-current', i === rang ? 'true' : 'false');
        });
    }
}
