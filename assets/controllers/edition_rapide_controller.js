import { Controller } from '@hotwired/stimulus';

/*
 * Modale « Édition rapide » des listes de fiches.
 *
 * Le crayon d'une ligne transmet la ligne en paramètres ; la modale est unique
 * dans la page et se remplit à l'ouverture. Les quatre états de la maquette
 * (adresse en saisie, erreur, enregistrement, panneau des sites) sont des
 * attributs `data-` sur la racine, que Tailwind diffuse par le groupe `qe`.
 * Aucun sélecteur de classe ici : tout passe par des cibles Stimulus, pour que
 * l'habillage puisse bouger sans casser le contrôleur.
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
        'compteGroupe', 'gerer', 'libelleEnregistrer', 'indice', 'crayon',
        'classification', 'groupeSite', 'groupeNom', 'site', 'siteNom', 'filtre',
    ];

    /* Attribut de valeur du composant ProgressBar du portail. */
    static JAUGE = 'data-provider-portal--progress-bar-current-value';

    connect() {
        this.rang = 0;
        /*
         * Les crayons sont des cibles Stimulus, pas une classe CSS : la modale
         * suit les listes qui changent de gabarit sans qu'on y revienne.
         */
        this.crayons = this.crayonTargets;
        // La maquette ouvre la modale sur une fiche déjà modifiée.
        this.modifiee = true;
        this.surTouche = (evenement) => this.clavier(evenement);
        window.addEventListener('keydown', this.surTouche);
    }

    disconnect() {
        window.removeEventListener('keydown', this.surTouche);
        window.clearTimeout(this.minuteur);
    }

    /* Les quatre états de la maquette. */
    etat(nom, actif) {
        this.modaleTarget.dataset[nom] = actif ? '1' : '0';
    }

    actif(nom) {
        return '1' === this.modaleTarget.dataset[nom];
    }

    // ------------------------------------------------------------ ouverture

    ouvrir(evenement) {
        this.remplir(evenement.params);
        this.rang = Number(evenement.params.rang) || 0;
        this.modifiee = true;
        this.modaleTarget.hidden = false;
        ['adresse', 'erreur', 'enregistrement', 'sites'].forEach((nom) => this.etat(nom, false));
        this.confirmationTarget.hidden = true;
        this.gererTarget.textContent = 'Gérer';
    }

    fermer() {
        this.modaleTarget.hidden = true;
        this.confirmationTarget.hidden = true;
        this.etat('sites', false);
        this.etat('enregistrement', false);
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
        ['adresse', 'erreur', 'sites'].forEach((nom) => this.etat(nom, false));
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
        const publiee = '1' === String(valeurs.publiee);

        this.nomTarget.textContent = valeurs.nom || '';
        this.nomChampTarget.textContent = valeurs.nom || '';
        this.referenceTarget.textContent = valeurs.reference || '';
        this.gammeTarget.textContent = libelleGamme;
        this.gammeChampTarget.textContent = libelleGamme;
        this.indiceGammeTarget.textContent = "d'après la gamme " + libelleGamme;

        this.statutTarget.textContent = valeurs.statut || '';
        this.statutTarget.classList.toggle('bg-success-pastel', publiee);
        this.statutTarget.classList.toggle('text-success', publiee);
        this.statutTarget.classList.toggle('bg-neutral-200', !publiee);
        this.statutTarget.classList.toggle('text-neutral-500', !publiee);

        /* La jauge est un ProgressBar : on la pilote par sa valeur Stimulus. */
        this.jaugeTarget.setAttribute(this.constructor.JAUGE, taux);
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
        this.classificationTargets.forEach((bloc) => {
            bloc.hidden = bloc.dataset.gamme !== gamme;
        });
    }

    // ------------------------------------------------------------- contrôles

    basculerVerrou() {
        const verrouille = '1' !== this.boiteGammeTarget.dataset.verrouille;
        this.boiteGammeTarget.dataset.verrouille = verrouille ? '1' : '0';
        this.verrouTarget.textContent = verrouille ? 'Déverrouiller' : 'Verrouiller';
    }

    corrigerAdresse() {
        this.etat('adresse', true);
    }

    // ------------------------------------------------- sites de diffusion

    basculerSites() {
        const ouvert = !this.actif('sites');
        this.etat('sites', ouvert);
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

        this.filtreTargets.forEach((filtre) => {
            filtre.setAttribute('aria-pressed', filtre === evenement.currentTarget ? 'true' : 'false');
        });

        this.siteTargets.forEach((site) => {
            site.hidden = retenus && 'true' !== site.getAttribute('aria-checked');
        });
    }

    /*
     * Le compte total, les puces de résumé et les décomptes par groupe suivent
     * les cases : c'est le seul endroit où la maquette recalcule quelque chose.
     */
    recompterSites() {
        let retenus = [];
        let total = 0;

        this.groupeSiteTargets.forEach((groupe, rang) => {
            const sites = this.siteTargets.filter((site) => groupe.contains(site));
            const coches = sites.filter((site) => 'true' === site.getAttribute('aria-checked'));

            total += sites.length;
            retenus = retenus.concat(coches.map(
                (site) => this.siteNomTargets.find((nom) => site.contains(nom)).textContent
            ));

            this.compteGroupeTargets[rang].textContent = coches.length + '/' + sites.length;
            this.sitesDecompteTarget.children[rang].textContent =
                this.groupeNomTargets[rang].textContent.trim()
                + ' ' + coches.length + '/' + sites.length;
        });

        this.sitesCompteTarget.textContent = retenus.length + ' sur ' + total;

        const puces = retenus.slice(0, 4).map((libelle) => this.puce(libelle, false));

        if (retenus.length > 4) {
            puces.push(this.puce('+' + (retenus.length - 4), true));
        }

        this.sitesPucesTarget.replaceChildren(...puces);
    }

    /*
     * Même composition que la puce du gabarit : enveloppe teintée, texte
     * `body-xs` gras. Les classes sont écrites en toutes lettres pour que
     * Tailwind les voie à la compilation.
     */
    puce(libelle, reste) {
        const enveloppe = document.createElement('span');
        const texte = document.createElement('span');

        enveloppe.className = 'inline-flex items-center box-border gap-[5px] px-2 py-[3px] '
            + 'rounded-lg whitespace-nowrap ' + (reste ? 'bg-neutral-200' : 'bg-primary-4');
        texte.className = 'antialiased text-[0.625rem] leading-[1rem] font-sans font-[900] '
            + (reste ? 'text-neutral-500' : 'text-primary-3');
        texte.textContent = libelle;
        enveloppe.append(texte);

        return enveloppe;
    }

    // --------------------------------------------------------- enregistrement

    enregistrer() {
        this.confirmationTarget.hidden = true;
        this.etat('enregistrement', true);
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
            } else if (this.actif('sites')) {
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
