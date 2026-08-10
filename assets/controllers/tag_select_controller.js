import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['select', 'choices'];

    connect() {
        this.choicesTarget.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
            checkbox.addEventListener('change', (event) => {
                this.onChange(checkbox.value, checkbox.checked);
            });
        });
    }

    onChange(value, state) {
        const option = this.selectTarget.querySelector(`option[value="${value}"]`);
        option.selected = state;
    }
}
