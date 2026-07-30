import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['editButton', 'previewWrapper', 'editionWrapper'];

    edit() {
        this.editButtonTarget.remove();
        this.previewWrapperTarget.remove();
        this.editionWrapperTarget.classList.remove('hidden');
    }
}
