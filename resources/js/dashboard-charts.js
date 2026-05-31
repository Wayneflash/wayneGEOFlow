import { LineChart } from 'echarts/charts';
import { GridComponent, TooltipComponent } from 'echarts/components';
import { graphic, init, use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';

use([GridComponent, LineChart, TooltipComponent, CanvasRenderer]);

window.echarts = { graphic, init };
window.dispatchEvent(new CustomEvent('dashboard:charts-ready'));
