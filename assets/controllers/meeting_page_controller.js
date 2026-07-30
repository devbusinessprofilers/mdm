import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['editButton', 'collectionPreviewWrapper', 'collectionEditionWrapper'];

    editCollection() {
        this.editButtonTarget.remove();
        this.collectionPreviewWrapperTarget.remove();
        this.collectionEditionWrapperTarget.classList.remove('hidden');
    }
}
