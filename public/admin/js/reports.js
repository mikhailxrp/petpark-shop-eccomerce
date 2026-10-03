// Графики страницы /admin/reports. Цифры и таблицы уже есть в HTML —
// графики только дополняют; нет Chart.js или данных — страница работает без них.

const DATA_ELEMENT_ID = 'report-data';
const REVENUE_CHART_ID = 'report-revenue-chart';
const SERVICES_CHART_ID = 'report-services-chart';
const REVENUE_COLOR = '#845adf';
const SERVICES_COLORS = ['#23b7e5', '#26bf94', '#f5b849'];

const readData = () => {
    const element = document.getElementById(DATA_ELEMENT_ID);
    if (element === null) {
        return null;
    }
    try {
        return JSON.parse(element.textContent);
    } catch (error) {
        console.error('reports: не удалось прочитать данные графиков', error);
        return null;
    }
};

const baseOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true } },
};

const drawBar = (canvasId, labels, label, values, colors) => {
    const canvas = document.getElementById(canvasId);
    if (canvas === null) {
        return;
    }
    new window.Chart(canvas, {
        type: 'bar',
        data: { labels, datasets: [{ label, data: values, backgroundColor: colors }] },
        options: baseOptions,
    });
};

const init = () => {
    const data = readData();
    if (data === null || typeof window.Chart === 'undefined') {
        return;
    }
    drawBar(REVENUE_CHART_ID, data.days, 'Выручка, ₽', data.revenue, REVENUE_COLOR);
    drawBar(SERVICES_CHART_ID, data.services.labels, 'Сумма, ₽', data.services.revenue, SERVICES_COLORS);
};

init();
