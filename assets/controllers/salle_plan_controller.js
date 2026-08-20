/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus'

// Import d'un fichier depuis une ligne du tableau « Capacités par salle » :
// le fichier part vers le flux documentaire de la fiche (usage Plan de salle),
// rattaché à la salle de la ligne. L'endpoint et le nom du formulaire varient
// par gamme (Lieu, Restaurant) et arrivent en values. Le fichier se gère
// ensuite dans la section Médias (métadonnées, remplacement, suppression).
export default class extends Controller {
    static targets = ['erreur']
    static values = { url: String, token: String, formulaire: String }

    async importer(event) {
        const input = event.target
        const file = input.files[0]
        if (!file) return
        const body = new FormData()
        body.append(`${this.formulaireValue}[documents][]`, file)
        body.append(`${this.formulaireValue}[usage]`, 'CONFIG_PLAN_SALLE')
        body.append(`${this.formulaireValue}[salle]`, event.params.salle)
        body.append(`${this.formulaireValue}[_token]`, this.tokenValue)
        this.montrerErreur('')
        input.disabled = true
        try {
            const response = await window.fetch(this.urlValue, {
                method: 'POST',
                body,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
            if (!response.ok) {
                const result = await response.json().catch(() => ({}))
                this.montrerErreur(result.error || "L'import du fichier a échoué.")
                return
            }
            this.marquerDeposee(input)
        } catch (_) {
            this.montrerErreur('Le stockage des documents est indisponible.')
        } finally {
            input.disabled = false
            input.value = ''
        }
    }

    marquerDeposee(input) {
        const vignette = input.closest('[data-salle-plan-vignette]')
        if (!vignette) return
        vignette.classList.remove('border-dashed', 'border-neutral-300', 'text-neutral-400')
        vignette.classList.add('border-solid', 'border-primary', 'text-primary')
        vignette.title = 'Fichier déposé — gérez-le dans la section Médias'
    }

    montrerErreur(message) { this.erreurTarget.textContent = message; this.erreurTarget.hidden = !message }
}
