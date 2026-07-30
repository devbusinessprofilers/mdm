import { Controller } from '@hotwired/stimulus';
import gaugeJS from 'gaugeJS';

// No named export => use gaugeJS instead { Gauge, Donut, BaseDonut, ... }
const { Gauge } = gaugeJS;

export default class extends Controller {
    static targets = ['canvas'];
    static values = {
        min: Number,
        max: Number,
        ranges: Array,
        current: Number,
    };

    connect() {
        const gauge = new Gauge(this.canvasTarget);

        const labels = this.rangesValue.reduce((acc, { start, end }) => {
            if (!acc.includes(start)) {
                acc.push(start)
            }

            if (!acc.includes(end)) {
                acc.push(end)
            }

            return acc;
        }, [])

        gauge.setOptions({
            angle: -0.30,
            lineWidth: 0.2,
            radiusScale: 0.90,
            pointer: {
                length: 0.5,
                strokeWidth: 0.2,
                color: '#FFFFFF',
            },
            limitMin: true,
            limitMax: true,
            staticZones: this.rangesValue.map(({ start, end, color }) => ({
                strokeStyle: color,
                min: start,
                max: end,
            })),
            staticLabels: {
                font: '14px Lato',
                labels: labels,
            },
        });

        gauge.minValue = this.minValue;
        gauge.maxValue = this.maxValue;
        gauge.animationSpeed = 1;
        gauge.set(this.currentValue);
    }
}
