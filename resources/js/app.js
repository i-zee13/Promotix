import './bootstrap';

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import ApexCharts from 'apexcharts';
import { figmaDateRangePicker } from './figma-date-range-picker';
import { registerGeoAudiencePicker } from './geo-audience-picker';
import { registerSortableTable } from './sortable-table';

window.Alpine = Alpine;
window.Chart = Chart;
window.ApexCharts = ApexCharts;
window.figmaDateRangePicker = figmaDateRangePicker;
Alpine.data('figmaDateRangePicker', figmaDateRangePicker);
registerGeoAudiencePicker(Alpine);
registerSortableTable(Alpine);

Alpine.start();
