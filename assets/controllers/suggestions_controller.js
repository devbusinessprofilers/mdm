import { Controller } from '@hotwired/stimulus';

/*
 * Tableau de suggestions de l'écran Qualité : bascule d'onglet côté client (sans
 * reload), sélection groupée par onglet, et avertissement avant de quitter un
 * onglet où des cases sont cochées (Annuler / Continuer, Continuer décoche tout).
 * Les vraies cases `suggestion_selection[ids][]` restent le champ soumis.
 */
export default class extends Controller {
    static targets = ['tab', 'panel', 'avert', 'accepter', 'ignorer'];

    connect() {
        this.enAttente = null;
        const actif = this.panneauActif;
        this.majActions(actif ? actif.dataset.cle : null);
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
        // Chaque onglet a deux variantes (outline / primary) : on montre la
        // primary de l'onglet actif et l'outline des autres.
        this.tabTargets.forEach((t) => {
            const actif = t.dataset.suggestionsCleParam === cle;
            const outline = t.querySelector('[data-role="tab-outline"]');
            const primary = t.querySelector('[data-role="tab-primary"]');
            if (outline) { outline.classList.toggle('hidden', actif); }
            if (primary) { primary.classList.toggle('hidden', !actif); }
        });
        this.majActions(cle);
    }

    /* Les boutons Accepter/Ignorer partagés pointent vers l'endpoint de l'onglet actif. */
    majActions(cle) {
        if (!cle || !this.hasAccepterTarget) {
            return;
        }
        const tab = this.tabTargets.find((t) => t.dataset.suggestionsCleParam === cle);
        if (!tab) {
            return;
        }
        if (tab.dataset.accepter) { this.accepterTarget.formAction = tab.dataset.accepter; }
        if (this.hasIgnorerTarget && tab.dataset.ignorer) { this.ignorerTarget.formAction = tab.dataset.ignorer; }
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
