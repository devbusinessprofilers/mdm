import { Controller } from '@hotwired/stimulus';
import HSDatepickerModule from '@preline/datepicker';

const HSDatepicker = HSDatepickerModule.default ?? HSDatepickerModule;

// NOTE: the line below is done with 'window.HSStaticMethods.autoInit()' but we dont use 'autoInit' here => need to init manually.
window.$hsDatepickerCollection ??= [];

/**
 * Controller to initialize Preline calendar based on vanilla-calendar-pro.
 * WARNING: lodash is required to initialize calendar!
 *
 * @see https://preline.co/plugins/html/advanced-datepicker.html
 * @see https://vanilla-calendar.pro/docs/reference
 */
export default class extends Controller {
    static values = { range: Boolean };

    calendar;

    connect() {
        this.calendar = new HSDatepicker(this.element, {
            'onClickDate': (self, event) => {
                if (false === this.rangeValue) {
                    self.hide();

                    return;
                }

                // NOTE: fix bug when click on the same date twice!
                if (self.context.selectedDates[0] === undefined) {
                    self.context.selectedDates = [];

                    return;
                }

                if (self.context.selectedDates.length === 2) {
                    self.hide();
                }
            },
            'onHide': (self) => {
                if (this.rangeValue && self.context.selectedDates.length !== 2) {
                    this.element.value = '';
                    self.context.selectedDates = [];
                }
            },
        });
    }

    disconnect() {
        if (this.calendar) {
            this.calendar.destroy();
        }
    }
}
