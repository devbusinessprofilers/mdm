import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { modalIdentifier: String };

    open() {
        const modalElement = document.querySelector(`[data-modal="${this.modalIdentifierValue}"]`)
        const modalController = this.application.getControllerForElementAndIdentifier(modalElement, 'provider-portal--modal');

        modalController.open();
    }

    close() {
        const modalElement = document.querySelector(`[data-modal="${this.modalIdentifierValue}"]`)
        const modalController = this.application.getControllerForElementAndIdentifier(modalElement, 'provider-portal--modal');

        modalController.close();
    }
}
