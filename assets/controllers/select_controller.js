import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['select', 'input', 'noOptions', 'list', 'optionPrototype', 'elementPrototype'];
    static values = { choices: Array, defaultSelection: Array, multiple: Boolean, limit: Number };

    dynamicChoices = [];
    selection = [];

    connect() {
        this.selection = this.choicesValue.reduce((acc, { label, value }) => {
            if (this.defaultSelectionValue.includes(value)) {
                acc.push(label);
            }

            return acc;
        }, []);

        this.syncSelection();
    }

    defaultSelectionValueChanged() {
        this.syncSelection();
    }

    prevent(event) {
        event.preventDefault();

        if (event.type === 'click') {
            const items = this.element.querySelector('[data-dropdown-target="items"]');
            if (!items || !items.classList.contains('hidden')) {
                event.stopPropagation();
            }
        }
    }

    onSearch(event) {
        if (event.inputType === 'deleteContentBackward' || event.inputType === 'deleteContentForward') {
            // To keep input order
            const newSelection = [];
            this.noOptionsTarget.classList.add('hidden');

            const inputValue = this.inputTarget.value;
            const inputSelection = inputValue.split(', ').map((label) => label.trim());

            [...this.choicesValue, ...this.dynamicChoices].forEach(({ label, value }) => {
                const choice = this.element.querySelector(`[data-select-value-param="${value}"]`);
                choice.classList.remove('hidden');

                if (inputSelection.includes(label) && !newSelection.includes(label)) {
                    newSelection.push(label);
                } else {
                    choice.classList.remove('bg-primary-4');

                    if (this.multipleValue) {
                        const checkbox = choice.querySelector('input');
                        if (checkbox) {
                            checkbox.checked = false;
                        }
                    }
                }
            });

            this.selection = this.selection.filter((item) => newSelection.includes(item));
            this.syncSelection({ withoutFinalSpace: true });

            return;
        }

        const dropdown = this.getDropdownController();
        if (dropdown && dropdown.contentTarget.classList.contains('hidden')) {
            dropdown.toggle();
        }

        const search = this.inputTarget.value.split(' ').pop();

        const regex = new RegExp(search, 'i');
        const filterdedChoices = [];
        [...this.choicesValue, ...this.dynamicChoices].forEach(({ label, value }) => {
            if (!regex.test(label)) {
                this.element.querySelector(`[data-select-value-param="${value}"]`).classList.add('hidden');
            } else {
                this.element.querySelector(`[data-select-value-param="${value}"]`).classList.remove('hidden');
                filterdedChoices.push({ label, value });
            }
        });

        if (filterdedChoices.length === 0) {
            this.noOptionsTarget.classList.remove('hidden');
        } else {
            this.noOptionsTarget.classList.add('hidden');
        }
    }

    toggleChoice(event) {
        if (this.multipleValue && event.target.tagName !== 'INPUT') {
            return;
        }

        const params = event.params;
        const value = this.multipleValue ? this.handleMultipleChange(params, event.target) : this.handleSingleChange(params);
        if (!value) {
            return;
        }

        this.syncSelection();

        this.dispatch('change', { target: this.selectTarget, detail: { value } });
    }

    handleMultipleChange({ value, label }, input) {
        if (this.selection.includes(label)) {
            this.selection = this.selection.filter((item) => item !== label);
            this.element.querySelector(`[data-select-value-param="${value}"]`).classList.remove('bg-primary-4');

            // try to find list element not checked and not hidden
            if (!this.listTarget.querySelector('li:has(input[type="checkbox"]:checked):not(.hidden)')) {
                this.resetElementVisibility();
            }
        } else {
            if (this.limitValue > 0 && this.selection.length >= this.limitValue) {
                input.checked = false;

                return null;
            }

            this.selection.push(label);
            this.element.querySelector(`[data-select-value-param="${value}"]`).classList.add('bg-primary-4');
        }

        return value;
    }

    handleSingleChange({ value, label }) {
        this.resetElementVisibility();

        if (this.selection.includes(label)) {
            this.selection = [];
            this.element.querySelector(`[data-select-value-param="${value}"]`).classList.remove('bg-primary-4');

            return value;
        }

        this.selection = [label];
        [...this.choicesValue, ...this.dynamicChoices].forEach(({ value: choiceValue }) => {
            this.element.querySelector(`[data-select-value-param="${choiceValue}"]`)?.classList.remove('bg-primary-4');
        });

        this.element.querySelector(`[data-select-value-param="${value}"]`).classList.add('bg-primary-4');

        const dropdown = this.getDropdownController();
        if (dropdown && !dropdown.contentTarget.classList.contains('hidden')) {
            dropdown.toggle();
        }

        return value;
    }

    replaceChoices(choices) {
        this.listTarget.innerHTML = '';
        this.choicesValue = [];
        this.dynamicChoices = choices;

        if (!this.hasElementPrototypeTarget || !this.hasOptionPrototypeTarget) {
            this.noOptionsTarget.classList.toggle('hidden', choices.length > 0);
            this.syncSelection();

            return;
        }

        const parsedDefaultValues = this.defaultSelectionValue.map(value => JSON.parse(value));

        // Opti DOM inclusions (only two fragment inclusions by run)
        const selectFragment = document.createDocumentFragment();
        const listFragment = document.createDocumentFragment();

        choices.forEach((choice, index) => {
            const optionPrototype = this.optionPrototypeTarget.cloneNode(true);
            optionPrototype.innerText = choice.label;
            optionPrototype.value = choice.json;
            optionPrototype.removeAttribute('data-select-target');
            selectFragment.appendChild(optionPrototype);

            const elementPrototype = this.elementPrototypeTarget.cloneNode(true);
            const typography = elementPrototype.querySelector('span');
            if (typography) {
                typography.innerText = choice.label;
            }

            const isActive = parsedDefaultValues.some((parsed) => parsed?.value === choice.value);

            const label = elementPrototype.querySelector('label');
            if (label) {
                const checkbox = label.querySelector('input');
                if (checkbox) {
                    const uniqueIdentifier = `${this.selectTarget.id}_option_${index}`;
                    label.setAttribute('for', uniqueIdentifier);
                    checkbox.id = uniqueIdentifier;

                    if (isActive) {
                        checkbox.checked = true;
                    }
                }
            }

            elementPrototype.classList.remove('hidden');
            elementPrototype.setAttribute('data-select-value-param', choice.value);
            elementPrototype.setAttribute('data-select-label-param', choice.label);
            elementPrototype.removeAttribute('data-select-target');
            if (isActive) {
                elementPrototype.classList.add('bg-primary-4');
            }
            listFragment.appendChild(elementPrototype);
        });

        this.selectTarget.appendChild(selectFragment);
        this.listTarget.appendChild(listFragment);

        this.noOptionsTarget.classList.toggle('hidden', choices.length > 0);

        this.selection = this.dynamicChoices.reduce((acc, { label, value }) => {
            if (parsedDefaultValues.some((parsed) => parsed?.value === value)) {
                acc.push(label);
            }

            return acc;
        }, []);

        this.syncSelection();
    }

    resetElementVisibility() {
        const listElements = this.listTarget.querySelectorAll('li');
        listElements.forEach((listElement) => {
            listElement.classList.toggle('hidden', this.choicesValue.length === 0);
        });
    }

    syncSelection(options = {}) {
        // sync real select input
        const selectOptions = this.selectTarget.querySelectorAll('option')
        selectOptions.forEach((selectOption) => {
            const choice = this.choicesValue.find(({ value }) => value === selectOption.value)
                || this.dynamicChoices.find(({ json }) => json === selectOption.value);
            selectOption.selected = this.selection.includes(choice?.label);
        });

        if (!this.hasInputTarget) {
            return;
        }

        // sync trigger input
        // Add a space at the end to prepare user search
        this.inputTarget.value = `${this.selection.join(', ')}${this.selection.length > 0 && !options.withoutFinalSpace ? ' ' : ''}`;
    }

    getDropdownController() {
        return this.application.getControllerForElementAndIdentifier(
            this.element.querySelector('[data-controller=dropdown]'),
            'dropdown'
        );
    }

    disconnect() {}
}
