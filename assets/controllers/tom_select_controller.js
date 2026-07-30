import { Controller } from '@hotwired/stimulus';
import { createPopper } from '@popperjs/core';
import TomSelect from 'tom-select';

import 'tom-select/dist/css/tom-select.default.min.css';

/**
 * @see https://tom-select.js.org/
 */
export default class extends Controller {
    static targets = ['select', 'optionPrototype', 'dropdownHeader'];
    static values = {
        withDropdownHeader: Boolean,
        withSelectAll: Boolean,
        separator: String,
        limit: Number,
    };

    tomSelect = null;
    popperInstance = null;

    connect() {
        const options = {
            maxOptions: null,
            hideSelected: false,
            dropdownParent: 'body',
            render: {
                option: (data, escape) => this.buildOption(data, escape),
            },
            onDropdownOpen: (dropdown) => {
                this.popperInstance = createPopper(this.tomSelect.wrapper, dropdown, {
                    placement: 'bottom-start',
                    modifiers: [
                        { name: 'offset', options: { offset: [0, 4] } },
                        { name: 'flip', options: { fallbackPlacements: ['top-start'] } },
                    ],
                });
            },
            onDropdownClose: () => {
                this.popperInstance?.destroy();
                this.popperInstance = null;
            },
        };

        if (this.withDropdownHeaderValue) {
            options.plugins = {
                'dropdown_header': {
                    html: () => this.dropdownHeaderTarget.innerHTML,
                }
            }
        }

        if (this.selectTarget.multiple) {
            if (this.limitValue > 0) {
                options.maxItems = this.limitValue;
            }

            options.onItemAdd = (value) => this.addOption(value);
            options.onItemRemove = (value) => this.removeOption(value);
            options.render.item = (data, escape) => this.buildItem(data, escape);
        }

        this.tomSelect = new TomSelect(this.selectTarget, options);

        if (this.selectTarget.multiple) {
            // Add a hook to update the checkbox state when an option is selected/deselected!
            this.tomSelect.hook('instead','onOptionSelect', (event, option)=> {
                const checkbox = option.querySelector('input[type="checkbox"]');

                checkbox.checked = !checkbox.checked;
                checkbox.checked ? this.addOption(option.dataset.value) : this.removeOption(option.dataset.value);
            });
        }

        const wrapper = this.element.closest('[data-dependency-target]');
        if (wrapper?.dataset.autoSelectAll === 'true') {
            delete wrapper.dataset.autoSelectAll;

            this.selectAll();
        }
    }

    buildOption(data, escape) {
        const optionNode = this.optionPrototypeTarget.firstElementChild.cloneNode(true);

        // For multiple select => update the checkbox component with a unique identifier to ensure it works properly!
        if (this.selectTarget.multiple) {
            const id = 'render-option-' + data.$id;
            const label = optionNode.querySelector('label');
            const checkbox = optionNode.querySelector('input[type="checkbox"]');

            label.setAttribute('for', id);
            checkbox.id = id;

            if (data.$option.selected) {
                checkbox.setAttribute('checked', true);
            }
        }

        optionNode.querySelector('[data-option-label]').outerHTML = escape(data.text);

        return optionNode.outerHTML;
    }

    buildItem(data, escape) {
        return '<div>' + escape(data.text) + '<span data-item-separator>' + this.separatorValue + '</span></div>';
    }

    addOption(value) {
        const item = this.tomSelect.getItem(value);
        const option = this.tomSelect.getOption(value);

        if (null === item) {
            this.tomSelect.addItem(value);
        }

        option.querySelector('input[type="checkbox"]').checked = true;

        // Need to update Poper since Tom Select recalculate dropdown position itself!
        requestAnimationFrame(() => this.popperInstance?.update());
    }

    removeOption(value) {
        const item = this.tomSelect.getItem(value);
        const option = this.tomSelect.getOption(value);

        if (null !== item) {
            this.tomSelect.removeItem(value);
        }

        option.querySelector('input[type="checkbox"]').checked = false;

        // Need to update Poper since Tom Select recalculate dropdown position itself!
        requestAnimationFrame(() => this.popperInstance?.update());
    }

    selectAll() {
        const wrapperIdentifier = this.selectTarget.getAttribute('data-provider-portal--form-dependency-wrapper-identifier-param');
        if (wrapperIdentifier) {
            const wrapper = document.querySelector(`[data-dependency-target="${wrapperIdentifier}"]`);
            if (wrapper) {
                wrapper.dataset.autoSelectAll = 'true';
            }
        }

        const values = Object.keys(this.tomSelect.options);
        values.forEach(value => {
            this.tomSelect.addItem(value, true);
            const option = this.tomSelect.getOption(value);
            if (option) {
                const checkbox = option.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    checkbox.checked = true;
                }
            }
        });

        this.selectTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }

    disconnect() {
        if (this.tomSelect) {
            this.tomSelect.destroy();
        }
    }
}
