<?php
// Funciones compartidas de gestion de backups: las usan tanto el endpoint
// web (backup.php, disparado por el boton) como el cron automatico
// (cron/scheduled_backup.php), para no duplicar la logica de retencion,
// listado ni notificaciones.

define('BACKUP_DIR', '/var/backups/tonymonitor');
define('RETENTION_FILE', __DIR__ . '/../config/backup_retention.json');
define('NOTIFY_FILE', __DIR__ . '/../config/backup_notify.json');
define('STATUS_FILE', BACKUP_DIR . '/status.json');
define('BACKUP_FILENAME_PATTERN', '/^backup_TonyMonitor_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.tar\.gz$/');
define('STORAGE_POOL_TOTAL_GB', 200); // pool Always Free de Block Storage

function get_retention() {
    if (file_exists(RETENTION_FILE)) {
        $data = json_decode(file_get_contents(RETENTION_FILE), true);
        if (isset($data['keep']) && is_int($data['keep'])) {
            return $data['keep'];
        }
    }
    return 2; // por defecto: mantener las 2 ultimas versiones, rotando
}

function set_retention($keep) {
    file_put_contents(RETENTION_FILE, json_encode(['keep' => $keep]));
}

function list_backups() {
    $files = glob(BACKUP_DIR . '/backup_TonyMonitor_*.tar.gz') ?: [];
    $result = [];

    foreach ($files as $path) {
        $name = basename($path);
        if (!preg_match(BACKUP_FILENAME_PATTERN, $name)) continue;

        preg_match('/backup_TonyMonitor_(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2}-\d{2})\.tar\.gz/', $name, $m);
        $result[] = [
            'filename' => $name,
            'date' => $m[1],
            'time' => str_replace('-', ':', $m[2]),
            'size_mb' => round(filesize($path) / 1048576, 1),
            'created_at' => filemtime($path),
        ];
    }

    usort($result, fn($a, $b) => $b['created_at'] <=> $a['created_at']);
    return $result;
}

function apply_retention($keep) {
    $backups = list_backups(); // ya viene ordenado de mas reciente a mas antiguo
    if ($keep <= 0) {
        foreach ($backups as $b) {
            @unlink(BACKUP_DIR . '/' . $b['filename']);
        }
        return;
    }
    foreach (array_slice($backups, $keep) as $b) {
        @unlink(BACKUP_DIR . '/' . $b['filename']);
    }
}

function get_notify_webhook() {
    if (file_exists(NOTIFY_FILE)) {
        $data = json_decode(file_get_contents(NOTIFY_FILE), true);
        if (isset($data['webhook_url'])) {
            return $data['webhook_url'];
        }
    }
    return '';
}

function set_notify_webhook($url) {
    // JSON_UNESCAPED_SLASHES: sin este flag, json_encode convierte "/" en
    // "\/", y el backup.sh (que lee este archivo con grep/sed, no con un
    // parser JSON real) tomaria esas barras invertidas como parte literal
    // de la URL, rompiendo la llamada a curl.
    file_put_contents(NOTIFY_FILE, json_encode(['webhook_url' => $url], JSON_UNESCAPED_SLASHES));
}

function get_status() {
    if (!file_exists(STATUS_FILE)) {
        return ['state' => 'idle'];
    }
    $data = json_decode(file_get_contents(STATUS_FILE), true);
    return is_array($data) ? $data : ['state' => 'idle'];
}

// Calcula si la retencion actual + el tamaño de las copias ya guardadas se
// acerca al limite del pool de 200GB Always Free, para avisar antes de que
// un backup falle por falta de espacio.
function get_space_summary() {
    $total = disk_total_space('/');
    $free = disk_free_space('/');
    $usedGb = round(($total - $free) / 1073741824, 1);
    $poolTotalGb = STORAGE_POOL_TOTAL_GB;
    $percent = round(($usedGb / $poolTotalGb) * 100, 1);

    $backups = list_backups();
    $backupsTotalGb = round(array_sum(array_column($backups, 'size_mb')) / 1024, 2);

    $warning = null;
    if ($percent >= 90) {
        $warning = 'Critico: el disco usa ' . $percent . '% del pool de ' . $poolTotalGb . 'GB. Borra copias o baja la retencion antes de que falle el proximo backup.';
    } elseif ($percent >= 75) {
        $warning = 'Aviso: el disco usa ' . $percent . '% del pool de ' . $poolTotalGb . 'GB (los backups guardados ocupan ' . $backupsTotalGb . ' GB). Vigila el espacio disponible.';
    }

    return [
        'disk_used_gb' => $usedGb,
        'pool_total_gb' => $poolTotalGb,
        'percent' => $percent,
        'backups_total_gb' => $backupsTotalGb,
        'warning' => $warning,
    ];
}
