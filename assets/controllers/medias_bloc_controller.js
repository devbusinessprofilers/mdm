/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus'

// Wrapper du bloc médias de l'éditeur de fiche : les actions médias ne
// rechargent plus la page, le bloc (galerie, compteurs, tuiles, modales) est
// re-rendu par le serveur et remplacé sur place — la saisie du formulaire
// principal de la fiche n'est jamais perdue.
export default class extends Controller {
    static values = { url: String }

    // Formulaires natifs des modales (documents, dépôt de documents du lieu) :
    // POST en fetch puis bloc rafraîchi. Les soumissions déjà traitées ailleurs
    // (métadonnées photo via lieu-media#save, annulation d'un confirm) arrivent
    // ici avec defaultPrevented — on les laisse passer.
    async intercepter(event) {
        const form = event.target
        if (event.defaultPrevented || !form.closest('[data-modal]')) return
        event.preventDefault()
        this.effacerErreur(form)
        try {
            const response = await window.fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
            if (!response.ok) {
                const result = await response.json().catch(() => ({}))
                this.montrerErreur(form, result.error || 'Une erreur est survenue.')
                return
            }
            this.rafraichir()
        } catch (_) {
            this.montrerErreur(form, 'Le serveur est injoignable — réessayez.')
        }
    }

    async rafraichir() {
        this.abort?.abort()
        this.abort = new AbortController()
        try {
            const response = await window.fetch(this.urlValue, { headers: { Accept: 'text/html' }, signal: this.abort.signal })
            if (!response.ok) throw new Error(String(response.status))
            // Le swap détruit la modale ouverte (fermeture voulue) et Stimulus
            // reconnecte lieu-media, qui re-précharge les modales.
            this.element.innerHTML = await response.text()
        } catch (error) {
            // L'action serveur a réussi : ne jamais laisser un affichage périmé.
            if (error.name !== 'AbortError') window.location.reload()
        }
    }

    montrerErreur(form, message) {
        form.insertAdjacentHTML('afterbegin', '<p class="form-error" data-medias-bloc-erreur></p>')
        form.querySelector('[data-medias-bloc-erreur]').textContent = message
    }

    effacerErreur(form) { form.querySelector('[data-medias-bloc-erreur]')?.remove() }
}
