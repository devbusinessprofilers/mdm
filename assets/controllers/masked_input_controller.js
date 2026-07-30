import { Controller } from '@hotwired/stimulus';
import IMask from 'imask';

export default class extends Controller {
    static targets = ['input'];
    static values = {
        mask: String,
    };

    connect() {
        this.imask = IMask(this.inputTarget, { mask: this.maskValue });
    }
}
