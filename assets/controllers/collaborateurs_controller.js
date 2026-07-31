import { Controller } from '@hotwired/stimulus';

/*
 * Panneau latéral de la section « Collaborateurs ».
 *
 * Deux états : l'invitation par email et le formulaire complet. Le crayon
 * d'une ligne ouvre le formulaire pré-rempli, « Continuer » l'ouvre vierge.
 * Comme dans la maquette, les droits et le statut repartent à zéro à chaque
 * ouverture — ils ne sont pas portés par la ligne.
 */
export default class extends Controller {
    static targets = ['ajout', 'formulaire', 'nom', 'prenom', 'email', 'telephone', 'role', 'principal'];

    connect() {
        // Adresse de l'invitation : c'est elle qui revient quand le formulaire
        // est ouvert sans passer par une ligne du tableau.
        this.emailParDefaut = this.emailTarget.textContent;
    }

    /** Ouvre le formulaire vierge depuis l'écran d'invitation. */
    continuer() {
        this.remplir({ nom: '', prenom: '', telephone: '', role: 'Manager' });
        this.afficher(this.formulaireTarget);
    }

    /** Ouvre le formulaire sur une ligne du tableau. */
    editer(evenement) {
        this.remplir(evenement.params);
        this.afficher(this.formulaireTarget);
    }

    annuler() {
        this.afficher(this.ajoutTarget);
    }

    basculerDroit(evenement) {
        const bouton = evenement.currentTarget;
        bouton.setAttribute('aria-checked', 'true' === bouton.getAttribute('aria-checked') ? 'false' : 'true');
    }

    basculerPrincipal() {
        const bouton = this.principalTarget;
        bouton.setAttribute('aria-checked', 'true' === bouton.getAttribute('aria-checked') ? 'false' : 'true');
    }

    afficher(etat) {
        this.ajoutTarget.hidden = etat !== this.ajoutTarget;
        this.formulaireTarget.hidden = etat !== this.formulaireTarget;
    }

    /*
     * L'email reste en lecture seule — il est affiché, jamais saisi. Un champ
     * vide garde la teinte grise du texte d'invite.
     */
    remplir(valeurs) {
        ['nom', 'prenom', 'telephone'].forEach((nom) => {
            const cible = this[`${nom}Target`];
            cible.textContent = valeurs[nom] ?? '';
            cible.classList.toggle('collab-panneau__valeur--vide', '' === cible.textContent);
        });

        this.emailTarget.textContent = valeurs.email ?? this.emailParDefaut;
        this.roleTarget.textContent = valeurs.role ?? 'Manager';

        this.element.querySelectorAll('.collab-panneau__droit').forEach((droit) => {
            droit.setAttribute('aria-checked', 'false');
        });

        this.principalTarget.setAttribute('aria-checked', '1' === String(valeurs.principal) ? 'true' : 'false');
    }
}
