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

  .backup-section {
    max-width: 1100px;
    margin: 24px auto 0;
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px 22px;
  }
  .backup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
  }
  .backup-header h2 { font-size: 1.2rem; }
  .backup-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
  select, button {
    font-family: inherit;
    font-size: 0.85rem;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: #23262f;
    color: var(--text);
    padding: 8px 12px;
    cursor: pointer;
  }
  button.primary { background: var(--green); color: #0f1115; font-weight: 700; border: none; }
  button.primary:hover { filter: brightness(1.1); }
  button.danger { background: rgba(231,76,60,0.15); color: var(--red); border: 1px solid rgba(231,76,60,0.3); }
  button:disabled { opacity: 0.5; cursor: not-allowed; }
  #backup-status { font-size: 0.85rem; color: var(--text-dim); margin-bottom: 12px; min-height: 1.2em; }
  table.backup-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
  table.backup-table th, table.backup-table td {
    text-align: left;
    padding: 10px 8px;
    border-bottom: 1px solid var(--border);
  }
  table.backup-table th { color: var(--text-dim); font-weight: 600; font-size: 0.8rem; }
  .row-actions { display: flex; gap: 8px; }
  .empty-msg { color: var(--text-dim); font-size: 0.85rem; padding: 12px 0; }
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

<div class="backup-section">
  <div class="backup-header">
    <h2>💾 Backup del servidor</h2>
    <div class="backup-controls">
      <label for="retention-select" style="font-size:0.85rem; color:var(--text-dim)">Mantener en el servidor:</label>
      <select id="retention-select">
        <option value="0">No conservar (se borra al crear la siguiente)</option>
        <option value="1">Última versión (1)</option>
        <option value="2">Últimas 2 versiones</option>
        <option value="3">Últimas 3 versiones</option>
        <option value="5">Últimas 5 versiones</option>
      </select>
      <button class="primary" id="backup-create-btn">Crear backup ahora</button>
    </div>
  </div>
  <div id="backup-space-warning" style="display:none; background:rgba(241,196,15,0.12); color:var(--yellow); border:1px solid rgba(241,196,15,0.3); border-radius:8px; padding:10px 14px; margin-bottom:12px; font-size:0.85rem;"></div>
  <div id="backup-status"></div>
  <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:16px;">
    <label for="webhook-input" style="font-size:0.85rem; color:var(--text-dim)">Notificar por webhook (Slack/Discord/Telegram, opcional):</label>
    <input type="text" id="webhook-input" placeholder="https://..." style="flex:1; min-width:220px; font-family:inherit; font-size:0.85rem; border-radius:8px; border:1px solid var(--border); background:#23262f; color:var(--text); padding:8px 12px;">
    <button id="webhook-save-btn">Guardar</button>
    <span id="webhook-save-msg" style="font-size:0.8rem; color:var(--text-dim)"></span>
  </div>
  <table class="backup-table" id="backup-table" style="display:none">
    <thead>
      <tr><th>Fecha</th><th>Hora</th><th>Tamaño</th><th></th></tr>
    </thead>
    <tbody id="backup-tbody"></tbody>
  </table>
  <div class="empty-msg" id="backup-empty">No hay copias guardadas en el servidor todavía.</div>
</div>

<script>
let backupKey = sessionStorage.getItem('tm_backup_key') || '';

function askBackupKey() {
  const k = prompt('Contraseña de administración de backups:');
  if (k) {
    backupKey = k;
    sessionStorage.setItem('tm_backup_key', k);
  }
  return !!k;
}

async function backupApi(action, params = {}) {
  if (!backupKey && !askBackupKey()) return null;
  const body = new URLSearchParams({ action, key: backupKey, ...params });
  const res = await fetch('backup.php', { method: 'POST', body });
  const data = await res.json();
  if (res.status === 403) {
    sessionStorage.removeItem('tm_backup_key');
    backupKey = '';
    document.getElementById('backup-status').textContent = 'Contraseña incorrecta. Vuelve a intentarlo.';
    return null;
  }
  return data;
}

let backupPollTimer = null;
let backupStartTime = null;

function renderSpaceWarning(space) {
  const el = document.getElementById('backup-space-warning');
  if (!space || !space.warning) {
    el.style.display = 'none';
    return;
  }
  el.style.display = 'block';
  el.textContent = '⚠️ ' + space.warning;
}

function renderBackups(data) {
  if (!data) return;
  if (typeof data.retention !== 'undefined') {
    document.getElementById('retention-select').value = String(data.retention);
  }
  if (typeof data.notify_webhook !== 'undefined') {
    document.getElementById('webhook-input').value = data.notify_webhook || '';
  }
  if (data.space) renderSpaceWarning(data.space);

  const tbody = document.getElementById('backup-tbody');
  const table = document.getElementById('backup-table');
  const empty = document.getElementById('backup-empty');

  tbody.innerHTML = '';
  if (!data.backups || data.backups.length === 0) {
    table.style.display = 'none';
    empty.style.display = 'block';
    return;
  }
  table.style.display = 'table';
  empty.style.display = 'none';

  for (const b of data.backups) {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${b.date}</td>
      <td>${b.time}</td>
      <td>${b.size_mb} MB</td>
      <td class="row-actions">
        <button onclick="downloadBackup('${b.filename}')">Descargar</button>
        <button class="danger" onclick="deleteBackup('${b.filename}')">Borrar</button>
      </td>`;
    tbody.appendChild(tr);
  }
}

async function loadBackups() {
  const data = await backupApi('list');
  if (data) renderBackups(data);
}

function elapsedText() {
  if (!backupStartTime) return '';
  const secs = Math.round((Date.now() - backupStartTime) / 1000);
  return ` (${secs}s transcurridos)`;
}

async function pollBackupStatus() {
  const status = await backupApi('status');
  const statusEl = document.getElementById('backup-status');
  const btn = document.getElementById('backup-create-btn');

  if (!status) return;

  if (status.state === 'running') {
    statusEl.textContent = '⏳ ' + (status.message || 'Generando backup...') + elapsedText();
    backupPollTimer = setTimeout(pollBackupStatus, 3000);
    return;
  }

  clearTimeout(backupPollTimer);
  btn.disabled = false;

  if (status.state === 'done') {
    statusEl.textContent = '✅ ' + (status.message || 'Backup completado') + elapsedText();
    renderBackups(status);
  } else if (status.state === 'error') {
    statusEl.textContent = '❌ Error: ' + (status.message || 'fallo desconocido') + elapsedText();
    renderBackups(status);
  } else {
    statusEl.textContent = '';
  }
}

async function createBackup() {
  const btn = document.getElementById('backup-create-btn');
  const status = document.getElementById('backup-status');
  btn.disabled = true;
  backupStartTime = Date.now();
  status.textContent = '⏳ Iniciando backup (webs + bases de datos)...';

  const data = await backupApi('create');

  if (!data) {
    btn.disabled = false;
    return;
  }
  if (data.error) {
    btn.disabled = false;
    status.textContent = 'Error: ' + data.error;
    return;
  }

  pollBackupStatus();
}

function downloadBackup(filename) {
  if (!backupKey && !askBackupKey()) return;
  window.location.href = 'backup.php?action=download&file=' + encodeURIComponent(filename) + '&key=' + encodeURIComponent(backupKey);
}

async function deleteBackup(filename) {
  if (!confirm('¿Borrar la copia ' + filename + '? Esta acción no se puede deshacer.')) return;
  const data = await backupApi('delete', { file: filename });
  if (data) renderBackups(data);
}

async function saveWebhook() {
  const input = document.getElementById('webhook-input');
  const msg = document.getElementById('webhook-save-msg');
  const data = await backupApi('set_notify_webhook', { webhook_url: input.value.trim() });
  if (data && data.ok) {
    msg.textContent = 'Guardado.';
    setTimeout(() => { msg.textContent = ''; }, 2500);
  } else if (data && data.error) {
    msg.textContent = data.error;
  }
}

document.getElementById('backup-create-btn').addEventListener('click', createBackup);
document.getElementById('retention-select').addEventListener('change', async (e) => {
  const data = await backupApi('set_retention', { keep: e.target.value });
  if (data) renderBackups(data);
});
document.getElementById('webhook-save-btn').addEventListener('click', saveWebhook);

loadBackups();
// Si al cargar la pagina ya habia un backup en curso (p. ej. lanzado por el
// cron automatico o desde otra pestaña), retomamos el seguimiento en vivo.
(async () => {
  const status = await backupApi('status');
  if (status && status.state === 'running') {
    document.getElementById('backup-create-btn').disabled = true;
    backupStartTime = Date.now();
    pollBackupStatus();
  }
})();

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
