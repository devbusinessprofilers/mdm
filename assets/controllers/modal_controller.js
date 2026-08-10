import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal'];
    static values = { display: Boolean };

    connect() {
        if (this.displayValue) {
            this.open();
        }
    }

    open() {
        this.modalTarget.style.display = 'flex';
    }

    close() {
        this.modalTarget.style.display = 'none';
    }
}
