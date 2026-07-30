import { Controller } from '@hotwired/stimulus';
import IMask from 'imask';

export default class extends Controller {
    static targets = ['input'];
    static values = {
        scale: Number,
        step: Number,
        min: Number,
        max: Number,
        radix: String,
        thousandsSeparator: String,
        padFractionalZeros: Boolean,
    };

    mask;
    roundScale;

    connect() {
        if (this.radixValue === this.thousandsSeparatorValue) {
            console.error('Radix and thousands separator mus be different.')

            return;
        }

        const options = {
            mask: Number,
            scale: this.scaleValue,
            thousandsSeparator: this.thousandsSeparatorValue,
            padFractionalZeros: this.padFractionalZerosValue,
            radix: this.radixValue,
            mapToRadix: ['.',','],
        }

        if (null !== this.min) {
            options.min = this.min;
        }

        if (null !== this.max) {
            options.max = this.max;
        }

        this.mask = IMask(this.inputTarget, options);

        this.roundScale = Math.pow(10, 2);

        this.dispatch('initialized', { detail: { numberInput: this.inputTarget, imask: this.mask } });
    }

    increase(event) {
        event.preventDefault();
        this.setValue(Math.round(this.roundScale * (this.currentValue() + this.stepValue)) / this.roundScale);
    }

    decrease(event) {
        event.preventDefault();
        this.setValue(Math.round(this.roundScale * (this.currentValue() - this.stepValue)) / this.roundScale);
    }

    get min() {
        return this.hasMinValue ? this.minValue : null;
    }

    get max() {
        return this.hasMaxValue ? this.maxValue : null;
    }

    currentValue() {
        return Number(this.inputTarget.value.replaceAll(this.thousandsSeparatorValue, '').replace(this.radixValue, '.'));
    }

    setValue(newValue) {
        this.inputTarget.value = newValue.toString().replace('.', this.radixValue);
        this.inputTarget.dispatchEvent(new Event('input', { 'value': this.inputTarget.value }))
        this.mask.updateValue();
    }
}
