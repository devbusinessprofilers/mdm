import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button'];
    static values = { formDatasetId: String };

    connect() {
        const target = document.querySelector(`[data-dynamic-form-id="${this.formDatasetIdValue}"]`)
        if (target) {
            this.buttonTarget.setAttribute('form', target.id);
        }
    }
}
