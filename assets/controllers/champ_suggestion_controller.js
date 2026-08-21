/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus'

// Pilule « Suggérer » d'un champ de description : envoie au serveur le champ, son
// contenu actuel et la gamme+id de la fiche (lues sur le formulaire), reçoit une
// proposition IA et remplace le contenu de l'éditeur. Le remplacement reste
// annulable (Ctrl+Z de TinyMCE, ou saisie sur la zone de texte simple).
export default class extends Controller {
    static targets = ['bouton', 'libelle', 'statut']
    static values = { champ: String, fieldId: String }

    connect() {
        // IA débranchée : la pilule maquette reste visible mais grisée (les
        // classes disabled:* du bouton s'en chargent), le back renverrait 404.
        if (this.form?.dataset.iaSuggestionActive !== '1') {
            this.boutonTarget.disabled = true
            this.boutonTarget.title = 'Suggestion IA désactivée'
        }
    }

    async suggerer() {
        const form = this.form
        if (!form) return
        this.effacerErreur()
        this.basculer(true)
        try {
            const response = await window.fetch(form.dataset.ficheSuggererUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': form.dataset.ficheSuggererCsrf,
                },
                body: JSON.stringify({
                    gamme: form.dataset.ficheGamme,
                    id: form.dataset.ficheId,
                    champ: this.champValue,
                    valeur: this.contenuActuel(),
                }),
            })
            if (!response.ok) {
                const data = await response.json().catch(() => ({}))
                this.montrerErreur(data.error || 'La suggestion a échoué.')
                return
            }
            const { suggestion } = await response.json()
            if (suggestion) this.remplacer(suggestion)
        } catch (_) {
            this.montrerErreur('Le serveur est injoignable — réessayez.')
        } finally {
            this.basculer(false)
        }
    }

    // TinyMCE si le champ est un Wysiwyg, sinon la zone de texte simple.
    editeur() {
        return window.tinymce?.get(this.fieldIdValue) ?? null
    }

    contenuActuel() {
        const editeur = this.editeur()
        if (editeur) return editeur.getContent()
        return document.getElementById(this.fieldIdValue)?.value ?? ''
    }

    remplacer(texte) {
        const editeur = this.editeur()
        if (editeur) {
            // Passe par le format de contenu texte : l'API échappe et gère
            // l'entrée dans la pile d'annulation (Ctrl+Z restaure).
            editeur.setContent(editeur.dom.encode(texte).replace(/\n/g, '<br>'))
            editeur.save()
            return
        }
        const zone = document.getElementById(this.fieldIdValue)
        if (zone) zone.value = texte
    }

    basculer(occupe) {
        this.boutonTarget.disabled = occupe
        this.libelleTarget.textContent = occupe ? 'Suggestion…' : 'Suggérer'
    }

    montrerErreur(message) {
        this.statutTarget.textContent = message
        this.statutTarget.classList.remove('hidden')
    }

    effacerErreur() {
        this.statutTarget.textContent = ''
        this.statutTarget.classList.add('hidden')
    }

    get form() {
        return this.element.closest('form')
    }
}
