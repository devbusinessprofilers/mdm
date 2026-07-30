import { Controller } from '@hotwired/stimulus';

const DOMTOM_CODES = ['GUA', 'MAR', 'GUY', 'REU', 'MAY'];

export default class extends Controller {
    static targets = ['notice'];

    connect() {
        const select = this.element.querySelector('select[multiple]');
        if (select) {
            this.#refresh(select);
        }
    }

    update(event) {
        if (!event.target.multiple) {
            return;
        }

        this.#refresh(event.target);
    }

    #refresh(select) {
        const hasDomTom = Array.from(select.options)
            .some(option => option.selected && DOMTOM_CODES.includes(option.value));

        this.noticeTarget.classList.toggle('hidden', !hasDomTom);
    }
}
