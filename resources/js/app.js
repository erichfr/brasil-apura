import './bootstrap';
import { Chart, DoughnutController, ArcElement, Tooltip } from 'chart.js';
setupDeveloperDialog();
Chart.register(DoughnutController, ArcElement, Tooltip);
let charts = [];
function renderCharts() {
    charts.forEach(chart => chart.destroy());
    charts = [];
    document.querySelectorAll('.composition-chart').forEach(canvas => {
        const values = JSON.parse(canvas.dataset.values);
        charts.push(new Chart(canvas, {
            type: 'doughnut',
            data: { labels: ['Válidos', 'Brancos', 'Nulos', 'Outras destinações'], datasets: [{ data: values, backgroundColor: ['#67d9cb','#92a5ff','#b4bfd0','#f1c57a'], borderWidth: 0, hoverOffset: 2 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '76%', animation: false, plugins: { tooltip: { callbacks: { label: context => context.label + ': ' + new Intl.NumberFormat('pt-BR').format(context.raw) } } } }
        }));
    });
}
renderCharts();
const container = document.getElementById('resultados');
const status = document.getElementById('refresh-status');
let lastVersion = null;
let refreshing = false;
async function refresh() {
    if (!container || document.hidden || refreshing) return;
    refreshing = true;
    try {
        const response = await fetch(container.dataset.api, { headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(15000) });
        if (!response.ok) throw new Error('Falha na consulta local');
        const payload = await response.json();
        const version = JSON.stringify([payload.result.data?.snapshot_id, payload.result.status, payload.result.last_success_at]);
        if (lastVersion !== version && !container.contains(document.activeElement)) {
            const opened = container.querySelector('.source-details')?.open;
            container.innerHTML = payload.html;
            if (opened) container.querySelector('.source-details').open = true;
            renderCharts();
            setupWorldMap();
            lastVersion = version;
        }
        status.textContent = 'Dados locais conferidos · 60 s';
    } catch {
        status.textContent = 'Falha na consulta. Tentaremos novamente.';
    } finally {
        refreshing = false;
    }
}
setInterval(refresh, 60000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
document.querySelector('.filters')?.addEventListener('submit', () => {
    const country = document.querySelector('.filters input[name=country]');
    if (country && document.getElementById('scope').value !== 'zz') country.disabled = true;
    container.setAttribute('aria-busy', 'true');
    status.textContent = 'Carregando resultados…';
});


function setupWorldMap() {
    const svg = document.getElementById('world-map');
    const select = document.getElementById('world-country');
    if (!svg || !select || svg.dataset.ready) return;
    svg.dataset.ready = 'true';
    const readout = document.querySelector('.world-country-name');
    const paths = [...svg.querySelectorAll('[data-country]')];
    const selected = paths.find(path => path.dataset.country === svg.dataset.selected);
    const baseText = selected ? selected.dataset.name : 'Clique em um país destacado ou use a lista.';
    paths.forEach(country => {
        country.addEventListener('pointerenter', () => {
            readout.textContent = country.dataset.name + (country.classList.contains('has-results') ? ' · ver votação' : ' · sem recorte cadastrado');
        });
    });
    svg.addEventListener('pointerleave', () => { readout.textContent = baseText; });
    select.addEventListener('change', () => {
        readout.textContent = 'Carregando votação…';
        select.form.requestSubmit();
    });
}
setupWorldMap();

function setupDeveloperDialog() {
    const dialog = document.getElementById('developer-dialog');
    const reopen = document.querySelector('[data-open-developer]');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    const storageKey = 'brasil-apura:developer-intro:v1';
    const open = () => {
        if (!dialog.open) {
            dialog.showModal();
            document.documentElement.classList.add('developer-modal-open');
        }
    };
    dialog.addEventListener('close', () => {
        document.documentElement.classList.remove('developer-modal-open');
        try { sessionStorage.setItem(storageKey, 'dismissed'); } catch { /* Storage is optional. */ }
    });
    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
    });
    reopen?.addEventListener('click', open);
    let dismissed = false;
    try { dismissed = sessionStorage.getItem(storageKey) === 'dismissed'; } catch { /* Keep the introduction accessible without storage. */ }
    if (!dismissed) open();
}
