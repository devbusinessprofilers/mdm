import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['wrapper', 'inputFile', 'prototype', 'preview'];
    static values = { pictureUrl: String }
;

    connect() {
        if (null !== this.pictureUrlValue && this.pictureUrlValue.length > 0) {
            this.setPreview(this.pictureUrlValue);
        }
    }

    onFileChange = () => {
        const uploadedFiles = this.inputFileTarget.files;

        if (uploadedFiles.length !== 1) {
            return;
        }

        this.setPreview(URL.createObjectURL(uploadedFiles[0]));

        // @todo: complete action depending on form implementation!
        console.log('upload picture');
    }

    setPreview(pictureUrl) {
        if (null === pictureUrl || pictureUrl.length === 0) {
            return;
        }

        // IMPORTANT: clear previous preview to avoid duplicate elements!
        this.previewTarget.innerHTML = '';

        const preview = this.prototypeTarget.cloneNode(true);

        const image = preview.querySelector('[data-prototype-image]');
        image.src = pictureUrl;

        const button = preview.querySelector('[data-prototype-delete]');
        button.addEventListener('click', () => {
            // @todo: complete action depending on form implementation!
            console.log('remove picture')

            image.remove();
            button.remove();
        });

        this.previewTarget.appendChild(image);
        this.previewTarget.appendChild(button);
    }
}
