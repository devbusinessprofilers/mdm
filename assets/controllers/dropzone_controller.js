import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['inputFile', 'cardArea', 'cardDefaultPrototype', 'cardPreviewPrototype'];
    static values = {
        withPreview: Boolean,
        maxFileCount: Number,
        documents: Array,
    };

    connect() {
        this.documentsValue.forEach((document) => this.addDocumentCard(document));
    }

    onFileChange = () => {
        const uploadedFiles = this.inputFileTarget.files;

        let count = this.cardAreaTarget.childElementCount;
        for (const file of uploadedFiles) {
            if (count >= this.maxFileCountValue) {
                console.error('Max file count reached');

                continue;
            }

            // @todo: complete action depending on form implementation!
            console.log('upload document');

            this.addDocumentCard({ name: file.name, url: URL.createObjectURL(file) });
            ++count;
        }
    }

    addDocumentCard(document) {
        const card = this.withPreviewValue ? this.createPreviewCard(document) : this.createDefaultCard(document);

        card.querySelector('[data-prototype-delete]').addEventListener('click', () => {
            // @todo: complete action depending on form implementation!
            console.log('remove document')

            card.remove();
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
