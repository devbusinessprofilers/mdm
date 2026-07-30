import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['select'];
    static values = { nearPath: String, type: String, position: Object };

    connect() {
        this.intitChoices();
        const addressController = this.getAddressController();
        if (addressController) {
            addressController.element.addEventListener('custom:change', this.onAddressChange.bind(this));
        }
    }

    async intitChoices() {
        const latitude = this.positionValue?.latitude ?? '';
        const longitude = this.positionValue?.longitude ?? '';
        const response = await fetch(`${this.nearPathValue}?type=${this.typeValue}&latitude=${latitude}&longitude=${longitude}`);

        if (!response.ok) {
            throw new Error('Failed to fetch choices');
        }

        const choices = await response.json();
        const selectController = this.getSelectController();
        if (selectController) {
            selectController.replaceChoices(choices.map((choice) => ({ ...choice, json: JSON.stringify(choice) })));
        }
    }

    onAddressChange(event) {
        this.positionValue = event.detail;
        this.intitChoices();
    }

    getSelectController() {
        return this.application.getControllerForElementAndIdentifier(
            this.element.querySelector('[data-controller=provider-portal--select]'),
            'provider-portal--select'
        );
    }

    getAddressController() {
        return this.application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller=provider-portal--address]'),
            'provider-portal--address'
        );
    }

    disconnect() {
        const addressController = this.getAddressController();
        if (addressController) {
            addressController.element.removeEventListener('custom:change', this.onAddressChange);
        }
    }
}
