import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['disableTag', 'enableTag'];
    static values = { state: Boolean };

    connect() {
        this.update();
    }

    update() {
        if (this.stateValue) {
            this.disableTagTarget.classList.add('hidden')
            this.enableTagTarget.classList.remove('hidden')
        } else {
            this.enableTagTarget.classList.add('hidden')
            this.disableTagTarget.classList.remove('hidden')
        }
    }

    stateValueChanged() {
        this.update();
    }
}
