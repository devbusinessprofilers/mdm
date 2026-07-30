import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['exTaxAmount', 'vat', 'taxAmount'];
    static values = { vatRates: Object };

    connect() {
        this.vatTarget.querySelectorAll('input[type="radio"]').forEach((radio) => {
            radio.addEventListener('change', () => this.calculateTaxAmount())
        });
    }

    calculateTaxAmount() {
        const selectedVat = this.vatTarget.querySelector('input[type="radio"]:checked');
        const rate = selectedVat ? this.vatRatesValue[selectedVat.value] : null;

        const exTaxAmountController = this.application.getControllerForElementAndIdentifier(this.exTaxAmountTarget.parentElement, 'provider-portal--number-input');
        const exTaxAmount = exTaxAmountController.currentValue();

        const taxAmount = (!!rate && !!exTaxAmount) ? rate * exTaxAmount : 0;

        const taxAmountController = this.application.getControllerForElementAndIdentifier(this.taxAmountTarget.parentElement, 'provider-portal--number-input');
        taxAmountController.setValue(taxAmount);
    }
}
