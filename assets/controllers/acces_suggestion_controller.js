/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus'

// Bouton « Suggérer les accès » du bloc Accès : demande au serveur ce qu'il y a
// autour des coordonnées GPS de la fiche (aéroport, gare, métro, tramway,
// grande ville) et ajoute chaque proposition comme ligne pré-remplie de la
// collection — retouchable ou retirable avant l'enregistrement. Les types déjà
// présents dans le formulaire ne sont pas redemandés. Vit sur la rangée
// d'en-tête du fieldset form-collection, dont il réutilise l'ajout de ligne.
export default class extends Controller {
    static targets = ['bouton', 'statut']

    async suggerer() {
        const form = this.element.closest('form')
        const fieldset = this.fieldset
        const collection = fieldset
            ? this.application.getControllerForElementAndIdentifier(fieldset, 'form-collection')
            : null
        if (!form || !collection) return
        this.effacerStatut()
        this.basculer(true)
        try {
            const response = await window.fetch(form.dataset.ficheSuggererAccesUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': form.dataset.ficheSuggererAccesCsrf,
                },
                body: JSON.stringify({
                    gamme: form.dataset.ficheGamme,
                    id: form.dataset.ficheId,
                    exclus: this.typesPresents(),
                }),
            })
            if (!response.ok) {
                const data = await response.json().catch(() => ({}))
                this.montrerStatut(data.error || 'La suggestion a échoué.')
                return
            }
            const { acces } = await response.json()
            if (!acces?.length) {
                this.montrerStatut('Rien de plus à suggérer autour de cette adresse.')
                return
            }
            acces.forEach((entree) => this.ajouter(collection, entree))
        } catch (_) {
            this.montrerStatut('Le serveur est injoignable — réessayez.')
        } finally {
            this.basculer(false)
        }
    }

    get fieldset() {
        return this.element.closest('fieldset[data-controller~="form-collection"]')
    }

    // Types des lignes déjà là (persistées ou fraîchement ajoutées) : le
    // serveur ne les repropose pas.
    typesPresents() {
        return [...(this.fieldset?.querySelectorAll('[data-form-collection-item] select') ?? [])]
            .filter((select) => select.name.endsWith('[type]'))
            .map((select) => select.value)
            .filter(Boolean)
    }

    // Une ligne par l'ajout standard de form-collection, puis remplissage des
    // champs par leur nom de soumission ([type], [nom]…).
    ajouter(collection, entree) {
        collection.add({ preventDefault() {}, currentTarget: this.boutonTarget })
        const ligne = collection.itemsTarget.lastElementChild
        if (!ligne) return
        for (const [champ, valeur] of Object.entries(entree)) {
            if (valeur === null || valeur === undefined) continue
            const widget = [...ligne.querySelectorAll('select, input')].find((el) => el.name.endsWith(`[${champ}]`))
            if (!widget) continue
            // Le Type passe par le composant Form:Select : son contrôleur
            // Stimulus `select` (pas encore connecté, la ligne vient d'être
            // insérée) dérive la sélection de data-…-default-selection-value
            // au branchement — poser la valeur sur le <select> caché serait
            // écrasé à ce moment-là.
            const composant = widget.closest('[data-controller~="select"]')
            if (composant) {
                composant.setAttribute('data-select-default-selection-value', JSON.stringify([String(valeur)]))
            } else {
                widget.value = String(valeur)
            }
        }
    }

    basculer(occupe) {
        this.boutonTarget.disabled = occupe
        this.boutonTarget.textContent = occupe ? 'Recherche…' : 'Suggérer les accès'
    }

    montrerStatut(message) {
        this.statutTarget.textContent = message
        this.statutTarget.classList.remove('hidden')
    }

    effacerStatut() {
        this.statutTarget.textContent = ''
        this.statutTarget.classList.add('hidden')
    }
}
