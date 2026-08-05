import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    toggle(event) {
        const row = event.target.closest('[data-ocr-suggestion]');
        if (!row || !event.target.checked) return;
        const checkbox = row.querySelector(`[data-ocr-choice="${event.target.dataset.ocrOpposite}"]`);
        if (checkbox) checkbox.checked = false;
    }
}
