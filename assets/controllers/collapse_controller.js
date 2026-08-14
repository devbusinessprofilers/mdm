import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['content', 'indicator', 'checkboxController'];
    static values = { defaultValue: Boolean };

    value;

    connect() {
        this.value = this.defaultValueValue;
        this.value ? this.handleOpen() : this.handleClose();
    }

    toggle(event) {
        if (this.hasCheckboxControllerTarget && event.target.tagName === 'INPUT') {
            this.value = this.checkboxControllerTarget.checked;
        } else {
            this.value = !this.value;
        }

        this.value ? this.handleOpen() : this.handleClose();
    }

    handleOpen() {
        this.contentTarget.classList.remove('grid-rows-[0fr]');
        this.contentTarget.classList.remove('mb-0');
        this.contentTarget.classList.add('grid-rows-[1fr]');
        this.contentTarget.classList.add('mb-4');

        if (this.hasIndicatorTarget) {
            this.indicatorTarget.classList.add('rotate-180');
        }
    }

    handleClose() {
        this.contentTarget.classList.remove('grid-rows-[1fr]');
        this.contentTarget.classList.remove('mb-4');
        this.contentTarget.classList.add('grid-rows-[0fr]');
        this.contentTarget.classList.add('mb-0');

        if (this.hasIndicatorTarget) {
            this.indicatorTarget.classList.remove('rotate-180');
        }
    }

    disconnect() {
    }
}
