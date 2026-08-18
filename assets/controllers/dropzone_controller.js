import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */

/*
 * Zone de dépôt du design-system (composant Form:Dropzone), branchée sur un
 * vrai <input type="file"> soumis avec le formulaire — pas d'AJAX. Le
 * contrôleur du portail était une ébauche (suppression factice) : ici la
 * liste de fichiers est vraiment reconstruite via DataTransfer, la corbeille
 * d'une carte retire donc le fichier de la soumission.
 */
export default class extends Controller {
    static targets = ['inputFile', 'cardArea', 'cardDefaultPrototype', 'cardPreviewPrototype'];
    static values = {
        withPreview: Boolean,
        maxFileCount: Number,
        documents: Array,
    };

    connect() {
        // Documents déjà en place côté serveur : cartes informatives.
        this.documentsValue.forEach((document) => this.addDocumentCard(document, null));
    }

    onFileChange() {
        // La sélection native remplace la précédente ; on borne au plafond.
        const files = Array.from(this.inputFileTarget.files).slice(0, this.maxFileCountValue);
        const transfer = new DataTransfer();
        files.forEach((file) => transfer.items.add(file));
        this.inputFileTarget.files = transfer.files;
        this.renderCards();
    }

    renderCards() {
        this.cardAreaTarget.innerHTML = '';
        this.documentsValue.forEach((document) => this.addDocumentCard(document, null));
        Array.from(this.inputFileTarget.files).forEach((file, index) => {
            this.addDocumentCard({ name: file.name, url: URL.createObjectURL(file) }, index);
        });
    }

    addDocumentCard(document, index) {
        const card = this.withPreviewValue ? this.createPreviewCard(document) : this.createDefaultCard(document);

        card.querySelector('[data-prototype-delete]').addEventListener('click', () => {
            if (null === index) {
                card.remove();

                return;
            }
            const transfer = new DataTransfer();
            Array.from(this.inputFileTarget.files).forEach((file, i) => {
                if (i !== index) {
                    transfer.items.add(file);
                }
            });
            this.inputFileTarget.files = transfer.files;
            this.renderCards();
        });

        this.cardAreaTarget.appendChild(card);
    }

    createDefaultCard(document) {
        const card = this.cardDefaultPrototypeTarget.cloneNode(true);
        card.querySelector('[data-prototype-value]').innerHTML = document.name;

        return card;
    }

    createPreviewCard(document) {
        const card = this.cardPreviewPrototypeTarget.cloneNode(true);
        const img = card.querySelector('img');
        img.src = document.url;
        img.alt = document.name;

        return card;
    }
}
