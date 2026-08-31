import { Controller } from '@hotwired/stimulus';

/*
 * Tunnel de création de fiche (gabarit maquette « Creation fiche »).
 *
 * La gamme choisie débloque les six autres blocs et détermine les sections
 * affichées (chaque section liste ses gammes dans data-types) ; les champs
 * d'une section masquée sont réinitialisés pour rester cohérents avec la
 * neutralisation côté serveur. Le rail suit le défilement et ses badges
 * reflètent le remplissage ; les compteurs de classification et de sites
 * suivent les vraies cases.
 */
export default class extends Controller {
    static targets = [
        'type', 'section', 'voile', 'bloc', 'ancre', 'badge', 'defilement',
        'verrou', 'corps', 'groupeClasse', 'sitesCompte', 'sitesPuces',
        'groupeSites', 'site', 'barreNote',
    ];

    static PUCES_SITES = 6;

    connect() {
        this.surDefilement = () => this.suivreDefilement();
        if (this.hasDefilementTarget) {
            this.defilementTarget.addEventListener('scroll', this.surDefilement);
        }
        this.refresh();
        this.recompterSites();
    }

    disconnect() {
        if (this.hasDefilementTarget) {
            this.defilementTarget.removeEventListener('scroll', this.surDefilement);
        }
    }

    /* Voile de la maquette : posé au départ du POST de création. */
    patienter() {
        if (this.hasVoileTarget) {
            this.voileTarget.hidden = false;
        }
    }

    gamme() {
        const radio = this.hasTypeTarget ? this.typeTarget.querySelector('input:checked') : null;

        return radio ? radio.value : '';
    }

    refresh() {
        const type = this.gamme();
        const choisie = '' !== type;

        for (const section of this.sectionTargets) {
            const visible = (section.dataset.types ?? '').split(' ').includes(type);
            section.hidden = !visible;
            if (!visible) {
                this.reset(section);
            }
        }

        // La gamme débloque les blocs 2 à 7 (verrouillage visuel : les champs
        // restent de vrais inputs, tous facultatifs tant que rien n'est saisi).
        this.blocTargets.forEach((bloc) => {
            if ('0' !== bloc.dataset.bloc) {
                bloc.classList.toggle('opacity-72', !choisie);
            }
        });
        this.verrouTargets.forEach((verrou) => { verrou.hidden = choisie; });
        this.corpsTargets.forEach((corps) => { corps.hidden = !choisie; });

        const creer = this.element.querySelector('#fiche_creation_submit');
        if (creer) {
            creer.disabled = !choisie;
        }

        if (this.hasBarreNoteTarget && !this.barreNoteTarget.dataset.erreurs) {
            this.barreNoteTarget.textContent = choisie
                ? "Prêt à créer. L'enrichissement se poursuit sur la fiche complète."
                : 'Choisissez une gamme pour activer la création.';
        }

        this.recompterClasse();
    }

    reset(section) {
        for (const field of section.querySelectorAll('select')) {
            for (const option of field.options) {
                option.selected = false;
            }
        }
        for (const field of section.querySelectorAll('input[type="checkbox"], input[type="radio"]')) {
            field.checked = false;
        }
    }

    // ------------------------------------------------------------ le rail

    aller(evenement) {
        const index = Number(evenement.params.bloc);
        const bloc = this.blocTargets.find((b) => Number(b.dataset.bloc) === index);

        if (!bloc || !this.hasDefilementTarget) {
            return;
        }

        this.defilementTarget.scrollTop = Math.max(0, this.hautBloc(bloc) - 12);
        this.marquerAncre(index);
    }

    /* Position du bloc dans la zone défilante (le main positionné fausse offsetTop). */
    hautBloc(bloc) {
        return bloc.offsetTop - this.defilementTarget.offsetTop;
    }

