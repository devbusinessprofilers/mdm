/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus'

// Ligne de critère géographique d'un site de diffusion : montre les champs du
// type choisi (ville + rayon, département, région, pays) et propose les villes
// via le géocodeur (Geoapify, niveau ville). Choisir une suggestion recopie le
// libellé et remplit les coordonnées cachées ; toute frappe les invalide — la
// validation serveur refuse une ville non choisie dans la liste.
export default class extends Controller {
    static targets = ['type', 'sectionVille', 'sectionDepartement', 'sectionRegion', 'sectionPays', 'champ', 'liste', 'pays', 'latitude', 'longitude']
    static values = {
        url: String,
        min: { type: Number, default: 2 },
        // 400 ms : mêmes réglages que les autres autocomplétions du portail.
        delai: { type: Number, default: 400 },
    }

    connect() {
        this.fermerSiExterieur = this.fermerSiExterieur.bind(this)
        this.indexActif = -1
        this.changerType()
    }

    disconnect() {
        this.fermer()
        window.clearTimeout(this.minuterie)
        this.controleurFetch?.abort()
    }

    changerType() {
        const type = this.typeTarget.value
        this.sectionVilleTarget.hidden = type !== 'ville'
        this.sectionDepartementTarget.hidden = type !== 'departement'
        this.sectionRegionTarget.hidden = type !== 'region'
        this.sectionPaysTarget.hidden = type !== 'pays'
        this.fermer()
    }

    saisir() {
        // Le libellé ne vaut plus rien sans son géocodage : coordonnées vidées.
        this.latitudeTarget.value = ''
        this.longitudeTarget.value = ''
        window.clearTimeout(this.minuterie)
        if (this.champTarget.value.trim().length < this.minValue) {
            this.fermer()
            return
        }
        this.minuterie = window.setTimeout(() => this.charger(), this.delaiValue)
    }

    changerPays() {
        this.latitudeTarget.value = ''
        this.longitudeTarget.value = ''
        this.fermer()
    }

    async charger() {
        this.controleurFetch?.abort()
        this.controleurFetch = new AbortController()
        const pays = this.paysTarget.value
        if (!pays) {
            this.fermer()
            return
        }
        const params = new URLSearchParams({ q: this.champTarget.value.trim(), pays })
        try {
            const response = await window.fetch(`${this.urlValue}?${params}`, {
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
            // Un silence ressemble à une panne : afficher l'absence de résultat.
            const vide = document.createElement('div')
            vide.textContent = 'Aucune ville trouvée : vérifiez l\'orthographe ou le pays.'
            vide.className = 'px-3 py-2 text-sm text-neutral-500'
            this.listeTarget.append(vide)
            this.ouvrir()
            return
        }
        for (const suggestion of suggestions) {
            const option = document.createElement('button')
            option.type = 'button'
            option.setAttribute('role', 'option')
            option.dataset.suggestion = JSON.stringify(suggestion)
            option.className = 'w-full px-3 py-2 rounded-xs text-left text-sm cursor-pointer hover:bg-primary-4'
            // mousedown : le clic doit gagner contre le blur du champ.
            option.dataset.action = 'mousedown->critere-geo#choisir'
            option.textContent = suggestion.label
            this.listeTarget.append(option)
        }
        this.ouvrir()
    }

    choisir(event) {
        event.preventDefault()
        this.retenir(event.currentTarget)
    }

    retenir(option) {
        const suggestion = JSON.parse(option.dataset.suggestion)
        this.champTarget.value = suggestion.label
        this.latitudeTarget.value = suggestion.latitude
        this.longitudeTarget.value = suggestion.longitude
        this.fermer()
    }

    clavier(event) {
        if (this.listeTarget.hidden) return
        if (event.key === 'Escape') {
            this.fermer()
            return
        }
        // La ligne « aucune ville » n'est pas une option navigable.
        const options = [...this.listeTarget.querySelectorAll('button')]
        if (options.length === 0) return
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault()
            const pas = event.key === 'ArrowDown' ? 1 : -1
            this.activer((this.indexActif + pas + options.length) % options.length, options)
        } else if (event.key === 'Enter' && this.indexActif >= 0) {
            event.preventDefault()
            this.retenir(options[this.indexActif])
        }
    }

    activer(index, options) {
        options[this.indexActif]?.classList.remove('bg-primary-4')
        this.indexActif = index
        options[index].classList.add('bg-primary-4')
        options[index].scrollIntoView({ block: 'nearest' })
    }

    ouvrir() {
        this.listeTarget.hidden = false
        this.champTarget.setAttribute('aria-expanded', 'true')
        document.addEventListener('mousedown', this.fermerSiExterieur)
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
