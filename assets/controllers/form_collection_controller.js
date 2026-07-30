import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';

/**
 * Allows to manage CollectionType in a form with add/remove functionalities.
 * When allowing add, we use both data attributes 'prototype' and 'prototypeName' (@see https://symfony.com/doc/current/reference/forms/types/collection.html)
 */
export default class extends Controller {
    static targets = ['collection', 'addButton'];
    static values = {
        maxItemCount: Number,
        sortable: Boolean,
    };

    referenceIndex;

    connect() {
        this.referenceIndex = this.collectionTarget.querySelectorAll('[data-collection-item]').length;
        if (this.sortableValue) {
            this.initSortable();
        }
    }

    addItem() {
        if (
            this.maxItemCountValue > 0
            && this.collectionTarget.querySelectorAll('[data-collection-item]').length >= this.maxItemCountValue
        ) {
            console.warn('max item count reached!');

            return;
        }

        const htmlPrototypeElement = this.collectionTarget.dataset.prototypeTemplate
            .replaceAll(this.collectionTarget.dataset.prototypeName, ++this.referenceIndex)
            .trim()
        ;

        const template = document.createElement('template');
        template.innerHTML = htmlPrototypeElement;

        this.collectionTarget.append(template.content.firstChild);

        if (this.sortableValue) {
            this.reorderAll();
        }
    }

    deleteItem(event) {
        const itemToRemove = event.target.closest('[data-collection-item]');

        if (!itemToRemove) {
            console.warn('no element found with "data-collection-item" attribute!');

            return;
        }

        itemToRemove.remove();
    }

    initSortable() {
        this.sortable = Sortable.create(this.collectionTarget, {
            handle: '[data-sortable-handler]',
            animation: 150,
            onEnd: () => this.reorderAll(),
        });
    }

    reorderAll() {
        const sortedItems = this.collectionTarget.querySelectorAll('input[data-sortable-item-rank]');

        if (0 === sortedItems.length) {
            console.log('no input found with "data-sortable-item-rank" attribute!')

            return;
        }

        let index = 1;
        this.collectionTarget.querySelectorAll('input[data-sortable-item-rank]').forEach((item) => {
            item.value = index++;
        });
    }

    disconnect() {
        if (this.sortable) {
            this.sortable.destroy();
        }
    }
}
