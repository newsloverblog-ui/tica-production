(function () {
  const data = document.getElementById('pt-chart-data');
  const canvas = document.getElementById('pt-chart');
  if (!data || !canvas || !window.Chart) return;

  const plot = document.querySelector('[data-traffic-plot]');
  const controls = document.querySelector('[data-traffic-options]');
  const buttons = controls.querySelectorAll('[data-traffic-chart]');
  const labels = Array.from(plot.querySelectorAll('.pt-legend li'), item => item.textContent.trim());
  const days = JSON.parse(data.textContent);
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const locale = document.documentElement.lang || undefined;
  const numbers = new Intl.NumberFormat(locale);
  let chart;

  function draw(type) {
    if (chart) chart.destroy();
    const line = type === 'line';
    chart = new Chart(canvas, {
      type,
      data: {
        labels: days.map(day => day.label),
        datasets: [
          {label: labels[0], data: days.map(day => day.views), backgroundColor: '#4669db', borderColor: '#4669db'},
          {label: labels[1], data: days.map(day => day.sessions), backgroundColor: '#142b52', borderColor: '#142b52'},
        ],
      },
      options: {
        maintainAspectRatio: false,
        legend: {display: false},
        animation: {duration: reduceMotion ? 0 : 180},
        elements: {line: {fill: false, tension: 0.2}, point: {radius: line ? 2 : 0}},
        tooltips: {mode: 'index', intersect: false},
        scales: {
          xAxes: [{gridLines: {display: false}, ticks: {maxRotation: 0, maxTicksLimit: 10, autoSkipPadding: 12, fontColor: '#526079'}}],
          yAxes: [{
            gridLines: {color: '#e3e9f3', drawBorder: false},
            ticks: {
              beginAtZero: true, suggestedMax: 1, maxTicksLimit: 5, fontColor: '#526079',
              callback: value => Number.isInteger(value) ? numbers.format(value) : '',
            },
          }],
        },
      },
    });
    buttons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.trafficChart === type)));
  }

  plot.hidden = false;
  controls.hidden = false;
  buttons.forEach(button => button.addEventListener('click', () => draw(button.dataset.trafficChart)));
  draw('bar');
})();
