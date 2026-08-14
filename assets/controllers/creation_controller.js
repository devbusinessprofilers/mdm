import { Controller } from '@hotwired/stimulus';

/*
 * Tunnel de création d'une fiche.
 *
 * Le choix de la gamme passe par le serveur — c'est lui qui décide des blocs et
 * de leurs champs. Tout le reste se règle ici : puces de classement,
 * départements, sites de diffusion, interrupteurs, contact de repli, mode
 * d'envoi des accès, et le rail qui suit le défilement.
 */
export default class extends Controller {
    static targets = [
        'ancre', 'defilement', 'bloc', 'suggestions', 'identite', 'contact',
        'banniere', 'banniereTitre',
        'compteClasse', 'zoneCompte', 'sitesCompte', 'sitesPuces', 'compteGroupe',
        'trame', 'trameIndice',
    ];

    static PUCES_SITES = 6;

    connect() {
        this.surDefilement = () => this.suivreDefilement();
        this.defilementTarget.addEventListener('scroll', this.surDefilement);

        this.surTouche = (evenement) => {
            if ('Escape' === evenement.key && this.hasSuggestionsTarget) {
                this.suggestionsTarget.hidden = true;
            }
        };
        window.addEventListener('keydown', this.surTouche);
    }

    disconnect() {
        this.defilementTarget.removeEventListener('scroll', this.surDefilement);
        window.removeEventListener('keydown', this.surTouche);
    }

    // ------------------------------------------------------------ le rail

    aller(evenement) {
        const index = Number(evenement.params.bloc);
        const bloc = this.blocTargets.find((b) => Number(b.dataset.bloc) === index);

        if (!bloc) {
            return;
        }

        this.defilementTarget.scrollTop = Math.max(0, bloc.offsetTop - 12);
        this.marquerAncre(index);
    }

    /* Le dernier bloc passé sous les 120 px du haut prend la main. */
    suivreDefilement() {
        let courant = 0;

        this.blocTargets.forEach((bloc) => {
            if (bloc.offsetTop - this.defilementTarget.scrollTop <= 120) {
                courant = Number(bloc.dataset.bloc);
            }
        });

        this.marquerAncre(courant);
    }

    marquerAncre(index) {
        this.ancreTargets.forEach((ancre, rang) => {
            if (rang === index) {
                ancre.setAttribute('aria-current', 'true');
            } else {
                ancre.removeAttribute('aria-current');
            }
        });
    }

    // -------------------------------------------------------- classement

    basculerPuce(evenement) {
        const puce = evenement.currentTarget;
        this.inverser(puce);

        const groupe = puce.closest('div').parentElement;
        const puces = Array.from(groupe.querySelectorAll('[data-action*="basculerPuce"]'));
        const retenues = puces.filter((p) => this.coche(p)).length;
        const compte = groupe.querySelector('[data-creation-target="compteClasse"]');

        compte.textContent = retenues > 0
            ? retenues + ' sélectionné' + (retenues > 1 ? 's' : '')
            : 'aucune sélection';
    }

    basculerDepartement(evenement) {
        this.inverser(evenement.currentTarget);

        const retenus = this.element.querySelectorAll('[data-action*="basculerDepartement"][aria-checked="true"]').length;
        this.zoneCompteTarget.textContent = retenus + ' départements couverts';
    }

    // ------------------------------------------------- sites de diffusion

    basculerSite(evenement) {
        const site = evenement.currentTarget;

        if (site.dataset.verrouille) {
            return;
        }

        this.inverser(site);
        this.recompterSites();
    }

    /*
     * Le compte global, les puces de résumé et le décompte de chaque groupe
     * suivent les cases — comme dans la maquette.
     */
    recompterSites() {
        const groupes = Array.from(this.element.querySelectorAll('[data-creation-target="compteGroupe"]'))
            .map((c) => c.closest('div').parentElement);
        let retenus = [];
        let total = 0;

        groupes.forEach((groupe, rang) => {
            const sites = Array.from(groupe.querySelectorAll('[data-action*="basculerSite"]'));
            const coches = sites.filter((s) => this.coche(s));

            total += sites.length;
            retenus = retenus.concat(coches.map((s) => s.dataset.libelle));

            this.compteGroupeTargets[rang].textContent = coches.length + ' / ' + sites.length;
        });

        this.sitesCompteTarget.textContent = retenus.length + ' sites sur ' + total;

        const puces = retenus.slice(0, this.constructor.PUCES_SITES).map((libelle) => this.puce(libelle, true));

        if (retenus.length > this.constructor.PUCES_SITES) {
            puces.push(this.puce('+' + (retenus.length - this.constructor.PUCES_SITES), false));
        }

        this.sitesPucesTarget.replaceChildren(...puces);
    }

