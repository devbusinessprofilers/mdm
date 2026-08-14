import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menuWrapper', 'menu'];

    isOpen = false;

    toggle() {
        if (this.isOpen) {
            this.menuTarget.classList.toggle('-translate-x-full');

            setTimeout(() => {
                this.menuWrapperTarget.classList.toggle('hidden');
                this.menuTarget.classList.toggle('hidden');
            }, 400);
        } else {
            this.menuWrapperTarget.classList.toggle('hidden');
            this.menuTarget.classList.toggle('hidden');

            setTimeout(() => {
                this.menuTarget.classList.toggle('-translate-x-full');
            }, 200);
        }

        this.isOpen = !this.isOpen;
    }
}
