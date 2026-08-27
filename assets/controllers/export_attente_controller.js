import { Controller } from '@hotwired/stimulus';

/**
 * Page de suivi d'un export Excel du référentiel : sonde le statut pendant
 * la génération (worker), bascule l'affichage attente → prêt (ou échec), et
 * déclenche le téléchargement — automatiquement si la case est cochée au
 * moment où le fichier devient prêt, sinon au clic sur le bouton.
 */
export default class extends Controller {
    static targets = ['attente', 'pret', 'echec', 'auto'];
    static values = {
        statut: String,
        statutUrl: String,
        fichierUrl: String,
    };

    connect() {
        if (this.statutValue === 'en_attente' || this.statutValue === 'en_cours') {
            this.surveiller();
        }
    }

    disconnect() {
        clearTimeout(this.minuterie);
    }

    async surveiller() {
        try {
            const reponse = await fetch(this.statutUrlValue, { headers: { Accept: 'application/json' } });
            if (reponse.ok) {
                const { statut } = await reponse.json();
                if (statut === 'terminee') {
                    this.attenteTarget.hidden = true;
                    this.pretTarget.hidden = false;
                    if (this.autoTarget.checked) {
                        this.telecharger();
                    }
                    return;
                }
                if (statut === 'echoue') {
                    this.attenteTarget.hidden = true;
                    this.echecTarget.hidden = false;
                    return;
                }
            }
        } catch {
            // Réseau momentanément indisponible : on réessaie au prochain tour.
        }
        this.minuterie = setTimeout(() => this.surveiller(), 3000);
    }

    telecharger() {
        window.location.assign(this.fichierUrlValue);
    }
}
