/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus'

// Recherche d'adresse du tunnel de création (Geoapify) : la requête combine le
// nom de la fiche et le texte saisi, bornée au pays du petit sélecteur.
// Choisir une suggestion la retient ; « Appliquer le choix » recopie ses
// composantes dans les champs d'adresse structurés — seuls ces champs sont
// soumis, la ligne de recherche reste hors formulaire.
export default class extends Controller {
    static targets = ['champ', 'liste', 'pays', 'etat']
    static values = {
        url: String,
        // Id du champ « Nom de la fiche » : son contenu ouvre la requête.
        labelId: String,
        // Préfixe des ids des champs de Localisation (fiche_creation_localisation).
        prefixe: String,
        min: { type: Number, default: 3 },
        // 400 ms : mêmes réglages que l'autocomplétion du référentiel.
        delai: { type: Number, default: 400 },
    }

    connect() {
        this.fermerSiExterieur = this.fermerSiExterieur.bind(this)
        this.indexActif = -1
        this.choix = null
    }

    disconnect() {
        this.fermer()
        window.clearTimeout(this.minuterie)
        this.controleurFetch?.abort()
    }

    nom() {
        return document.getElementById(this.labelIdValue)?.value.trim() ?? ''
    }

    saisir() {
        this.choix = null
        window.clearTimeout(this.minuterie)
        if (`${this.nom()} ${this.champTarget.value.trim()}`.trim().length < this.minValue) {
            this.fermer()
            return
        }
        this.minuterie = window.setTimeout(() => this.charger(), this.delaiValue)
    }

    changerPays() {
        this.choix = null
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
        // Nom et texte séparés : le serveur essaie « nom + texte » puis, faute
        // de résultat, le texte seul (un nom inconnu d'OSM étouffe la requête).
        const params = new URLSearchParams({
            nom: this.nom(),
            q: this.champTarget.value.trim(),
            pays,
        })
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
            vide.textContent = 'Aucune adresse trouvée : précisez la rue ou la ville.'
            vide.className = 'px-3 py-2 text-sm text-neutral-500'
            this.listeTarget.append(vide)
            this.ouvrir()
            return
        }
        for (const suggestion of suggestions) {
            const option = document.createElement('button')
            option.type = 'button'
            option.setAttribute('role', 'option')
            option.textContent = suggestion.label
            option.dataset.suggestion = JSON.stringify(suggestion)
            option.className = 'w-full px-3 py-2 rounded-xs text-left text-sm cursor-pointer truncate hover:bg-primary-4'
            // mousedown : le clic doit gagner contre le blur du champ.
            option.dataset.action = 'mousedown->adresse-autocomplete#choisir'
            this.listeTarget.append(option)
        }
        this.ouvrir()
    }

    ouvrir() {
        this.listeTarget.hidden = false
        this.champTarget.setAttribute('aria-expanded', 'true')
        document.addEventListener('mousedown', this.fermerSiExterieur)
    }

    choisir(event) {
        event.preventDefault()
        this.retenir(event.currentTarget)
    }

    retenir(option) {
        this.choix = JSON.parse(option.dataset.suggestion)
        this.champTarget.value = this.choix.label
        this.fermer()
        this.annoncer('Adresse retenue : cliquez sur « Appliquer le choix » pour remplir les champs.')
    }

    clavier(event) {
        if (this.listeTarget.hidden) return
        if (event.key === 'Escape') {
            this.fermer()
            return
        }
        // La ligne « aucune adresse » n'est pas une option navigable.
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

    appliquer() {
        if (!this.choix) {
            this.annoncer('Choisissez d’abord une adresse dans les suggestions.')
            return
        }
        for (const [cle, valeur] of Object.entries(this.choix)) {
            if (cle === 'label' || valeur === null) continue
            const champ = document.getElementById(`${this.prefixeValue}_${cle}`)
            if (!champ) continue
            champ.value = valeur
            // Le rail de la page écoute ces champs (badges de remplissage).
            champ.dispatchEvent(new Event('input', { bubbles: true }))
        }
        this.annoncer('Adresse appliquée : vérifiez et complétez les champs ci-dessous.')
    }

    annoncer(texte) {
        if (this.hasEtatTarget) {
            this.etatTarget.textContent = texte
        }
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
