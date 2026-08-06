import { Controller } from '@hotwired/stimulus';

/*
 * Affiche uniquement les sous-thématiques dont la thématique parente est
 * cochée (les cases portent data-parent = code de la thématique). Une
 * sous-thématique masquée est décochée pour rester cohérente côté serveur.
 */
export default class extends Controller {
    static targets = ['thematique', 'sousThematique'];

    connect() {
        this.refresh();
    }

    refresh() {
        const selected = new Set(
            this.thematiqueTargets
                .filter((checkbox) => checkbox.checked)
                .map((checkbox) => checkbox.value),
        );
        for (const checkbox of this.sousThematiqueTargets) {
            const visible = selected.has(checkbox.dataset.parent);
            const wrapper = checkbox.closest('.form-check') ?? checkbox.parentElement;
            if (wrapper) {
                wrapper.hidden = !visible;
            }
            if (!visible && checkbox.checked) {
                checkbox.checked = false;
            }
        }
    }
}
