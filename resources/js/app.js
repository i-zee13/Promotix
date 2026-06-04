import './bootstrap';

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import { figmaDateRangePicker } from './figma-date-range-picker';

window.Alpine = Alpine;
window.Chart = Chart;
window.figmaDateRangePicker = figmaDateRangePicker;
Alpine.data('figmaDateRangePicker', figmaDateRangePicker);

Alpine.start();
