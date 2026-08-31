/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['input', 'dropzone', 'progress', 'progressBar', 'error', 'list', 'item']
    static values = { uploadUrl: String, orderUrl: String, modalesUrl: String, token: String }

    // Les modales de paramètres des médias ne sont plus rendues dans la page :
    // elles sont préchargées ici, en arrière-plan, juste après le chargement.
    // Le clic sur une vignette (modal-trigger-button) les trouve déjà dans le DOM.
    connect() { if (this.modalesUrlValue) this.chargerModales() }

    async chargerModales() {
        try {
            const response = await window.fetch(this.modalesUrlValue, { headers: { Accept: 'text/html' } })
            if (!response.ok) throw new Error(String(response.status))
            this.element.insertAdjacentHTML('beforeend', await response.text())
        } catch (_) {
            this.showError('Le chargement des paramètres des médias a échoué — rechargez la page pour les modifier.')
        }
    }

    upload(event) { this.uploadFiles(Array.from(event.target.files || [])) }
    // La carte entière est la zone de dépôt : on ne réagit qu'aux fichiers de
    // l'OS (types contient « Files »), pas au réordonnancement des vignettes.
    isFileDrag(event) { return Array.from(event.dataTransfer?.types || []).includes('Files') }
    dragover(event) { if (!this.isFileDrag(event)) return; event.preventDefault(); this.dropzoneTarget.classList.add('is-dragging') }
    dragleave(event) {
        // Passer d'un enfant de la carte à un autre déclenche dragleave : on ne
        // retire le voile qu'en quittant réellement la carte.
        if (event.relatedTarget && this.element.contains(event.relatedTarget)) return
        this.dropzoneTarget.classList.remove('is-dragging')
    }
    drop(event) { if (!this.isFileDrag(event)) return; event.preventDefault(); this.dropzoneTarget.classList.remove('is-dragging'); this.uploadFiles(Array.from(event.dataTransfer.files || [])) }

    uploadFiles(files) {
        if (!files.length) return
        const body = new FormData()
        files.forEach(file => body.append('photos[]', file))
        this.requestWithProgress(this.uploadUrlValue, body)
    }

    save(event) {
        event.preventDefault()
        const formData = new FormData(event.currentTarget)
        const data = {}
        for (const [key, value] of formData.entries()) {
            const match = key.match(/\[([^\]]+)]$/)
            data[match ? match[1] : key] = value
        }
        this.fetch(event.currentTarget.dataset.url, { method: 'PATCH', body: JSON.stringify(data), headers: { 'Content-Type': 'application/json' } })
    }

    replace(event) {
        const file = event.target.files[0]
        if (!file) return
        const body = new FormData(); body.append('photo', file)
        this.requestWithProgress(event.target.dataset.url, body)
    }

    remove(event) {
        if (window.confirm('Supprimer définitivement cette photo ?')) this.fetch(event.currentTarget.dataset.url, { method: 'DELETE' })
    }
    retry(event) { this.fetch(event.currentTarget.dataset.url, { method: 'POST' }) }

    // Select de catégorie sous la vignette : la catégorie seule est modifiée
    // (endpoint dédié — le PATCH complet effacerait légende, source, crop…).
    changerCategorie(event) {
        this.fetch(event.currentTarget.dataset.url, { method: 'PATCH', body: JSON.stringify({ usage: event.currentTarget.value }), headers: { 'Content-Type': 'application/json' } })
    }

    // Barre de salle posée sur une photo « Salle de réunion » : la salle
    // rattachée change, la catégorie courante est conservée par le serveur.
    changerSalle(event) {
        this.fetch(event.currentTarget.dataset.url, { method: 'PATCH', body: JSON.stringify({ salle_id: event.currentTarget.value }), headers: { 'Content-Type': 'application/json' } })
    }

    // Lance la reconnaissance IA de la photo. Le retour se fait dans le bouton
    // lui-même : la modale recouvre la zone d'erreur de la carte, et les
    // suggestions n'arrivent que plus tard dans Médias › Reconnaissance IA.
    async enrich(event) {
        const button = event.currentTarget
        const label = button.querySelector('span') || button
        button.disabled = true
        const response = await window.fetch(button.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.tokenValue } }).catch(() => null)
        const result = response ? await response.json().catch(() => ({})) : {}
        if (!response || !response.ok) {
            label.textContent = result.error || 'Une erreur est survenue.'
            button.disabled = false
            return
        }
        label.textContent = result.queued
            ? 'Reconnaissance lancée — suggestions à venir dans Médias › Reconnaissance IA'
            : 'Une reconnaissance est déjà en cours pour cette photo'
    }

    moveUp(event) { const item = event.currentTarget.closest('[data-lieu-media-target="item"]'); item.previousElementSibling?.before(item); this.saveOrder() }
    moveDown(event) { const item = event.currentTarget.closest('[data-lieu-media-target="item"]'); item.nextElementSibling?.after(item); this.saveOrder() }
    dragstart(event) { this.dragged = event.currentTarget }
    dragoverItem(event) { event.preventDefault() }
    // Sans vignette en cours de glissement (dépôt de fichiers sur une tuile),
    // on laisse l'événement remonter au drop de la carte.
    dropItem(event) { if (!this.dragged) return; event.preventDefault(); if (this.dragged !== event.currentTarget) event.currentTarget.before(this.dragged); this.dragged = null; this.saveOrder() }

    saveOrder() {
        if (!this.hasListTarget) return
        const ids = Array.from(this.listTarget.querySelectorAll('[data-id]')).map(item => item.dataset.id)
        // Le bloc est re-rendu après l'enregistrement : numéros, badge
        // « Photo principale » et selects reviennent du serveur, à jour.
        this.fetch(this.orderUrlValue, { method: 'POST', body: JSON.stringify({ ids }), headers: { 'Content-Type': 'application/json' } })
    }

    async fetch(url, options = {}) {
        this.showError('')
        const headers = { ...(options.headers || {}), 'X-CSRF-TOKEN': this.tokenValue }
        const response = await window.fetch(url, { ...options, headers })
        if (!response.ok) { const result = await response.json().catch(() => ({})); this.showError(result.error || 'Une erreur est survenue.'); return }
        // Plus de rechargement : le wrapper medias-bloc écoute cet événement
        // et re-rend le bloc seul — la saisie du formulaire principal survit.
        if (options.reload !== false) this.dispatch('updated')
    }

    requestWithProgress(url, body) {
        this.showError(''); this.progressTarget.hidden = false
        const xhr = new XMLHttpRequest(); xhr.open('POST', url); xhr.setRequestHeader('X-CSRF-TOKEN', this.tokenValue)
        xhr.upload.onprogress = event => { if (event.lengthComputable) this.progressBarTarget.style.width = `${Math.round(event.loaded / event.total * 100)}%` }
        xhr.onload = () => { if (xhr.status >= 200 && xhr.status < 300) this.dispatch('updated'); else { let result = {}; try { result = JSON.parse(xhr.responseText) } catch (_) {} this.showError(result.error || 'Le téléversement a échoué.'); this.progressTarget.hidden = true } }
        xhr.onerror = () => { this.showError('Le stockage des médias est indisponible.'); this.progressTarget.hidden = true }
        xhr.send(body)
    }

    showError(message) { this.errorTarget.textContent = message; this.errorTarget.hidden = !message }
}
