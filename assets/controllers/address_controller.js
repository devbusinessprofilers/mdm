import { Controller } from '@hotwired/stimulus';
import { useClickOutside, useDebounce } from 'stimulus-use';
import { createPopper } from '@popperjs/core';

export default class extends Controller {
    static targets = [
        'country',
        'street',
        'zipCode',
        'city',
        'district',
        'department',
        'area',
        'position',
        'suggestionDropdown',
        'suggestionItemPrototype',
    ];

    static values = {
        autocompletePath: String,
        placeDetailsPath: String,
        nearbyDependencyWrapper: String,
    };

    popper = null;
    location = null;

    static debounces = [
        'onSearch',
    ];

    connect() {
        useDebounce(this, { wait: 800 });
        useClickOutside(this);
    }

    clickOutside() {
        this.closeSuggestionDropdown();
    }

    onCountryChange(event) {
        this.streetTarget.value = null;
        this.zipCodeTarget.value = null;
        this.cityTarget.value = null;
        this.districtTarget.value = null;
        this.departmentTarget.value = null;
        this.areaTarget.value = null;

        const coordinates = { latitude: null, longitude: null };
        this.changeMapPosition(coordinates);
    }

    async onSearch(event) {
        if (!event.params.type || !event.target.value || event.target.value.length < 3) {
            return;
        }

        const suggestions = await this.getSuggestions(event.params.type, event.target.value);
        this.displaySuggestionDropdown(event.target, suggestions);
    }

    onMapLocationChange(event) {
        if (
            null === this.nearbyDependencyWrapperValue
            || event.detail.latitude === this.location?.latitude
            || event.detail.longitude === this.location?.longitude
        ) {
            // @todo: improve comparison with a distance check (i.e. no reload if coordinates are too close to previous one)!
            return;
        }

        // NOTE: dispatch update event manually for stimulus controller "form_dependency"!
        this.element.dispatchEvent(new CustomEvent('provider-portal--form-dependency:change', {
            bubbles: true,
            detail: {
                params: { wrapperIdentifier: this.nearbyDependencyWrapperValue },
                target: {
                    type: 'computed',
                    name: 'location',
                    value: {
                        latitude: event.detail.latitude,
                        longitude: event.detail.longitude,
                    },
                },
            }
        }));
    }

    displaySuggestionDropdown(target, suggestions) {
        if (!suggestions || 0 === suggestions.length) {
            return;
        }

        this.suggestionDropdownTarget.replaceChildren();
        suggestions.forEach((suggestion) => {
            const itemElement = this.suggestionItemPrototypeTarget.firstElementChild.cloneNode(false);
            itemElement.innerHTML = suggestion.label;
            itemElement.addEventListener('click', () => {
                this.selectSuggestion(target, suggestion.placeId);
                this.closeSuggestionDropdown();
            });
            this.suggestionDropdownTarget.appendChild(itemElement);
        });

        this.suggestionDropdownTarget.classList.remove('hidden');
        this.popper = createPopper(target, this.suggestionDropdownTarget, {
            placement: 'bottom-start',
            modifiers: [
                { name: 'offset', options: { offset: [0, 4] } },
                { name: 'flip', options: { fallbackPlacements: ['top-start'] } },
            ],
        });
    }

    closeSuggestionDropdown() {
        this.suggestionDropdownTarget.replaceChildren();
        this.suggestionDropdownTarget.classList.add('hidden');

        this.popper?.destroy();
        this.popper = null;
    }

    async selectSuggestion(target, placeId) {
        const address = await this.getPlaceDetails(placeId);

        if (!address) {
            return;
        }

        this.streetTarget.value = address.street;
        this.zipCodeTarget.value = address.zipCode;
        this.cityTarget.value = address.city;
        this.departmentTarget.value = address.department;
        this.areaTarget.value = address.area;

        if (!address.position) {
            return;
        }

        const coordinates = { latitude: address.position.latitude, longitude: address.position.longitude };
        this.changeMapPosition(coordinates);
    }

    changeMapPosition({ latitude, longitude }) {
        this.location = { latitude, longitude };

        const mapController = this.application.getControllerForElementAndIdentifier(
            this.positionTarget,
            'provider-portal--map',
        );

        if (!mapController) {
            return;
        }

        mapController.updateCoordinates({ latitude, longitude });
    }

    async getSuggestions(type, value) {
        const response = await fetch(`${this.autocompletePathValue}?type=${type}&input=${value}&country=${this.countryTarget.value}`);

        if (!response.ok) {
            throw new Error('Failed to fetch suggestions');
        }

        return await response.json();
    }

    async getPlaceDetails(placeId) {
        const response = await fetch(`${this.placeDetailsPathValue}?id=${placeId}`);

        if (!response.ok) {
            throw new Error('Failed to fetch suggestions');
        }

        return await response.json();
    }
}
