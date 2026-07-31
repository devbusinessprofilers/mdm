import { Controller } from '@hotwired/stimulus';
import { createPopper } from '@popperjs/core';

export default class extends Controller {
    static targets = ['trigger', 'tooltip', 'arrow'];
    static values = { placement: String }

    popper = null;
    isOpen = false;
    hideTimeout = null;

    open() {
        if (this.isOpen) {
            clearTimeout(this.hideTimeout);

            return;
        }

        this.tooltipTarget.classList.remove('hidden');

        this.popper = createPopper(this.triggerTarget, this.tooltipTarget, {
            placement: this.placementValue,
            modifiers: [
                { name: 'arrow', options: { element:  this.arrowTarget } },
                { name: 'offset', options: { offset: [0, 20] } },
            ],
        });

        this.isOpen = true;
    }

    close() {
        if (!this.isOpen) {
            return;
        }

        this.hideTimeout = setTimeout(() => {
            this.tooltipTarget.classList.add('hidden');
            this.popper?.destroy();
            this.popper = null;

            this.isOpen = false;
        }, 100);
    }
}
