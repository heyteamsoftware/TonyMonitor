<?php
// Snapshot diario del trafico de red acumulado. Pensado para ejecutarse por
// cron (php-cli), no accesible desde el navegador.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Forbidden');
}

$historyFile = __DIR__ . '/../data/history.json';

function read_network_total_gb() {
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

    return round(($rxBytes + $txBytes) / 1073741824, 4);
}

$currentTotalGb = read_network_total_gb();
$today = date('Y-m-d');
$currentMonth = date('Y-m');

$history = [
    'month' => $currentMonth,
    'accumulated_gb' => 0,
    'last_total_gb' => $currentTotalGb,
    'last_check' => date('Y-m-d H:i:s'),
    'daily' => [],
];

if (file_exists($historyFile)) {
    $decoded = json_decode(file_get_contents($historyFile), true);
    if (is_array($decoded)) {
        $history = array_merge($history, $decoded);
    }
}

// Nuevo mes: reiniciar acumulado y log diario, conservando solo el ultimo
// total conocido para poder calcular el delta del primer dia.
if ($history['month'] !== $currentMonth) {
    $history['month'] = $currentMonth;
    $history['accumulated_gb'] = 0;
    $history['daily'] = [];
}

$delta = $currentTotalGb - $history['last_total_gb'];
// Un delta negativo significa que el servidor se reinicio y el contador de
// /proc/net/dev volvio a cero: contamos el total actual como trafico nuevo.
if ($delta < 0) {
    $delta = $currentTotalGb;
}

$history['accumulated_gb'] = round($history['accumulated_gb'] + $delta, 4);
$history['last_total_gb'] = $currentTotalGb;
$history['last_check'] = date('Y-m-d H:i:s');

// Evitar duplicar la entrada si el cron se ejecuta mas de una vez el mismo dia.
$found = false;
foreach ($history['daily'] as &$entry) {
    if ($entry['date'] === $today) {
        $entry['delta_gb'] = round($entry['delta_gb'] + $delta, 4);
        $found = true;
        break;
    }
}
unset($entry);

if (!$found) {
    $history['daily'][] = ['date' => $today, 'delta_gb' => round($delta, 4)];
}

// Mantener como maximo los ultimos 60 dias.
if (count($history['daily']) > 60) {
    $history['daily'] = array_slice($history['daily'], -60);
}

file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT));
echo "OK: " . $today . " delta=" . round($delta, 4) . "GB acumulado_mes=" . $history['accumulated_gb'] . "GB\n";
