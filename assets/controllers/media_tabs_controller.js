/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus'

// Onglets internes du volet Médias (Photos, Plans, Supports commerciaux,
// Vidéo, Documents). Le shell vit HORS du wrapper medias-bloc : quand le bloc
// est re-rendu en AJAX, panelTargetConnected ré-applique l'onglet actif aux
// panneaux reconstruits — l'onglet choisi survit aux rafraîchissements.
// Plusieurs panneaux peuvent partager le même data-onglet (zone AJAX + zone
// statique du même onglet).
export default class extends Controller {
    static targets = ['onglet', 'panel']

    initialize() { this.actif = 'photos' }

    activer(event) {
        const bouton = event.currentTarget
        if (bouton.disabled) return
        this.actif = event.params.onglet
        this.panelTargets.forEach(panel => { panel.hidden = panel.dataset.onglet !== this.actif })
        this.ongletTargets.forEach(onglet => onglet.setAttribute('aria-selected', String(onglet === bouton)))
    }

    panelTargetConnected(panel) { panel.hidden = panel.dataset.onglet !== this.actif }
}
