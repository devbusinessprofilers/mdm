import ChartDataLabels from 'chartjs-plugin-datalabels';

export const configureChart = () => {
    addDataLabelPlugin();
    processSuffixOptions();
};

const addDataLabelPlugin = () => {
    // Add chartjs-plugin-datalabels but disable it per default
    document.addEventListener('chartjs:init', (event) => {
        const Chart = event.detail.Chart;
        Chart.register(ChartDataLabels);
        Chart.defaults.set('plugins.datalabels', { display: false });
    });
};

/**
 * Custom suffix option available through chart options:
 * - plugins > datalabels > suffix (to add suffix on data labels in graph)
 * - scales > [x|y] > ticks > suffix (to add suffix on axis labels X or Y)
 */
const processSuffixOptions = () => {
    // Check custom "suffix" option and add formatter to add this suffix in both data (labels inside graph) and axis (X/Y scale labels).
    document.addEventListener('chartjs:pre-connect', (event) => {
        const options = event.detail.options;

        // Suffix option for data (labels inside graph)
        const datalabels = options?.plugins?.datalabels;
        if (datalabels?.suffix) {
            const suffix = datalabels.suffix;
            delete datalabels.suffix;
            datalabels.formatter = (value) => `${value}${suffix}`;
        }

        // Suffix option for axis (scale labels)
        for (const scale of Object.values(options?.scales ?? {})) {
            if (scale.ticks?.suffix) {
                const suffix = scale.ticks.suffix;
                delete scale.ticks.suffix;
                scale.ticks.callback = (value) => `${value}${suffix}`;
            }
        }
    });
};