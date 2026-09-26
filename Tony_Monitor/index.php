<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Monitor del Servidor</title>
<style>
  :root {
    --bg: #0f1115;
    --card-bg: #1a1d24;
    --border: #2a2e38;
    --text: #e6e8ec;
    --text-dim: #9aa0ab;
    --green: #2ecc71;
    --yellow: #f1c40f;
    --red: #e74c3c;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: var(--bg);
    color: var(--text);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    padding: 24px 16px 48px;
  }
  header {
    max-width: 1100px;
    margin: 0 auto 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
  }
  h1 { font-size: 1.6rem; font-weight: 600; }
  #last-update { color: var(--text-dim); font-size: 0.85rem; }
  .grid {
    max-width: 1100px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 16px;
  }
  .card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 18px 20px;
  }
  .card-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95rem;
    color: var(--text-dim);
    margin-bottom: 10px;
  }
  .card-value {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 10px;
  }
  .card-sub { font-size: 0.8rem; color: var(--text-dim); }
  .bar-bg {
    width: 100%;
    height: 10px;
    background: #2a2e38;
    border-radius: 6px;
    overflow: hidden;
    margin-top: 8px;
  }
  .bar-fill {
    height: 100%;
    border-radius: 6px;
    transition: width 0.5s ease, background 0.5s ease;
  }
  .status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 0.9rem;
    font-weight: 600;
  }
  .status-active { background: rgba(46,204,113,0.15); color: var(--green); }
  .status-inactive { background: rgba(231,76,60,0.15); color: var(--red); }
  .loads { display: flex; gap: 16px; margin-top: 4px; }
  .loads div { text-align: center; }
  .loads .l-value { font-size: 1.3rem; font-weight: 700; }
  .loads .l-label { font-size: 0.75rem; color: var(--text-dim); }
</style>
</head>
<body>

<header>
  <h1>🖥️ Monitor del Servidor</h1>
  <div id="last-update">Cargando...</div>
</header>

<div class="grid">

  <div class="card">
    <div class="card-title">💾 Disco</div>
    <div class="card-value" id="disk-value">--</div>
    <div class="card-sub" id="disk-sub">--</div>
    <div class="bar-bg"><div class="bar-fill" id="disk-bar" style="width:0%"></div></div>
  </div>

  <div class="card">
    <div class="card-title">🧠 RAM</div>
    <div class="card-value" id="ram-value">--</div>
    <div class="card-sub" id="ram-sub">--</div>
    <div class="bar-bg"><div class="bar-fill" id="ram-bar" style="width:0%"></div></div>
  </div>

  <div class="card">
    <div class="card-title">⚙️ CPU</div>
    <div class="card-value" id="cpu-value">--</div>
    <div class="card-sub">Uso en tiempo real</div>
    <div class="bar-bg"><div class="bar-fill" id="cpu-bar" style="width:0%"></div></div>
  </div>

  <div class="card">
    <div class="card-title">⏱️ Uptime</div>
    <div class="card-value" id="uptime-value">--</div>
    <div class="card-sub">Tiempo encendido</div>
  </div>

  <div class="card">
    <div class="card-title">🌐 Apache</div>
    <div class="card-value"><span class="status-badge" id="apache-status">--</span></div>
    <div class="card-sub">Estado del servicio</div>
  </div>

  <div class="card">
    <div class="card-title">🌡️ Carga del sistema</div>
    <div class="loads">
      <div><div class="l-value" id="load-1">--</div><div class="l-label">1 min</div></div>
      <div><div class="l-value" id="load-5">--</div><div class="l-label">5 min</div></div>
      <div><div class="l-value" id="load-15">--</div><div class="l-label">15 min</div></div>
    </div>
  </div>

  <div class="card">
    <div class="card-title">📋 Procesos activos</div>
    <div class="card-value" id="proc-value">--</div>
    <div class="card-sub">Total en ejecución</div>
  </div>

  <div class="card">
    <div class="card-title">🌐📊 Red (cuota Always Free)</div>
    <div class="card-value" id="net-value">--</div>
    <div class="card-sub" id="net-sub">--</div>
    <div class="bar-bg"><div class="bar-fill" id="net-bar" style="width:0%"></div></div>
    <div class="card-sub" style="margin-top:8px">Acumulado desde el último reinicio, no desde el ciclo de facturación</div>
  </div>

  <div class="card">
    <div class="card-title">🗄️ Pool de almacenamiento (200GB)</div>
    <div class="card-value" id="pool-value">--</div>
    <div class="card-sub" id="pool-sub">--</div>
    <div class="bar-bg"><div class="bar-fill" id="pool-bar" style="width:0%"></div></div>
    <div class="card-sub" style="margin-top:8px">Solo cuenta el volumen de arranque de esta instancia</div>
  </div>

  <div class="card" id="projection-card">
    <div class="card-title">📈 Previsión mensual de red</div>
    <div class="card-value" id="projection-value">--</div>
    <div class="card-sub" id="projection-sub">--</div>
    <div class="bar-bg"><div class="bar-fill" id="projection-bar" style="width:0%"></div></div>
    <div class="card-sub" id="projection-days" style="margin-top:8px">--</div>
    <div class="card-sub" id="projection-note" style="margin-top:4px">--</div>
  </div>

