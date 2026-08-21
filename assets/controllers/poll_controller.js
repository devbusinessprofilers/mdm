import { Controller } from '@hotwired/stimulus';

/*
 * Recharge périodiquement le contenu de l'élément porteur en re-récupérant un
 * fragment HTML côté serveur, pour suivre en direct un contenu qui évolue (ex.
 * l'état des files Messenger sur l'écran Outils) sans recharger toute la page.
 *
 * Volontairement autonome (fetch + remplacement) plutôt que de s'appuyer sur le
 * rechargement natif d'une turbo-frame : on maîtrise ainsi l'anti-cache et on ne
 * dépend pas de l'état interne de Turbo.
 */
export default class extends Controller {
    static values = { url: String, interval: Number };

    connect() {
        const delay = this.intervalValue > 0 ? this.intervalValue : 10000;
        this.timer = window.setInterval(() => this.refresh(), delay);
    }

    disconnect() {
        window.clearInterval(this.timer);
    }

    async refresh() {
        if (document.hidden || !this.hasUrlValue) {
            return;
        }
        try {
            const response = await fetch(this.urlValue, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
            });
            if (!response.ok) {
                return;
            }
            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const fresh = doc.getElementById(this.element.id) ?? doc.body.firstElementChild;
            if (fresh) {
                this.element.innerHTML = fresh.innerHTML;
            }
        } catch {
            // Erreur réseau transitoire : on retentera au prochain tick.
        }
    }
}