    /* Le dernier bloc passé sous les 120 px du haut prend la main. */
    suivreDefilement() {
        let courant = 0;

        this.blocTargets.forEach((bloc) => {
            if (this.hautBloc(bloc) - this.defilementTarget.scrollTop <= 120) {
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

    /*
     * Badges du rail : « OK » bloc renseigné, « à faire » sinon, « — » tant
     * que la gamme n'est pas choisie. Les « ! » posés par le serveur après une
     * soumission en erreur s'effacent dès que l'utilisateur retouche la page.
     */
    majRail() {
        const choisie = '' !== this.gamme();
        const rempli = (id) => {
            const champ = this.element.querySelector(id);

            return Boolean(champ) && '' !== champ.value.trim();
        };
        const repli = this.element.querySelector('#fiche_creation_contactRepli');
        const envoyer = this.element.querySelector('#fiche_creation_envoyerAcces');
        const remplis = [
            choisie,
            rempli('#fiche_creation_label'),
            this.groupeClasseTargets.some((groupe) => !groupe.closest('[hidden]') && groupe.querySelector('input:checked')),
            true,
            this.siteTargets.some((site) => site.querySelector('input').checked),
            Boolean(repli && repli.checked) || rempli('#fiche_creation_collabEmail') || rempli('#fiche_creation_collaborateurExistant'),
            // L'envoi des accès est incompatible avec le contact de repli.
            !(envoyer && envoyer.checked && repli && repli.checked),
        ];

        this.badgeTargets.forEach((badge, index) => {
            const verrouille = index > 0 && !choisie;
            const ok = !verrouille && remplis[index];

            // Un « ! » posé par le serveur tient tant que le bloc n'est pas
            // renseigné : il ne s'efface qu'une fois l'erreur corrigée.
            if (ok) {
                badge.dataset.fautif = '';
            }
            const fautif = Boolean(badge.dataset.fautif);

            badge.textContent = fautif ? '!' : (verrouille ? '—' : (ok ? 'OK' : 'à faire'));
            badge.classList.toggle('bg-error-pastel', fautif);
            badge.classList.toggle('text-error', fautif);
            badge.classList.toggle('bg-success-pastel', !fautif && ok);
            badge.classList.toggle('text-success', !fautif && ok);
            badge.classList.toggle('bg-neutral-200', !fautif && !ok);
            badge.classList.toggle('text-neutral-400', !fautif && !ok);
        });
    }

    // -------------------------------------------------------- classement

    recompterClasse() {
        this.groupeClasseTargets.forEach((groupe) => {
            const retenues = groupe.querySelectorAll('input:checked').length;
            const compte = groupe.querySelector('[data-fiche-creation-target="compteClasse"]');

            if (compte) {
                compte.textContent = retenues > 0
                    ? retenues + ' sélectionné' + (retenues > 1 ? 's' : '')
                    : 'aucune sélection';
            }
        });

        this.majRail();
    }

    // ------------------------------------------------- sites de diffusion

    /*
     * Le compte global, les puces de résumé et le décompte de chaque groupe
     * suivent les cases — comme dans la maquette.
     */
    recompterSites() {
        let retenus = [];
        let total = 0;

        this.groupeSitesTargets.forEach((groupe) => {
            const sites = Array.from(groupe.querySelectorAll('[data-fiche-creation-target="site"]'));
            const coches = sites.filter((site) => site.querySelector('input').checked);

            total += sites.length;
            retenus = retenus.concat(coches.map((site) => site.dataset.libelle));

            const compte = groupe.querySelector('[data-fiche-creation-target="compteGroupe"]');
            if (compte) {
                compte.textContent = coches.length + ' / ' + sites.length;
            }
        });

        if (this.hasSitesCompteTarget) {
            this.sitesCompteTarget.textContent = retenus.length + ' sites sur ' + total;
        }

        if (this.hasSitesPucesTarget) {
            const puces = retenus.slice(0, this.constructor.PUCES_SITES).map((libelle) => this.puce(libelle, true));

            if (retenus.length > this.constructor.PUCES_SITES) {
                puces.push(this.puce('+' + (retenus.length - this.constructor.PUCES_SITES), false));
            }

            this.sitesPucesTarget.replaceChildren(...puces);
        }

        this.majRail();
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

    filtrerSites(evenement) {
        const recherche = evenement.target.value.trim().toLowerCase();

        this.siteTargets.forEach((site) => {
            site.hidden = '' !== recherche && !site.dataset.libelle.toLowerCase().includes(recherche);
        });
        this.groupeSitesTargets.forEach((groupe) => {
            groupe.hidden = !Array.from(groupe.querySelectorAll('[data-fiche-creation-target="site"]'))
                .some((site) => !site.hidden);
        });
    }
}
