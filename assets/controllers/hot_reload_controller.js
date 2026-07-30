import { Controller } from '@hotwired/stimulus';
import { Idiomorph } from 'idiomorph';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    connect() {
        window.Idiomorph = Idiomorph;
        import('frankenphp-hot-reload');
    }
}
