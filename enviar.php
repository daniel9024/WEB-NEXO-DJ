<?php
/* =========================================================
   FORMULARIO DE SOLICITUD DE ACCESO · NEXO DJ
   1) Envía la solicitud a CORREO_DESTINO.
   2) Envía un correo de agradecimiento al cliente.
   Responde JSON: { ok: bool, mensaje: string }
   ========================================================= */

const CORREO_DESTINO   = 'contacto@chiledj.cl';  // donde te llegan las solicitudes
const CORREO_REMITENTE = 'contacto@chiledj.cl';  // debe ser una cuenta real de tu dominio
const NOMBRE_REMITENTE = 'NEXO DJ';
const URL_LOGO         = 'https://chiledj.cl/img/nexo-email.png';
const MAX_POR_HORA     = 3;                       // envíos permitidos por IP y hora

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Santiago');

function responder($ok, $mensaje, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode(['ok' => $ok, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

function campo($nombre, $max) {
    $valor = trim((string) ($_POST[$nombre] ?? ''));
    return mb_substr($valor, 0, $max, 'UTF-8');
}

// Quita saltos de línea para que nadie inyecte cabeceras en el correo
function linea($texto) {
    return preg_replace('/[\r\n]+/', ' ', $texto);
}

function e($texto) {
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método no permitido.', 405);
}

// Campo trampa: las personas no lo ven, los bots lo llenan
if (!empty($_POST['sitio_web'])) {
    responder(true, 'Solicitud enviada.');
}

/* ---------- Datos ---------- */

$perfiles = ['Contador independiente', 'Estudio contable', 'Empresa (área contable interna)', 'Otro'];
$volumenes = ['', '1–10', '11–50', '51–200', 'Más de 200'];

// Largos máximos: los mismos maxlength del formulario en index.html
$nombre   = linea(campo('nombre', 80));
$correo   = linea(campo('correo', 120));
$empresa  = linea(campo('empresa', 100));
$perfil   = campo('perfil', 60);
$volumen  = campo('volumen', 20);
$telefono = linea(campo('telefono', 16));
$mensaje  = campo('mensaje', 1000);
$acepta   = !empty($_POST['acepta']);

/* ---------- Validación (mismas reglas que js/script.js) ---------- */

if (mb_strlen($nombre, 'UTF-8') < 3
    || !preg_match("/^[\p{L}'. -]+$/u", $nombre)
    || count(preg_split('/\s+/', $nombre)) < 2) {
    responder(false, 'Escribe tu nombre y apellido (solo letras).', 422);
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || !preg_match('/\.[a-z]{2,}$/i', $correo)) {
    responder(false, 'Escribe un correo válido, por ejemplo nombre@empresa.cl.', 422);
}
if (!in_array($perfil, $perfiles, true) || !in_array($volumen, $volumenes, true)) {
    responder(false, 'Selecciona una opción válida.', 422);
}
// Teléfono chileno: 9 dígitos, o 11 si incluye el 56 del país
$digitos = preg_replace('/\D/', '', $telefono);
if ($telefono !== '' && (!preg_match('/^[0-9 +]+$/', $telefono)
    || !(strlen($digitos) === 9 || (strlen($digitos) === 11 && strpos($digitos, '56') === 0)))) {
    responder(false, 'El teléfono debe tener 9 dígitos, por ejemplo +56 9 1234 5678.', 422);
}
if (!$acepta) {
    responder(false, 'Debes aceptar que te contactemos.', 422);
}

/* ---------- Límite por IP (evita que usen el formulario para enviar spam) ---------- */

$ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
$registro = sys_get_temp_dir() . '/nexo_form_' . md5($ip);
$ahora = time();
$envios = is_file($registro) ? array_filter(
    array_map('intval', file($registro, FILE_IGNORE_NEW_LINES)),
    function ($t) use ($ahora) { return $t > $ahora - 3600; }
) : [];

if (count($envios) >= MAX_POR_HORA) {
    responder(false, 'Recibimos varias solicitudes desde tu conexión. Intenta más tarde o escríbenos a ' . CORREO_DESTINO . '.', 429);
}

/* ---------- Envío ---------- */

function enviar($para, $asunto, $html, $responderA) {
    $cabeceras = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'From: ' . mb_encode_mimeheader(NOMBRE_REMITENTE, 'UTF-8') . ' <' . CORREO_REMITENTE . '>',
        'Reply-To: ' . $responderA,
        'X-Mailer: PHP/' . phpversion(),
    ]);
    // -f fija el remitente real (envelope), necesario para que SPF valide
    // base64 en líneas de 76 caracteres: los servidores rechazan líneas de más de 2048
    $cuerpo = chunk_split(base64_encode($html));
    return mail($para, mb_encode_mimeheader($asunto, 'UTF-8'), $cuerpo, $cabeceras, '-f' . CORREO_REMITENTE);
}

function plantilla($contenido) {
    return '<!doctype html><html lang="es"><body style="margin:0;padding:0;background:#F5F7F4;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F7F4;padding:32px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#FFFFFF;border-radius:16px;font-family:Segoe UI,Arial,sans-serif;color:#10201A;font-size:15px;line-height:1.6;">'
        . '<tr><td style="height:6px;background:#15803D;border-radius:16px 16px 0 0;"></td></tr>'
        . '<tr><td style="padding:36px 40px 32px;">' . $contenido . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function firma() {
    return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:28px;border-top:1px solid #E2E8E3;width:100%;">'
        . '<tr><td style="padding-top:20px;">'
        . '<img src="' . URL_LOGO . '" width="150" height="43" alt="NEXO DJ" style="display:block;border:0;margin-bottom:12px;">'
        . '<strong style="font-size:15px;">Equipo NEXO DJ</strong><br>'
        . '<span style="color:#5B6B63;font-size:13px;">Validador de declaraciones juradas SII</span><br>'
        . '<a href="mailto:' . CORREO_DESTINO . '" style="color:#15803D;font-size:13px;text-decoration:none;">' . CORREO_DESTINO . '</a>'
        . '<span style="color:#9AA8A0;font-size:13px;"> · </span>'
        . '<a href="https://chiledj.cl" style="color:#15803D;font-size:13px;text-decoration:none;">chiledj.cl</a>'
        . '</td></tr></table>';
}

// 1) Solicitud para ti
$filas = [
    'Nombre'            => $nombre,
    'Correo'            => $correo,
    'Empresa / estudio' => $empresa ?: '—',
    'Perfil'            => $perfil,
    'DJ por temporada'  => $volumen ?: '—',
    'Teléfono'          => $telefono ?: '—',
    'Mensaje'           => $mensaje ?: '—',
];
$tabla = '';
foreach ($filas as $etiqueta => $valor) {
    $tabla .= '<tr><td style="padding:8px 12px 8px 0;color:#5B6B63;vertical-align:top;white-space:nowrap;">' . e($etiqueta) . '</td>'
        . '<td style="padding:8px 0;">' . nl2br(e($valor)) . '</td></tr>';
}
$htmlAdmin = plantilla(
    '<h1 style="margin:0 0 4px;font-size:20px;">Nueva solicitud de acceso</h1>'
    . '<p style="margin:0 0 20px;color:#5B6B63;">Recibida el ' . date('d-m-Y H:i') . ' desde chiledj.cl</p>'
    . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">' . $tabla . '</table>'
    . '<p style="margin:24px 0 0;color:#5B6B63;font-size:13px;">Responde este correo para escribirle directamente a ' . e($nombre) . '.</p>'
);

if (!enviar(CORREO_DESTINO, "Nueva solicitud de acceso – $nombre ($perfil)", $htmlAdmin, $correo)) {
    responder(false, 'No pudimos enviar tu solicitud. Escríbenos a ' . CORREO_DESTINO . '.', 500);
}

// Se registra el envío solo si la solicitud salió
$envios[] = $ahora;
file_put_contents($registro, implode("\n", $envios));

// 2) Agradecimiento para el cliente
$htmlCliente = plantilla(
    '<p style="margin:0 0 16px;">Hola ' . e($nombre) . ':</p>'
    . '<p style="margin:0 0 16px;">Gracias por tu interés en <strong>NEXO DJ</strong>. Recibimos tu solicitud de acceso anticipado y ya está en nuestra lista.</p>'
    . '<p style="margin:0 0 16px;">NEXO DJ revisa tus declaraciones juradas en tu propio computador antes de presentarlas al SII: te indica cada error por fila y campo, y tus datos nunca salen de tu equipo.</p>'
    . '<p style="margin:0 0 8px;"><strong>Qué viene ahora</strong></p>'
    . '<ol style="margin:0 0 16px;padding-left:20px;">'
    . '<li>Revisamos tu solicitud dentro de los próximos <strong>2 días hábiles</strong>.</li>'
    . '<li>Te escribimos con los pasos para instalar NEXO DJ y tus datos de acceso.</li>'
    . '<li>Si quieres, coordinamos una breve demostración con tus propios archivos.</li>'
    . '</ol>'
    . '<p style="margin:0 0 16px;">Si tienes cualquier duda, solo responde este correo.</p>'
    . '<p style="margin:0;">Saludos cordiales,</p>'
    . firma()
    . '<p style="margin:24px 0 0;color:#9AA8A0;font-size:12px;">Recibiste este correo porque solicitaste acceso en chiledj.cl. Si no fuiste tú, puedes ignorarlo.</p>'
);

// Si falla el agradecimiento, la solicitud igual llegó: no se muestra error al cliente
enviar($correo, 'Recibimos tu solicitud de acceso a NEXO DJ', $htmlCliente, CORREO_DESTINO);

responder(true, '¡Gracias! Te enviamos un correo de confirmación.');
