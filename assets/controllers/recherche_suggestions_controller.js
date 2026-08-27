/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus'

// Autocomplétion des champs de recherche (navbar et référentiel) : propose des
// noms de fiches pendant la frappe (corrections orthographiques comprises,
// côté serveur). Choisir une suggestion remplit le champ puis soumet le
// formulaire — celui qui contient le champ, ou celui que son attribut `form`
// désigne (champ de la barre d'outils rattaché au formulaire de filtres).
export default class extends Controller {
    static targets = ['champ', 'liste']
    static values = {
        url: String,
        // 3 lettres : en deçà, la requête n'est pas discriminante (aligné sur
        // la taille minimale d'indexation fulltext côté serveur).
        min: { type: Number, default: 3 },
        // 400 ms : assez long pour absorber une frappe lente sans paraître
        // paresseux. Chaque frappe annule de toute façon le fetch en cours.
        delai: { type: Number, default: 400 },
    }

    connect() {
        this.fermerSiExterieur = this.fermerSiExterieur.bind(this)
        this.indexActif = -1
    }

    disconnect() {
        this.fermer()
        window.clearTimeout(this.minuterie)
        this.controleurFetch?.abort()
    }

    saisir() {
        window.clearTimeout(this.minuterie)
        const q = this.champTarget.value.trim()
        if (q.length < this.minValue) {
            this.fermer()
            return
        }
        this.minuterie = window.setTimeout(() => this.charger(q), this.delaiValue)
    }

    async charger(q) {
        this.controleurFetch?.abort()
        this.controleurFetch = new AbortController()
        try {
            const response = await window.fetch(`${this.urlValue}?q=${encodeURIComponent(q)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: this.controleurFetch.signal,
            })
            if (!response.ok) {
                this.fermer()
                return
            }
            const { suggestions } = await response.json()
            this.afficher(suggestions ?? [])
        } catch (_) {
            // Requête annulée par la frappe suivante, ou réseau : on se tait.
        }
    }

    afficher(suggestions) {
        this.listeTarget.replaceChildren()
        this.indexActif = -1
        if (suggestions.length === 0) {
            this.fermer()
            return
        }
        for (const label of suggestions) {
            const option = document.createElement('button')
            option.type = 'button'
            option.setAttribute('role', 'option')
            option.textContent = label
            option.className = 'w-full px-3 py-2 rounded-xs text-left text-sm cursor-pointer truncate hover:bg-primary-4'
            // mousedown : le clic doit gagner contre le blur du champ.
            option.dataset.action = 'mousedown->recherche-suggestions#choisir'
            this.listeTarget.append(option)
        }
        this.listeTarget.hidden = false
        this.champTarget.setAttribute('aria-expanded', 'true')
        document.addEventListener('mousedown', this.fermerSiExterieur)
    }

    choisir(event) {
        event.preventDefault()
        this.champTarget.value = event.currentTarget.textContent
        this.fermer()
        this.champTarget.form?.requestSubmit()
    }

    clavier(event) {
        if (this.listeTarget.hidden) return
        const options = [...this.listeTarget.children]
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault()
            const pas = event.key === 'ArrowDown' ? 1 : -1
            this.activer((this.indexActif + pas + options.length) % options.length, options)
        } else if (event.key === 'Enter' && this.indexActif >= 0) {
            event.preventDefault()
            this.champTarget.value = options[this.indexActif].textContent
            this.fermer()
            this.champTarget.form?.requestSubmit()
        } else if (event.key === 'Escape') {
            this.fermer()
        }
    }

    activer(index, options) {
        options[this.indexActif]?.classList.remove('bg-primary-4')
        this.indexActif = index
        options[index].classList.add('bg-primary-4')
        options[index].scrollIntoView({ block: 'nearest' })
    }

    fermer() {
        this.listeTarget.hidden = true
        this.listeTarget.replaceChildren()
        this.indexActif = -1
        this.champTarget.setAttribute('aria-expanded', 'false')
        document.removeEventListener('mousedown', this.fermerSiExterieur)
    }

    fermerSiExterieur(event) {
        if (!this.element.contains(event.target)) {
            this.fermer()
        }
    }
}
