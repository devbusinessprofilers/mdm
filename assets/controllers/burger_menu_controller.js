import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['outsideTrigger', 'insideTrigger', 'menu'];

    toggle() {
        this.outsideTriggerTarget.classList.toggle('hidden');
        this.insideTriggerTarget.classList.toggle('hidden');

        this.menuTarget.classList.toggle('opacity-100');
        this.menuTarget.classList.toggle('opacity-0');
        this.menuTarget.classList.toggle('translate-x-full');
    }
}