</div>

<script>
function colorForPercent(p) {
  if (p < 60) return 'var(--green)';
  if (p < 85) return 'var(--yellow)';
  return 'var(--red)';
}

function updateBar(id, percent) {
  const el = document.getElementById(id);
  el.style.width = percent + '%';
  el.style.background = colorForPercent(percent);
}

async function refresh() {
  try {
    const res = await fetch('api.php', { cache: 'no-store' });
    const data = await res.json();

    document.getElementById('disk-value').textContent = data.disk.percent + '%';
    document.getElementById('disk-sub').textContent =
      data.disk.used_gb + ' GB / ' + data.disk.total_gb + ' GB';
    updateBar('disk-bar', data.disk.percent);

    document.getElementById('ram-value').textContent = data.memory.percent + '%';
    document.getElementById('ram-sub').textContent =
      data.memory.used_mb + ' MB / ' + data.memory.total_mb + ' MB';
    updateBar('ram-bar', data.memory.percent);

    document.getElementById('cpu-value').textContent = data.cpu_percent + '%';
    updateBar('cpu-bar', data.cpu_percent);

    document.getElementById('uptime-value').textContent = data.uptime.formatted;

    const apacheEl = document.getElementById('apache-status');
    apacheEl.textContent = data.apache === 'active' ? 'Activo' : 'Inactivo';
    apacheEl.className = 'status-badge ' + (data.apache === 'active' ? 'status-active' : 'status-inactive');

    document.getElementById('load-1').textContent = data.load['1min'];
    document.getElementById('load-5').textContent = data.load['5min'];
    document.getElementById('load-15').textContent = data.load['15min'];

    document.getElementById('proc-value').textContent = data.processes;

    document.getElementById('net-value').textContent = data.network.total_gb + ' GB';
    document.getElementById('net-sub').textContent =
      '↓ ' + data.network.rx_gb + ' GB / ↑ ' + data.network.tx_gb + ' GB — cuota ' + data.network.quota_gb + ' GB/mes';
    updateBar('net-bar', data.network.percent);

    document.getElementById('pool-value').textContent = data.storage_pool.remaining_gb + ' GB libres';
    document.getElementById('pool-sub').textContent =
      data.storage_pool.this_volume_used_gb + ' GB usados / ' + data.storage_pool.pool_total_gb + ' GB del pool';
    updateBar('pool-bar', data.storage_pool.percent);

    const proj = data.projection;
    document.getElementById('projection-value').textContent = proj.monthly_gb + ' GB / mes';
    document.getElementById('projection-sub').textContent =
      'Ritmo actual: ' + proj.rate_mb_per_hour + ' MB/h — ' + proj.percent_of_quota + '% de la cuota de 10 TB';
    updateBar('projection-bar', Math.min(proj.percent_of_quota, 100));

    const daysEl = document.getElementById('projection-days');
    if (proj.days_to_exhaust === null) {
      daysEl.textContent = 'Sin tráfico detectado todavía.';
    } else if (proj.status === 'danger') {
      daysEl.textContent = '⚠️ Al ritmo actual, la cuota se agotaría en ' + proj.days_to_exhaust + ' días.';
    } else if (proj.status === 'warning') {
      daysEl.textContent = '⚠️ Consumo elevado: la cuota se agotaría en ' + proj.days_to_exhaust + ' días si se mantiene el ritmo.';
    } else {
      daysEl.textContent = 'Al ritmo actual, la cuota duraría ' + proj.days_to_exhaust + ' días (holgado).';
    }

    document.getElementById('projection-note').textContent =
      (proj.source === 'history' ? '📅 ' : '🕐 ') + proj.note;

    document.getElementById('last-update').textContent = 'Última actualización: ' + data.timestamp;
  } catch (e) {
    document.getElementById('last-update').textContent = 'Error al actualizar: ' + e.message;
  }
}

refresh();
setInterval(refresh, 30000);
</script>

</body>
</html>
