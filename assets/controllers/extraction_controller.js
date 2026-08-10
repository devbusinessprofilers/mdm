import { Controller } from '@hotwired/stimulus';

/*
 * Modale « Extraire d'un document ».
 *
 * Trois temps — dépôt, lecture, validation — que la maquette enchaîne sans
 * recharger. Les trois volets sont dans le DOM ; on ne fait que les révéler.
 *
 * Le pied change de libellé à chaque étape : les trois sont posés en valeurs
 * Stimulus par le gabarit, pour qu'aucune chaîne ne vive dans ce fichier.
 */
export default class extends Controller {
    static targets = ['modale', 'volet', 'etape', 'puce', 'puceTexte', 'etapeLibelle',
        'avancer', 'reculer', 'valeur'];
    static values = { libelles: Object, etapes: Array };

    connect() {
        this.rang = 0;
    }

    ouvrir(evenement) {
        evenement?.preventDefault();
        this.rang = 0;
        this.modaleTarget.hidden = false;
        this.peindre();
    }

    fermer() {
        this.modaleTarget.hidden = true;
    }

    avancer() {
        // Au dernier temps, « Appliquer » clôt la modale : la fiche a sa réponse.
        if (this.rang >= this.etapesValue.length - 1) {
            this.fermer();

            return;
        }

        this.rang += 1;
        this.peindre();
    }

    reculer() {
        // Au premier temps, le bouton dit « Annuler » et referme.
        if (0 === this.rang) {
            this.fermer();

            return;
        }

        this.rang -= 1;
        this.peindre();
    }

    /* Une valeur acceptée ou rejetée sort de la liste des décisions à prendre. */
    accepter(evenement) {
        this.trancher(evenement, 'acceptée');
    }

    rejeter(evenement) {
        this.trancher(evenement, 'rejetée');
    }

    /* Le lot ne prend que les valeurs de confiance maximale, jamais les identiques. */
    accepterEnLot() {
        this.valeurTargets.forEach((ligne) => {
            if ('1' !== ligne.dataset.deja && Number(ligne.dataset.confiance) >= 4) {
                this.marquer(ligne, 'acceptée');
            }
        });
    }

    trancher(evenement, verdict) {
        const ligne = evenement.currentTarget.closest('[data-extraction-target="valeur"]');

        if (ligne) {
            this.marquer(ligne, verdict);
        }
    }

    marquer(ligne, verdict) {
        if (ligne.dataset.verdict) {
            return;
        }

        ligne.dataset.verdict = verdict;
        ligne.classList.add('opacity-55');
        ligne.querySelectorAll('button').forEach((bouton) => {
            bouton.disabled = true;
            bouton.classList.add('pointer-events-none');
        });
    }

    peindre() {
        const courante = this.etapesValue[this.rang];

        this.voletTargets.forEach((volet) => {
            volet.hidden = volet.dataset.etape !== courante;
        });

        this.etapeTargets.forEach((etape, rang) => {
            const active = rang === this.rang;
            const passee = rang < this.rang;

            this.puceTargets[rang].className = this.puceTargets[rang].className
                .replace(/bg-\S+/g, '')
                + (active ? ' bg-linear-(--primary-gradient)' : passee ? ' bg-primary' : ' bg-neutral-200');

            this.teinter(this.puceTextTarget(rang), active || passee ? 'neutral-100' : 'neutral-500');
            this.teinter(this.etapeLibelleTargets[rang],
                active ? 'primary-3' : passee ? 'neutral-900' : 'neutral-500');
        });

        this.reculerTarget.querySelector('span, p').textContent = 0 === this.rang ? 'Annuler' : 'Retour';
        this.avancerTarget.querySelector('span, p').textContent = this.libellesValue[courante];
    }

    puceTextTarget(rang) {
        return this.puceTexteTargets[rang];
    }

    /*
     * `Typography` pose sa couleur en classe : on retire l'ancienne avant
     * d'ajouter la nouvelle, sinon les deux cohabitent et l'ordre du fichier
     * CSS décide à notre place.
     */
    teinter(element, teinte) {
        if (!element) {
            return;
        }

        element.classList.remove('text-neutral-100', 'text-neutral-500',
            'text-neutral-900', 'text-primary-3');
        element.classList.add('text-' + teinte);
    }
}