    puce(libelle, retenue) {
        const span = document.createElement('span');
        span.className = 'flex items-center justify-center gap-1 rounded-lg px-2 py-1 border '
            + (retenue ? 'bg-primary-3 border-primary-3' : 'bg-neutral-200 border-neutral-500');

        const texte = document.createElement('span');
        texte.className = 'font-black text-[0.75rem] leading-[1.25rem] '
            + (retenue ? 'text-neutral-100' : 'text-neutral-900');
        texte.textContent = libelle;
        span.appendChild(texte);

        return span;
    }

    // ------------------------------------------------------- interrupteurs

    basculerInterrupteur(evenement) {
        this.inverser(evenement.currentTarget);
    }

    /*
     * Le contact de repli verrouille les quatre champs et interdit l'envoi des
     * accès : le contact de repli n'est pas l'adresse du prestataire.
     */
    basculerRepli(evenement) {
        const bouton = evenement.currentTarget;
        const actif = !this.coche(bouton);

        this.inverser(bouton);
        this.contactTarget.dataset.repli = actif ? '1' : '0';
        this.envoyer(actif);
    }

    choisirAcces(evenement) {
        const option = evenement.currentTarget;

        if ('true' === option.getAttribute('aria-disabled')) {
            return;
        }

        const options = Array.from(this.element.querySelectorAll('[data-action*="choisirAcces"]'));
        options.forEach((autre) => autre.setAttribute('aria-checked', autre === option ? 'true' : 'false'));

        this.majTrame(option === options[0]);
    }

    /*
     * L'implantation permute l'adresse et la zone d'intervention, et la
     * bannière suit : sans adresse affichée, l'adresse n'est plus un champ
     * obligatoire manquant.
     */
    choisirImplantation(evenement) {
        const valeur = evenement.params.valeur;

        this.identiteTarget.dataset.implantation = valeur;
        this.majBanniere('zone' === valeur);
    }

    /*
     * Sans adresse affichée, l'adresse n'est plus un champ obligatoire
     * manquant : la maquette la retire de la bannière et redescend le compte.
     */
    majBanniere(zone) {
        if (!this.hasBanniereTarget) {
            return;
        }

        const adresse = this.banniereTarget.querySelector('[data-champ="adresse"]');

        if (adresse) {
            adresse.hidden = zone;
        }

        const restants = Array.from(this.banniereTarget.querySelectorAll('[data-champ], .flex-wrap > span'))
            .filter((champ) => !champ.hidden).length;

        this.banniereTarget.hidden = 0 === restants;
        this.banniereTitreTarget.textContent = restants + ' champ' + (restants > 1 ? 's' : '')
            + ' obligatoire' + (restants > 1 ? 's' : '') + ' à compléter';
    }

    basculerSuggestions() {
        if (this.hasSuggestionsTarget) {
            this.suggestionsTarget.hidden = !this.suggestionsTarget.hidden;
        }
    }

    /* Bascule l'option « envoyer » entre inerte et disponible. */
    envoyer(repli) {
        const options = Array.from(this.element.querySelectorAll('[data-action*="choisirAcces"]'));

        if (0 === options.length) {
            return;
        }

        const [envoyer, rien] = options;
        envoyer.classList.toggle('cursor-default', repli);
        envoyer.classList.toggle('cursor-pointer', !repli);

        if (repli) {
            envoyer.setAttribute('aria-disabled', 'true');
            envoyer.setAttribute('aria-checked', 'false');
            rien.setAttribute('aria-checked', 'true');
        } else {
            envoyer.removeAttribute('aria-disabled');
        }

        envoyer.querySelector('[data-aide]').textContent = repli
            ? "Indisponible : le contact de repli n'est pas l'adresse du prestataire."
            : 'Le prestataire reçoit ses identifiants du portail dès la création.';

        this.majTrame(!repli && this.coche(envoyer), repli);
    }

    /*
     * `repli` distingue les deux raisons de ne pas envoyer : le contact de repli
     * l'interdit, ou l'utilisateur a choisi « ne rien envoyer ».
     */
    majTrame(actif, repli = false) {
        const boite = this.trameTarget.parentElement;

        boite.classList.toggle('bg-neutral-200', !actif);
        boite.classList.toggle('bg-primary-5', actif);
        boite.classList.toggle('inset-ring-1', actif);
        boite.classList.toggle('inset-ring-primary', actif);

        this.trameTarget.textContent = actif ? 'Bienvenue — Lieux (FR)' : 'Aucun envoi';
        this.trameIndiceTarget.textContent = actif
            ? 'Cinq trames disponibles, une par gamme et par langue.'
            : (repli
                ? 'Envoi désactivé : le contact de repli ne reçoit pas les accès.'
                : 'La fiche rejoindra la file des accès non transmis.');
    }

    // ----------------------------------------------------------- outillage

    coche(element) {
        return 'true' === element.getAttribute('aria-checked');
    }

    inverser(element) {
        element.setAttribute('aria-checked', this.coche(element) ? 'false' : 'true');
    }
}
