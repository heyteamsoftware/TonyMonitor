<?php
// Backup automatico programado (cron semanal, ejecutado como root: no
// necesita el sudoers especial que usa el boton manual). Reutiliza la misma
// logica de retencion que el panel web para no dejar duplicados.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Forbidden');
}

require __DIR__ . '/../inc/backup_functions.php';

// Igual que el boton manual: se limpian los excedentes antes de generar el
// nuevo backup, asi el recien creado siempre queda disponible.
apply_retention(get_retention());

$script = __DIR__ . '/../scripts/backup.sh';
$output = [];
$exitCode = 0;
exec(escapeshellarg($script) . ' 2>&1', $output, $exitCode);

echo implode("\n", $output) . "\n";
echo $exitCode === 0 ? "OK\n" : "ERROR (exit code {$exitCode})\n";
exit($exitCode);
