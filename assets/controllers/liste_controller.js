import { Controller } from '@hotwired/stimulus';

/*
 * Liste des fiches.
 *
 * Les filtres passent par le serveur : lignes, badges et compte sortent d'un
 * seul prédicat, et les recalculer ici les ferait diverger. Le contrôleur ne
 * garde que ce qui est purement local — les panneaux, la sélection de lignes
 * et le repli du rail.
 *
 * Écart avec la maquette : la sélection vit dans les vraies cases
 * `selection[ids][]` du formulaire Symfony (cibles `case`), pas dans des
 * boutons aria-checked — c'est ce que le POST des actions groupées soumet.
 */
export default class extends Controller {
    static targets = ['panneau', 'replie', 'ouvert', 'picker', 'plus', 'vues',
        'bandeau', 'vueFiltres', 'vueActions', 'compteSelection', 'ligne',
        'caseTete', 'case', 'tout', 'tableau', 'densite'];

    connect() {
        this.surTouche = (evenement) => {
            if ('Escape' !== evenement.key) {
                return;
            }

            if (!this.vuesTarget.hidden) {
                this.vuesTarget.hidden = true;
            } else if (!this.pickerTarget.hidden) {
                this.pickerTarget.hidden = true;
            } else if (!this.plusTarget.hidden) {
                this.plusTarget.hidden = true;
            }
        };
        window.addEventListener('keydown', this.surTouche);

        // Un clic hors des menus les referme, comme les autres écrans.
        this.surClic = (evenement) => {
            if (!this.element.contains(evenement.target)) {
                return;
            }

            if (!this.pickerTarget.parentElement.contains(evenement.target)) {
                this.pickerTarget.hidden = true;
            }

            if (!this.plusTarget.parentElement.contains(evenement.target)) {
                this.plusTarget.hidden = true;
            }
        };
        document.addEventListener('click', this.surClic);

        // Cases restaurées par le navigateur (retour arrière) : resynchroniser.
        if (this.hasBandeauTarget) {
            this.caseTargets.forEach((c) => this.peindreLigne(c));
            this.majBandeau();
        }

        // La densité choisie survit à la navigation, sans toucher l'URL.
        if (this.hasTableauTarget) {
            const memorisee = window.localStorage.getItem('referentiel.densite');
            if (memorisee) {
                this.appliquerDensite(memorisee);
            }
        }
    }

    disconnect() {
        window.removeEventListener('keydown', this.surTouche);
        document.removeEventListener('click', this.surClic);
    }

    /* Le repli est porté par les largeurs Tailwind, pas par une classe d'écran. */
    basculerPanneau() {
        const replie = this.panneauTarget.classList.toggle('w-[60px]');

        this.panneauTarget.classList.toggle('w-[284px]', !replie);
        this.replieTarget.classList.toggle('flex', replie);
        this.replieTarget.classList.toggle('hidden', !replie);
        this.ouvertTarget.classList.toggle('flex', !replie);
        this.ouvertTarget.classList.toggle('hidden', replie);
    }

    basculerPicker(evenement) {
        const ouvert = this.pickerTarget.hidden;
        this.pickerTarget.hidden = !ouvert;
        this.plusTarget.hidden = true;
        evenement.currentTarget.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
    }

    basculerPlus(evenement) {
        const ouvert = this.plusTarget.hidden;
        this.plusTarget.hidden = !ouvert;
        this.pickerTarget.hidden = true;
        evenement.currentTarget.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
    }

    basculerVues() {
        this.vuesTarget.hidden = !this.vuesTarget.hidden;
        this.pickerTarget.hidden = true;
    }

    /* Densité Normale/Compacte : purement locale, diffusée par `data-densite`. */
    basculerDensite(evenement) {
        this.appliquerDensite(evenement.params.densite);
        window.localStorage.setItem('referentiel.densite', evenement.params.densite);
    }

    appliquerDensite(valeur) {
        this.tableauTarget.dataset.densite = valeur;
        this.densiteTargets.forEach((bouton) => {
            bouton.toggleAttribute('data-actif', bouton.dataset.listeDensiteParam === valeur);
        });
    }

    /* Copie l'URL courante — elle porte tout l'état du filtre (`f`). */
    copierLien(evenement) {
        const bouton = evenement.currentTarget;
        navigator.clipboard?.writeText(window.location.href).then(() => {
            bouton.setAttribute('title', 'Lien copié');
        });
    }

    /* La case d'une ligne a changé (vrai input soumis au POST). */
    basculerLigne(evenement) {
        this.peindreLigne(evenement.currentTarget);
        this.majBandeau();
    }

    /* Case de tête : coche tout, ou décoche tout si tout l'était déjà. */
    basculerTout() {
        const cocher = !this.caseTargets.every((c) => c.checked);

        this.caseTargets.forEach((c) => {
            c.checked = cocher;
            this.peindreLigne(c);
        });
        this.majBandeau();
    }

    peindreLigne(caseLigne) {
        caseLigne.closest('[data-liste-target="ligne"]')
            ?.classList.toggle('bg-primary-4', caseLigne.checked);
    }

    /* Le bandeau passe en mode sélection dès qu'une ligne est cochée. */
    majBandeau() {
        const cochees = this.caseTargets.filter((c) => c.checked).length;
        const toutLeFiltre = this.hasToutTarget && this.toutTarget.checked;
        const enSelection = cochees > 0 || toutLeFiltre;

        if (this.hasCaseTeteTarget) {
            this.caseTeteTarget.setAttribute('aria-checked',
                0 === cochees ? 'false'
                    : (cochees === this.caseTargets.length ? 'true' : 'mixed'));
        }

        this.bandeauTarget.classList.toggle('bg-primary-4', enSelection);
        this.bandeauTarget.classList.toggle('inset-ring-primary', enSelection);
        this.bandeauTarget.classList.toggle('bg-neutral-100', !enSelection);
        this.bandeauTarget.classList.toggle('inset-ring-neutral-200', !enSelection);

        this.vueFiltresTarget.classList.toggle('hidden', enSelection);
        this.vueFiltresTarget.classList.toggle('flex', !enSelection);
        this.vueActionsTarget.classList.toggle('flex', enSelection);
        this.vueActionsTarget.classList.toggle('hidden', !enSelection);

        if (!enSelection) {
            return;
        }

        /*
         * Le total vient d'un attribut, pas d'un découpage du libellé : les
         * milliers y sont séparés par une espace fine, « 15 906 » se serait
         * lu « 15 ».
         */
        const totalAffiche = this.bandeauTarget.dataset.total || '0';
        const total = parseInt(totalAffiche.replace(/\s/g, ''), 10) || 0;
        const effectif = toutLeFiltre ? total : cochees;
        this.compteSelectionTarget.textContent = toutLeFiltre
            ? 'Tout le filtre — ' + totalAffiche + ' fiches'
            : cochees + ' sélectionnée' + (cochees > 1 ? 's' : '') + ' sur ' + totalAffiche;

        /*
         * Passé son plafond, une action se désactive et son étiquette
         * « Plafond N » apparaît — une grande sélection n'est pas une petite
         * en plus gros.
         */
        this.element.querySelectorAll('[data-plafond]').forEach((bouton) => {
            const depasse = effectif > parseInt(bouton.dataset.plafond, 10);
            bouton.disabled = depasse;
            const etiquette = bouton.closest('[data-ligne-action]')?.querySelector('[data-plafond-tag]');
            if (etiquette) {
                etiquette.hidden = !depasse;
            }
        });
    }
}
