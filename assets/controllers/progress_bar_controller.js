import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['bar'];
    static values = {
        max: Number,
        current: Number,
    };

    connect() {
        this.update();
    }

    update() {
        const percentage = Math.min(100, Math.max(0, (this.currentValue / this.maxValue) * 100));
        this.barTarget.style.width = `${percentage}%`;
    }

    currentValueChanged() {
        this.update();
    }
}
