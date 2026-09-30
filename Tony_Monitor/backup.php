<?php
// Gestion de backups completos del servidor (webs + BBDD + config Apache +
// crontabs): crear (en segundo plano), consultar progreso, listar,
// descargar y borrar copias guardadas, configurar retencion y notificacion
// por webhook. Todo protegido por contraseña.

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/inc/backup_functions.php';

$secretFile = __DIR__ . '/config/backup_secret.php';
if (!file_exists($secretFile)) {
    http_response_code(500);
    die(json_encode(['error' => 'Backup no configurado: falta config/backup_secret.php']));
}
require $secretFile; // define BACKUP_PASSWORD_HASH

$key = $_POST['key'] ?? $_GET['key'] ?? '';
if (!$key || !password_verify($key, BACKUP_PASSWORD_HASH)) {
    http_response_code(403);
    die(json_encode(['error' => 'Contraseña incorrecta.']));
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

switch ($action) {

    case 'list':
        echo json_encode([
            'backups' => list_backups(),
            'retention' => get_retention(),
            'notify_webhook' => get_notify_webhook(),
            'space' => get_space_summary(),
        ]);
        break;

    case 'set_retention':
        $keep = (int)($_POST['keep'] ?? $_GET['keep'] ?? 1);
        if ($keep < 0) $keep = 0;
        if ($keep > 10) $keep = 10;
        set_retention($keep);
        apply_retention($keep);
        echo json_encode([
            'ok' => true,
            'retention' => $keep,
            'backups' => list_backups(),
            'space' => get_space_summary(),
        ]);
        break;

    case 'set_notify_webhook':
        $url = trim($_POST['webhook_url'] ?? $_GET['webhook_url'] ?? '');
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            http_response_code(400);
            die(json_encode(['error' => 'La URL del webhook no es valida.']));
        }
        set_notify_webhook($url);
        echo json_encode(['ok' => true, 'notify_webhook' => $url]);
        break;

    case 'create':
        $status = get_status();
        if ($status['state'] === 'running') {
            echo json_encode(['ok' => true, 'already_running' => true, 'status' => $status]);
            break;
        }

        // Se limpian los excedentes ANTES de generar el nuevo backup (y no
        // despues), para que el que se acaba de crear siempre quede
        // disponible para descargar hasta que se lance el siguiente, incluso
        // con la opcion "no guardar ninguna" (retencion 0).
        apply_retention(get_retention());

        $script = '/var/www/html/Tony_Monitor/scripts/backup.sh';
        // Se lanza en segundo plano: backup.php no espera a que termine, el
        // progreso se consulta despues con action=status. Necesario porque
        // un backup grande puede tardar varios minutos y bloquear la peticion
        // HTTP no es fiable (timeouts de navegador o del propio Apache).
        exec('sudo ' . escapeshellarg($script) . ' > /dev/null 2>&1 &');

        echo json_encode(['ok' => true, 'started' => true]);
        break;

    case 'status':
        $status = get_status();
        if ($status['state'] === 'done' || $status['state'] === 'error') {
            $status['backups'] = list_backups();
            $status['space'] = get_space_summary();
        }
        echo json_encode($status);
        break;

    case 'download':
        $filename = $_GET['file'] ?? '';
        if (!preg_match(BACKUP_FILENAME_PATTERN, $filename)) {
            http_response_code(400);
            die(json_encode(['error' => 'Nombre de archivo invalido']));
        }
        $path = BACKUP_DIR . '/' . $filename;
        if (!file_exists($path)) {
            http_response_code(404);
            die(json_encode(['error' => 'Backup no encontrado']));
        }
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        readfile($path);
        exit;

    case 'delete':
        $filename = $_POST['file'] ?? $_GET['file'] ?? '';
        if (!preg_match(BACKUP_FILENAME_PATTERN, $filename)) {
            http_response_code(400);
            die(json_encode(['error' => 'Nombre de archivo invalido']));
        }
        $path = BACKUP_DIR . '/' . $filename;
        if (file_exists($path)) {
            unlink($path);
        }
        echo json_encode(['ok' => true, 'backups' => list_backups(), 'space' => get_space_summary()]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Accion desconocida']);
}
