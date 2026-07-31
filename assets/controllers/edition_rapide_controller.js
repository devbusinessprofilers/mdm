import { Controller } from '@hotwired/stimulus';

/*
 * Modale « Édition rapide » des listes de fiches.
 *
 * Le crayon d'une ligne transmet la ligne en paramètres ; la modale est unique
 * dans la page et se remplit à l'ouverture. Les quatre états de la maquette
 * (adresse en saisie, erreur, enregistrement, panneau des sites) sont des
 * classes sur la racine.
 *
 * Raccourcis de la maquette : Échap ferme la couche la plus haute, ⌘/Ctrl + ⏎
 * enregistre, Alt + ← / → passent d'une fiche à l'autre.
 */
export default class extends Controller {
    static targets = [
        'modale', 'confirmation', 'confirmationCorps',
        'nom', 'nomChamp', 'reference', 'gamme', 'gammeChamp', 'indiceGamme',
        'statut', 'jauge', 'taux', 'position', 'rue', 'cp', 'ville', 'gps',
        'verrou', 'boiteGamme', 'sitesCompte', 'sitesPuces', 'sitesDecompte',
        'compteGroupe', 'gerer', 'libelleEnregistrer', 'indice',
    ];

    connect() {
        this.rang = 0;
        this.crayons = Array.from(this.element.querySelectorAll('.ref__crayon'));
        // La maquette ouvre la modale sur une fiche déjà modifiée.
        this.modifiee = true;
        this.surTouche = (evenement) => this.clavier(evenement);
        window.addEventListener('keydown', this.surTouche);
    }

    disconnect() {
        window.removeEventListener('keydown', this.surTouche);
        window.clearTimeout(this.minuteur);
    }

    // ------------------------------------------------------------ ouverture

    ouvrir(evenement) {
        this.remplir(evenement.params);
        this.rang = Number(evenement.params.rang) || 0;
        this.modifiee = true;
        this.modaleTarget.hidden = false;
        this.modaleTarget.classList.remove('qe--adresse', 'qe--erreur', 'qe--enregistrement', 'qe--sites-ouverts');
        this.confirmationTarget.hidden = true;
        this.gererTarget.textContent = 'Gérer';
    }

    fermer() {
        this.modaleTarget.hidden = true;
        this.confirmationTarget.hidden = true;
        this.modaleTarget.classList.remove('qe--sites-ouverts', 'qe--enregistrement');
    }

    /* La fermeture passe par la confirmation dès qu'un champ a bougé. */
    tenterFermer() {
        if (this.modifiee) {
            this.confirmationTarget.hidden = false;
            return;
        }

        this.fermer();
    }

    abandonner() {
        this.fermer();
    }

    // ------------------------------------------------------------ navigation

    precedente() {
        this.allerA(this.rang - 1);
    }

    suivante() {
        this.allerA(this.rang + 1);
    }

    allerA(rang) {
        const borne = Math.max(0, Math.min(this.crayons.length - 1, rang));
        const crayon = this.crayons[borne];

        if (!crayon) {
            return;
        }

        this.rang = borne;
        this.remplir(this.parametres(crayon));
        this.modaleTarget.classList.remove('qe--adresse', 'qe--erreur', 'qe--sites-ouverts');
        this.confirmationTarget.hidden = true;
    }

    /*
     * Relit les paramètres Stimulus d'un crayon. La navigation ne passe pas
     * par un clic, il n'y a donc pas d'événement d'où tirer `params`.
     */
    parametres(crayon) {
        const valeurs = {};
        const prefixe = 'editionRapide';

        Object.keys(crayon.dataset).forEach((cle) => {
            if (cle.startsWith(prefixe) && cle.endsWith('Param')) {
                const nom = cle.slice(prefixe.length, -'Param'.length);
                valeurs[nom.charAt(0).toLowerCase() + nom.slice(1)] = crayon.dataset[cle];
            }
        });

        return valeurs;
    }

    // ------------------------------------------------------------- garniture

    remplir(valeurs) {
        const taux = Number(valeurs.completude) || 0;
        const gamme = valeurs.gamme || 'lieu';
        const libelleGamme = 'restaurant' === gamme ? 'Restaurants' : 'Lieux';

        this.nomTarget.textContent = valeurs.nom || '';
        this.nomChampTarget.textContent = valeurs.nom || '';
        this.referenceTarget.textContent = valeurs.reference || '';
        this.gammeTarget.textContent = libelleGamme;
        this.gammeChampTarget.textContent = libelleGamme;
        this.indiceGammeTarget.textContent = "d'après la gamme " + libelleGamme;

        this.statutTarget.textContent = valeurs.statut || '';
        this.statutTarget.classList.toggle('qe__badge--publiee', '1' === String(valeurs.publiee));

        this.jaugeTarget.style.setProperty('--qe-taux', taux + '%');
        this.tauxTarget.textContent = taux + ' %';
        this.positionTarget.textContent = 'Fiche ' + (Number(valeurs.rang ?? this.rang) + 1)
            + ' sur ' + this.crayons.length + ' · ' + (valeurs.ville || '');

        this.rueTarget.textContent = valeurs.rue || '';
        this.cpTarget.textContent = valeurs.cp || '';
        this.villeTarget.textContent = valeurs.ville || '';
        this.gpsTarget.textContent = valeurs.gps || '';

        this.confirmationCorpsTarget.textContent = '3 champs ont été modifiés sur « '
            + (valeurs.nom || '') + ' ». La liste, ses filtres et la sélection '
            + 'sont conservés dans tous les cas.';

        // La classification suit la gamme.
        this.element.querySelectorAll('.qe__classification').forEach((bloc) => {
            bloc.hidden = bloc.dataset.gamme !== gamme;
        });
    }

