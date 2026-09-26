<?php
header('Content-Type: application/json; charset=utf-8');

function get_cpu_usage() {
    $stat1 = file('/proc/stat');
    $cpu1 = preg_split('/\s+/', trim($stat1[0]));
    usleep(200000);
    $stat2 = file('/proc/stat');
    $cpu2 = preg_split('/\s+/', trim($stat2[0]));

    $idle1 = $cpu1[4] + $cpu1[5];
    $idle2 = $cpu2[4] + $cpu2[5];

    $total1 = 0;
    $total2 = 0;
    for ($i = 1; $i <= 7; $i++) {
        $total1 += isset($cpu1[$i]) ? $cpu1[$i] : 0;
        $total2 += isset($cpu2[$i]) ? $cpu2[$i] : 0;
    }

    $totalDiff = $total2 - $total1;
    $idleDiff = $idle2 - $idle1;

    if ($totalDiff == 0) return 0;
    $usage = (1 - $idleDiff / $totalDiff) * 100;
    return round($usage, 1);
}

function get_memory() {
    $meminfo = file_get_contents('/proc/meminfo');
    preg_match('/MemTotal:\s+(\d+)/', $meminfo, $mTotal);
    preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $mAvail);

    $totalKb = isset($mTotal[1]) ? (float)$mTotal[1] : 0;
    $availKb = isset($mAvail[1]) ? (float)$mAvail[1] : 0;
    $usedKb = $totalKb - $availKb;

    return [
        'total_mb' => round($totalKb / 1024, 1),
        'used_mb' => round($usedKb / 1024, 1),
        'free_mb' => round($availKb / 1024, 1),
        'percent' => $totalKb > 0 ? round(($usedKb / $totalKb) * 100, 1) : 0,
    ];
}

function get_disk() {
    $path = '/';
    $total = disk_total_space($path);
    $free = disk_free_space($path);
    $used = $total - $free;

    return [
        'total_gb' => round($total / 1073741824, 1),
        'used_gb' => round($used / 1073741824, 1),
        'free_gb' => round($free / 1073741824, 1),
        'percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
    ];
}

function get_uptime() {
    $uptime = file_get_contents('/proc/uptime');
    $seconds = (float)explode(' ', $uptime)[0];

    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);

    return [
        'seconds' => (int)$seconds,
        'formatted' => "{$days}d {$hours}h {$minutes}m",
    ];
}

function get_loadavg() {
    $load = file_get_contents('/proc/loadavg');
    $parts = explode(' ', trim($load));
    return [
        '1min' => (float)$parts[0],
        '5min' => (float)$parts[1],
        '15min' => (float)$parts[2],
    ];
}

function get_processes() {
    $load = file_get_contents('/proc/loadavg');
    $parts = explode(' ', trim($load));
    if (isset($parts[3])) {
        $procs = explode('/', $parts[3]);
        return isset($procs[1]) ? (int)$procs[1] : 0;
    }
    return 0;
}

function get_apache_status() {
    $status = trim(shell_exec('systemctl is-active apache2 2>/dev/null'));
    if ($status === '') {
        $status = trim(shell_exec('systemctl is-active httpd 2>/dev/null'));
    }
    return $status === 'active' ? 'active' : 'inactive';
}

function get_network() {
    $dev = file('/proc/net/dev');
    $rxBytes = 0;
    $txBytes = 0;

    foreach ($dev as $line) {
        if (strpos($line, ':') === false) continue;
        list($iface, $data) = explode(':', $line, 2);
        $iface = trim($iface);
        if ($iface === 'lo') continue;

        $fields = preg_split('/\s+/', trim($data));
        $rxBytes += (float)$fields[0];
        $txBytes += (float)$fields[8];
    }

    $totalBytes = $rxBytes + $txBytes;
    $quotaGb = 10240; // 10 TB/mes incluidos en Always Free
    $totalGb = round($totalBytes / 1073741824, 2);

    return [
        'rx_gb' => round($rxBytes / 1073741824, 2),
        'tx_gb' => round($txBytes / 1073741824, 2),
        'total_gb' => $totalGb,
        'quota_gb' => $quotaGb,
        'percent' => round(($totalGb / $quotaGb) * 100, 2),
        'note' => 'Acumulado desde el ultimo reinicio del servidor, no desde el inicio del ciclo de facturacion. Verifica el consumo mensual real en la consola de OCI.',
    ];
}

