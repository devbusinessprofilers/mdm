import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'lengthTag', 'upperTag', 'lowerTag', 'numberTag', 'specialTag', 'progress'];
    static values = { withControl: Boolean }

    connect() {
        this.handle();
    }

    handle() {
        if (!this.withControlValue) {
            return;
        }

        const password = this.inputTarget.value;
        let success = 0;

        if (password.length >= 8) {
            success++;
            this.lengthTagTarget.dataset['providerPortal-StateTagStateValue'] = 'true';
        } else {
            this.lengthTagTarget.dataset['providerPortal-StateTagStateValue'] = 'false';
        }

        const upperRegex = /[A-Z]+/;
        if (upperRegex.test(password)) {
            success++;
            this.upperTagTarget.dataset['providerPortal-StateTagStateValue'] = 'true';
        } else {
            this.upperTagTarget.dataset['providerPortal-StateTagStateValue'] = 'false';
        }

        const lowerRegex = /[a-z]+/;
        if (lowerRegex.test(password)) {
            success++;
            this.lowerTagTarget.dataset['providerPortal-StateTagStateValue'] = 'true';
        } else {
            this.lowerTagTarget.dataset['providerPortal-StateTagStateValue'] = 'false';
        }

        const numberRegex = /[0-9]+/;
        if (numberRegex.test(password)) {
            success++;
            this.numberTagTarget.dataset['providerPortal-StateTagStateValue'] = 'true';
        } else {
            this.numberTagTarget.dataset['providerPortal-StateTagStateValue'] = 'false';
        }

        const specialRegex = /[^\w\d]+/;
        if (specialRegex.test(password)) {
            success++;
            this.specialTagTarget.dataset['providerPortal-StateTagStateValue'] = 'true';
        } else {
            this.specialTagTarget.dataset['providerPortal-StateTagStateValue'] = 'false';
        }

        this.progressTarget.dataset['providerPortal-ProgressBarCurrentValue'] = success;
    }

    toggle() {
        this.inputTarget.type = ('text' === this.inputTarget.type) ? 'password' : 'text';
    }
}
