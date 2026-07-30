import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['inputFile', 'prototype', 'preview'];
    static values = { fileName: String };

    connect() {
        if (null !== this.fileNameValue && this.fileNameValue.length > 0) {
            this.setPreview(this.fileNameValue);
        }
    }

    onFileChange = () => {
        const uploadedFiles = this.inputFileTarget.files;

        if (uploadedFiles.length !== 1) {
            return;
        }

        this.setPreview(uploadedFiles[0].name);

        // @todo: complete action depending on form implementation!
        console.log('upload document');
    }

    setPreview(fileName) {
        // IMPORTANT: clear previous preview to avoid duplicate elements!
        this.previewTarget.innerHTML = '';

        const preview = this.prototypeTarget.cloneNode(true);

        preview.querySelector('[data-prototype-document]').innerHTML = fileName;
        preview.querySelector('[data-prototype-delete]').addEventListener('click', () => {
            // @todo: complete action depending on form implementation!
            console.log('remove document')

            preview.remove();
        });

        this.previewTarget.appendChild(preview);
    }
}
