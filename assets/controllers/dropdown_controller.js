import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['trigger', 'indicator', 'content'];
    static values = {
        aboveClass: String,
        belowClass: String,
    };

    isOpen = false;

    connect() {
        this.handleClickOutside = this.handleClickOutside.bind(this)
    }

    open() {
        if (this.isOpen) {
            return;
        }

        // NOTE: display dropdown before resolving position, otherwise dropdown height cannot be known
        this.contentTarget.classList.remove('hidden');
        this.resolvePosition();
        this.isOpen = true;

        if (this.hasIndicatorTarget) {
            this.indicatorTarget.classList.toggle('rotate-180');
        }

        document.addEventListener('click', this.handleClickOutside)
    }

    close() {
        if (!this.isOpen) {
            return;
        }

        this.contentTarget.classList.add('hidden');
        this.isOpen = false;

        if (this.hasIndicatorTarget) {
            this.indicatorTarget.classList.toggle('rotate-180');
        }

        document.removeEventListener('click', this.handleClickOutside)
    }

    toggle(event) {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    resolvePosition() {
        const triggerPosition = this.triggerTarget.getBoundingClientRect();
        const dropdownHeight = this.contentTarget.offsetHeight;
        const spaceAbove = triggerPosition.top;
        const spaceBelow = window.innerHeight - triggerPosition.bottom;

        this.contentTarget.classList.remove(this.aboveClassValue, this.belowClassValue);

        // Use appropriate class "absolute position" (tailwind) to display dropdown above or below
        if (spaceBelow < dropdownHeight && spaceAbove > dropdownHeight) {
            this.contentTarget.classList.add(this.aboveClassValue);
        } else {
            this.contentTarget.classList.add(this.belowClassValue);
        }
    }

    handleClickOutside(event) {
        if (!this.element.contains(event.target)) {
            this.close()
        }
    }
}