    // ------------------------------------------------------------- contrôles

    basculerInterrupteur(evenement) {
        const bouton = evenement.currentTarget;
        bouton.setAttribute('aria-checked', 'true' === bouton.getAttribute('aria-checked') ? 'false' : 'true');
        this.modifiee = true;
    }

    basculerVerrou() {
        const verrouille = this.boiteGammeTarget.classList.toggle('qe__boite-champ--verrouillee');
        this.verrouTarget.textContent = verrouille ? 'Déverrouiller' : 'Verrouiller';
    }

    corrigerAdresse() {
        this.modaleTarget.classList.add('qe--adresse');
    }

    // ------------------------------------------------- sites de diffusion

    basculerSites() {
        const ouvert = this.modaleTarget.classList.toggle('qe--sites-ouverts');
        this.gererTarget.textContent = ouvert ? 'Fermer' : 'Gérer';
    }

    basculerSite(evenement) {
        const site = evenement.currentTarget;

        if (site.dataset.verrouille) {
            return;
        }

        site.setAttribute('aria-checked', 'true' === site.getAttribute('aria-checked') ? 'false' : 'true');
        this.modifiee = true;
        this.recompterSites();
    }

    filtrer(evenement) {
        const retenus = 'retenus' === evenement.params.filtre;

        this.element.querySelectorAll('.qe__filtre').forEach((filtre) => {
            const actif = filtre === evenement.currentTarget;
            filtre.classList.toggle('qe__filtre--actif', actif);
            filtre.setAttribute('aria-pressed', actif ? 'true' : 'false');
        });

        this.element.querySelectorAll('.qe__site').forEach((site) => {
            site.hidden = retenus && 'true' !== site.getAttribute('aria-checked');
        });
    }

    /*
     * Le compte total, les puces de résumé et les décomptes par groupe suivent
     * les cases : c'est le seul endroit où la maquette recalcule quelque chose.
     */
    recompterSites() {
        const groupes = Array.from(this.element.querySelectorAll('.qe__groupe-sites'));
        let retenus = [];
        let total = 0;

        groupes.forEach((groupe, rang) => {
            const sites = Array.from(groupe.querySelectorAll('.qe__site'));
            const coches = sites.filter((s) => 'true' === s.getAttribute('aria-checked'));

            total += sites.length;
            retenus = retenus.concat(coches.map((s) => s.querySelector('.qe__site-nom').textContent));

            this.compteGroupeTargets[rang].textContent = coches.length + '/' + sites.length;
            this.sitesDecompteTarget.children[rang].textContent =
                groupe.querySelector('.qe__groupe-nom').textContent.trim()
                + ' ' + coches.length + '/' + sites.length;
        });

        this.sitesCompteTarget.textContent = retenus.length + ' sur ' + total;

        const puces = retenus.slice(0, 4).map((libelle) => this.puce(libelle, false));

        if (retenus.length > 4) {
            puces.push(this.puce('+' + (retenus.length - 4), true));
        }

        this.sitesPucesTarget.replaceChildren(...puces);
    }

    puce(libelle, reste) {
        const span = document.createElement('span');
        span.className = 'qe__puce' + (reste ? ' qe__puce--reste' : '');
        span.textContent = libelle;

        return span;
    }

    // --------------------------------------------------------- enregistrement

    enregistrer() {
        this.confirmationTarget.hidden = true;
        this.modaleTarget.classList.add('qe--enregistrement');
        this.libelleEnregistrerTarget.textContent = 'Enregistrement…';
        this.indiceTarget.textContent = 'Enregistrement de 4 modifications…';

        window.clearTimeout(this.minuteur);
        this.minuteur = window.setTimeout(() => {
            this.modifiee = false;
            this.fermer();
            this.libelleEnregistrerTarget.textContent = 'Enregistrer et suivante';
            this.indiceTarget.textContent =
                '⌘ ⏎ enregistrer · ⇧ ⌘ ⏎ enregistrer et suivante · Échap fermer';
        }, 1100);
    }

    // ----------------------------------------------------------------- clavier

    clavier(evenement) {
        if (this.modaleTarget.hidden) {
            return;
        }

        if ('Escape' === evenement.key) {
            evenement.preventDefault();

            if (!this.confirmationTarget.hidden) {
                this.confirmationTarget.hidden = true;
            } else if (this.modaleTarget.classList.contains('qe--sites-ouverts')) {
                this.basculerSites();
            } else {
                this.tenterFermer();
            }

            return;
        }

        if ('Enter' === evenement.key && (evenement.metaKey || evenement.ctrlKey)) {
            evenement.preventDefault();
            this.enregistrer();

            return;
        }

        if (evenement.altKey && 'ArrowRight' === evenement.key) {
            evenement.preventDefault();
            this.suivante();
        }

        if (evenement.altKey && 'ArrowLeft' === evenement.key) {
            evenement.preventDefault();
            this.precedente();
        }
    }
}
