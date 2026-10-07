import './bootstrap';

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import ApexCharts from 'apexcharts';
import { figmaDateRangePicker } from './figma-date-range-picker';
import { registerGeoAudiencePicker } from './geo-audience-picker';
import { registerSortableTable } from './sortable-table';
import { PromotixDomainFilter } from './promotix-domain-filter';

window.Alpine = Alpine;
window.Chart = Chart;
window.ApexCharts = ApexCharts;
window.figmaDateRangePicker = figmaDateRangePicker;
window.PromotixDomainFilter = PromotixDomainFilter;
Alpine.data('figmaDateRangePicker', figmaDateRangePicker);
registerGeoAudiencePicker(Alpine);
registerSortableTable(Alpine);

Alpine.start();
