import { startStimulusApp } from '@symfony/stimulus-bundle';
import { configureChart } from './scripts/chart.js';

// Import lodash as global (e.g. required for Preline calendar).
import lodash from 'lodash';
window._ = lodash;

startStimulusApp();
configureChart();
