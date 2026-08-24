import { Controller } from '@hotwired/stimulus';

/*
 * Tableau de suggestions de l'écran Qualité : bascule d'onglet côté client (sans
 * reload), sélection groupée par onglet, et avertissement avant de quitter un
 * onglet où des cases sont cochées (Annuler / Continuer, Continuer décoche tout).
 * Les vraies cases `suggestion_selection[ids][]` restent le champ soumis.
 */
export default class extends Controller {
    static targets = ['tab', 'panel', 'avert'];

    connect() {
        this.enAttente = null;
    }

    get panneauActif() {
        return this.panelTargets.find((p) => !p.classList.contains('hidden')) ?? null;
    }

    activer(event) {
        const cle = event.params.cle;
        const actif = this.panneauActif;
        if (actif && actif.dataset.cle === cle) {
            return;
        }
        if (actif && this.casesCochees(actif).length > 0) {
            this.enAttente = cle;
            this.avertTarget.classList.remove('hidden');

            return;
        }
        this.basculer(cle);
    }

    annuler() {
        this.avertTarget.classList.add('hidden');
        this.enAttente = null;
    }

    continuer() {
        const actif = this.panneauActif;
        if (actif) {
            this.cases(actif).forEach((c) => { c.checked = false; });
            this.majTete(actif);
        }
        this.avertTarget.classList.add('hidden');
        if (this.enAttente) {
            this.basculer(this.enAttente);
        }
        this.enAttente = null;
    }

    basculer(cle) {
        this.panelTargets.forEach((p) => p.classList.toggle('hidden', p.dataset.cle !== cle));
        this.tabTargets.forEach((t) => t.setAttribute(
            'aria-selected',
            t.dataset.suggestionsCleParam === cle ? 'true' : 'false',
        ));
    }

    basculerTout(event) {
        const panel = event.currentTarget.closest('[data-suggestions-target="panel"]');
        if (!panel) {
            return;
        }
        const cases = this.cases(panel);
        const cocher = !cases.every((c) => c.checked);
        cases.forEach((c) => { c.checked = cocher; });
        this.majTete(panel);
    }

    surCase(event) {
        const panel = event.target.closest('[data-suggestions-target="panel"]');
        if (panel) {
            this.majTete(panel);
        }
    }

    cases(panel) {
        return Array.from(panel.querySelectorAll('input[type="checkbox"][data-role="case"]'));
    }

    casesCochees(panel) {
        return this.cases(panel).filter((c) => c.checked);
    }

    majTete(panel) {
        const tete = panel.querySelector('[data-role="caseTete"]');
        if (!tete) {
            return;
        }
        const cases = this.cases(panel);
        const cochees = cases.filter((c) => c.checked).length;
        tete.setAttribute(
            'aria-checked',
            cochees === 0 ? 'false' : (cochees === cases.length ? 'true' : 'mixed'),
        );
    }
}