function get_projection_from_uptime($networkTotalGb, $networkQuotaGb, $uptimeSeconds) {
    $secondsInMonth = 30 * 86400;

    if ($uptimeSeconds <= 0) {
        return [
            'monthly_gb' => 0,
            'percent_of_quota' => 0,
            'days_to_exhaust' => null,
            'rate_mb_per_hour' => 0,
            'status' => 'ok',
            'source' => 'uptime',
            'note' => 'Sin datos suficientes todavia (el servidor acaba de reiniciar).',
        ];
    }

    $ratePerSecond = $networkTotalGb / $uptimeSeconds;
    $monthlyGb = round($ratePerSecond * $secondsInMonth, 2);
    $rateMbPerHour = round(($ratePerSecond * 3600) * 1024, 1);
    $percentOfQuota = round(($monthlyGb / $networkQuotaGb) * 100, 2);

    $daysToExhaust = $ratePerSecond > 0 ? round(($networkQuotaGb / $ratePerSecond) / 86400, 1) : null;

    return [
        'monthly_gb' => $monthlyGb,
        'percent_of_quota' => $percentOfQuota,
        'days_to_exhaust' => $daysToExhaust,
        'rate_mb_per_hour' => $rateMbPerHour,
        'status' => $percentOfQuota >= 100 ? 'danger' : ($percentOfQuota >= 70 ? 'warning' : 'ok'),
        'source' => 'uptime',
        'note' => 'Proyeccion basada solo en el ritmo desde el ultimo reinicio (' . round($uptimeSeconds / 3600, 1) . ' horas de datos). Aun no hay historico diario suficiente; se afinara cuando el cron acumule varios dias.',
    ];
}

function get_projection($networkTotalGb, $networkQuotaGb, $uptimeSeconds) {
    $historyFile = __DIR__ . '/data/history.json';

    if (!file_exists($historyFile)) {
        return get_projection_from_uptime($networkTotalGb, $networkQuotaGb, $uptimeSeconds);
    }

    $history = json_decode(file_get_contents($historyFile), true);
    if (!is_array($history) || empty($history['daily'])) {
        return get_projection_from_uptime($networkTotalGb, $networkQuotaGb, $uptimeSeconds);
    }

    $today = date('Y-m-d');
    $dayOfMonth = (int)date('j');
    $daysInMonth = (int)date('t');

    // Trafico ya confirmado por el cron hasta el ultimo snapshot, mas lo que
    // ha crecido el contador desde entonces (sesion actual desde el ultimo check).
    $accumulatedGb = isset($history['accumulated_gb']) ? (float)$history['accumulated_gb'] : 0;
    $lastTotalGb = isset($history['last_total_gb']) ? (float)$history['last_total_gb'] : $networkTotalGb;
    $sinceLastCheckGb = $networkTotalGb - $lastTotalGb;
    if ($sinceLastCheckGb < 0) $sinceLastCheckGb = 0; // reinicio reciente, ya lo contara el proximo cron

    $monthToDateGb = round($accumulatedGb + $sinceLastCheckGb, 3);
    $completedDays = count($history['daily']);

    if ($dayOfMonth <= 0) $dayOfMonth = 1;
    $monthlyGb = round(($monthToDateGb / $dayOfMonth) * $daysInMonth, 2);
    $rateMbPerHour = round(($monthToDateGb / $dayOfMonth / 24) * 1024, 1);
    $percentOfQuota = round(($monthlyGb / $networkQuotaGb) * 100, 2);

    $dailyAvgGb = $monthToDateGb / $dayOfMonth;
    $daysToExhaust = $dailyAvgGb > 0 ? round($networkQuotaGb / $dailyAvgGb, 1) : null;

    return [
        'monthly_gb' => $monthlyGb,
        'percent_of_quota' => $percentOfQuota,
        'days_to_exhaust' => $daysToExhaust,
        'rate_mb_per_hour' => $rateMbPerHour,
        'month_to_date_gb' => $monthToDateGb,
        'days_with_data' => $completedDays,
        'status' => $percentOfQuota >= 100 ? 'danger' : ($percentOfQuota >= 70 ? 'warning' : 'ok'),
        'source' => 'history',
        'note' => 'Proyeccion basada en ' . $completedDays . ' dia(s) de historico real acumulado este mes (' . $monthToDateGb . ' GB hasta hoy, dia ' . $dayOfMonth . ' de ' . $daysInMonth . ').',
    ];
}

function get_storage_pool() {
    // Pool Always Free de Block Storage: 200 GB totales compartidos entre todos los volumenes de la cuenta.
    $poolTotalGb = 200;
    $disk = get_disk();
    $usedGb = $disk['used_gb'];
    $remainingGb = round($poolTotalGb - $usedGb, 1);

    return [
        'pool_total_gb' => $poolTotalGb,
        'this_volume_used_gb' => $usedGb,
        'remaining_gb' => $remainingGb,
        'percent' => round(($usedGb / $poolTotalGb) * 100, 1),
        'note' => 'Calculado solo con el volumen de arranque de esta instancia. Si tienes otros volumenes o instancias, restalos tambien del pool de 200GB.',
    ];
}

$uptime = get_uptime();
$network = get_network();

echo json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'disk' => get_disk(),
    'memory' => get_memory(),
    'cpu_percent' => get_cpu_usage(),
    'uptime' => $uptime,
    'apache' => get_apache_status(),
    'load' => get_loadavg(),
    'processes' => get_processes(),
    'network' => $network,
    'projection' => get_projection($network['total_gb'], $network['quota_gb'], $uptime['seconds']),
    'storage_pool' => get_storage_pool(),
], JSON_PRETTY_PRINT);
