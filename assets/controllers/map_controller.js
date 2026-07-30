import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['latitude', 'longitude', 'map'];

    connect() {
        this.mapTarget.addEventListener('ux:map:marker:before-create', this.onMarkerBeforeCreate.bind(this));
        this.mapTarget.addEventListener('ux:map:marker:after-create', this.onMarkerAfterCreate.bind(this));
    }

    onMarkerBeforeCreate(event) {
        event.detail.definition.bridgeOptions = {
            gmpDraggable: true,
        };
    }

    onMarkerAfterCreate(event) {
        const marker = event.detail.marker;
        marker.addListener('dragend', (mapEvent) => {
            const position = mapEvent.latLng;

            this.latitudeTarget.value = position.lat();
            this.longitudeTarget.value = position.lng();

            this.dispatchChangeEvent();
        });
    }

    updateCoordinates({ latitude, longitude }) {
        this.latitudeTarget.value = latitude;
        this.longitudeTarget.value = longitude;

        this.callLiveUpdate(latitude, longitude);
        this.dispatchChangeEvent();
    }

    getCoordinates() {
        return {
            latitude: this.latitudeTarget.value,
            longitude: this.longitudeTarget.value,
        };
    }

    callLiveUpdate(latitude, longitude) {
        const component = this.mapTarget.__component;
        if (component) {
            component.action('onUpdate', {
                latitude: latitude,
                longitude: longitude
            });
        }
    }

    dispatchChangeEvent() {
        this.dispatch('location', { detail: { latitude: this.latitudeTarget.value, longitude: this.longitudeTarget.value } });
    }

    disconnect() {
        this.mapTarget.removeEventListener('ux:map:marker:before-create', this.onMarkerBeforeCreate);
        this.mapTarget.removeEventListener('ux:map:marker:after-create', this.onMarkerAfterCreate);
    }
}
