(function () {
  const panel = document.querySelector('[data-traffic-url]');
  if (!panel) return;

  const status = panel.querySelector('[data-traffic-status]');
  const history = panel.querySelector('[data-traffic-history]');
  const series = history.querySelector('ol');
  const updated = history.querySelector('[data-traffic-updated]');
  const metrics = panel.querySelectorAll('[data-traffic-stat]');
  const locale = document.documentElement.lang || undefined;
  const numbers = new Intl.NumberFormat(locale);
  const time = new Intl.DateTimeFormat(locale, {hour: '2-digit', minute: '2-digit', hourCycle: 'h23'});
  const freshTime = new Intl.DateTimeFormat(locale, {hour: '2-digit', minute: '2-digit', second: '2-digit'});
  let timer;
  let pending = false;
  let expired = false;

  function render(data) {
    const maxViews = Math.max(1, ...data.windows.map(window => window.views));
    const bars = data.windows.map(({start, end, views}) => {
      const item = document.createElement('li');
      item.style.setProperty('--bar-height', (views / maxViews * 100) + '%');
      item.title = time.format(new Date(start)) + '–' + time.format(new Date(end));

      const value = document.createElement('strong');
      value.textContent = numbers.format(views);
      const bar = document.createElement('div');
      bar.className = 'cd-traffic-bar';
      bar.setAttribute('aria-hidden', 'true');
      const label = document.createElement('time');
      label.dateTime = end;
      label.textContent = time.format(new Date(end));
      label.setAttribute('aria-label', item.title);
      item.append(value, bar, label);
      return item;
    });

    metrics.forEach(metric => {
      metric.textContent = numbers.format(data[metric.dataset.trafficStat]);
    });
    series.replaceChildren(...bars);
    updated.dateTime = data.updated_at;
    updated.textContent = freshTime.format(new Date(data.updated_at));
    history.hidden = false;
    status.hidden = true;
  }

  async function refresh() {
    clearTimeout(timer);
    if (pending || expired || document.hidden) return;

    pending = true;
    const request = new AbortController();
    const timeout = setTimeout(() => request.abort(), 10000);
    try {
      const response = await fetch(panel.dataset.trafficUrl, {
        headers: {Accept: 'application/json'},
        cache: 'no-store',
        signal: request.signal,
      });
      if (response.redirected || response.status === 401 || response.status === 403) {
        expired = true;
        status.textContent = panel.dataset.trafficAuth;
        status.hidden = false;
        return;
      }
      if (!response.ok) throw new Error('Traffic data unavailable');
      render(await response.json());
    } catch (error) {
      status.textContent = panel.dataset.trafficError;
      status.hidden = false;
    } finally {
      clearTimeout(timeout);
      pending = false;
      if (!expired && !document.hidden) timer = setTimeout(refresh, 15000);
    }
  }

  document.addEventListener('visibilitychange', refresh);
  refresh();
})();
