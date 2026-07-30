import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['items'];
    static values = {
        index: Number,
        prototype: String,
    };

    add(event) {
        event.preventDefault();
        const wrapper = document.createElement('fieldset');
        wrapper.dataset.formCollectionItem = '';
        wrapper.innerHTML = this.prototypeValue.replaceAll('__name__', String(this.indexValue));

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.textContent = event.currentTarget.dataset.removeLabel || 'Retirer';
        removeButton.dataset.action = 'form-collection#remove';
        wrapper.append(removeButton);
        this.itemsTarget.append(wrapper);
        this.indexValue++;
    }

    remove(event) {
        event.preventDefault();
        event.currentTarget.closest('[data-form-collection-item]')?.remove();
    }
}
