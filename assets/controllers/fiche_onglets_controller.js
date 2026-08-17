import { Controller } from '@hotwired/stimulus';

/*
 * Onglets de l'éditeur de fiche.
 *
 * Toutes les sections sont rendues dans la page, dans un seul formulaire :
 * changer d'onglet ne fait que basculer les volets visibles — les saisies des
 * autres onglets restent dans le DOM et partent ensemble à l'enregistrement.
 * L'URL est mise à jour (`?section=N`) sans navigation : un rechargement ou
 * un partage retombe sur le même onglet, et sans JavaScript les entrées du
 * rail restent de vrais liens.
 */
export default class extends Controller {
    static targets = ['volet', 'entree'];

    activer(evenement) {
        evenement.preventDefault();
        const index = String(evenement.params.volet);

        this.voletTargets.forEach((volet) => {
            volet.hidden = volet.dataset.volet !== index;
        });
        this.entreeTargets.forEach((entree) => {
            if (String(entree.dataset.ficheOngletsVoletParam) === index) {
                entree.setAttribute('aria-current', 'page');
            } else {
                entree.removeAttribute('aria-current');
            }
        });

        const url = new URL(evenement.currentTarget.href, window.location.href);
        window.history.replaceState({}, '', url);
        this.element.querySelector('[data-fiche-onglets-haut]')?.scrollIntoView({ block: 'start' });
    }
}
