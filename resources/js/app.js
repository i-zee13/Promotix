import './bootstrap';

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import ApexCharts from 'apexcharts';
import { figmaDateRangePicker } from './figma-date-range-picker';

window.Alpine = Alpine;
window.Chart = Chart;
window.ApexCharts = ApexCharts;
window.figmaDateRangePicker = figmaDateRangePicker;
Alpine.data('figmaDateRangePicker', figmaDateRangePicker);

Alpine.start();
