<?php
/**
 * Recibe el formulario de contacto de contacto.html y se lo manda por
 * mail al estudio.
 *
 * Con JavaScript, la página manda los datos con fetch y espera JSON, así
 * no se va a ningún lado. Sin JavaScript, cae acá de una y este archivo
 * dibuja una página chiquita con el resultado y un enlace para volver.
 */

declare(strict_types=1);

/** A dónde llega el mensaje. Se cambia acá y en ningún otro lado. */
const CONTACTO_DESTINO = 'info@arhaus.com.ar';

/**
 * Quién figura como remitente. OJO: tiene que ser una casilla del propio
 * dominio. Si se pusiera la dirección de quien escribe, el servidor de
 * destino ve que arhaus.com.ar manda un mail de gmail.com y lo tira a
 * spam (falla el SPF). La dirección de la visita va en Reply-To, que es
 * lo que usa el botón "Responder".
 *
 * Va la misma casilla que recibe, que seguro existe: si se pusiera una
 * inventada (web@, noreply@) los rebotes se pierden.
 */
const CONTACTO_REMITENTE = 'info@arhaus.com.ar';

const CONTACTO_LARGO_NOMBRE = 80;
const CONTACTO_LARGO_EMAIL = 120;
const CONTACTO_LARGO_TELEFONO = 40;
const CONTACTO_LARGO_MENSAJE = 3000;

/** Un mensaje por minuto desde la misma visita: alcanza para frenar el goteo de spam. */
const CONTACTO_ESPERA = 60;

session_start();

$esFetch = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch';

/**
 * Largo y recorte contando caracteres y no bytes, para no partir una "ñ"
 * al medio. Igual que en lib/helpers.php: si el servidor no tiene
 * mbstring, se cae a las funciones de siempre en vez de reventar.
 */
function contacto_largo(string $texto): int
{
    return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
}

function contacto_recortar(string $texto, int $largo): string
{
    return function_exists('mb_substr') ? mb_substr($texto, 0, $largo, 'UTF-8') : substr($texto, 0, $largo);
}

/** Corta cualquier salto de línea: es lo que se usa para inyectar cabeceras de mail. */
function una_linea(string $texto, int $largo): string
{
    $texto = str_replace(["\r", "\n", "\0"], ' ', $texto);
    $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
    return contacto_recortar($texto, $largo);
}

function terminar(bool $bien, string $texto, int $codigo = 200): void
{
    global $esFetch;

    if ($esFetch) {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($bien ? ['ok' => true, 'mensaje' => $texto] : ['error' => $texto], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code($codigo);
    header('Content-Type: text/html; charset=utf-8');
    $t = htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto - ARHAUS</title>
    <link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        body { margin:0; min-height:100vh; display:grid; place-items:center; padding:24px;
               background:#F5F4F0; color:#333; font-family:"Work Sans",sans-serif; font-weight:300; }
        div { max-width:460px; text-align:center; }
        p { font-size:1.05rem; line-height:1.7; margin:0 0 24px; }
        a { display:inline-block; padding:11px 24px; border:1px solid rgba(51,51,51,.5);
            border-radius:999px; color:#333; font-size:.85rem; text-decoration:none; }
        a:hover { background:#333; color:#F5F4F0; }
    </style>
</head>
<body>
    <div>
        <p>$t</p>
        <a href="contacto.html">Volver a Contacto</a>
    </div>
</body>
</html>
HTML;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    terminar(false, 'Entrá al formulario desde la página de contacto.', 405);
}

// La trampa: un campo que la gente no ve y los robots sí completan.
if (trim((string)($_POST['sitio_web'] ?? '')) !== '') {
    terminar(true, 'Gracias, recibimos tu mensaje.');
}

$ultimo = (int)($_SESSION['contacto_ultimo'] ?? 0);
if ($ultimo && time() - $ultimo < CONTACTO_ESPERA) {
    terminar(false, 'Recién mandaste un mensaje. Esperá un minuto antes del siguiente.', 429);
}

$nombre = una_linea((string)($_POST['nombre'] ?? ''), CONTACTO_LARGO_NOMBRE);
$email = una_linea((string)($_POST['email'] ?? ''), CONTACTO_LARGO_EMAIL);
$telefono = una_linea((string)($_POST['telefono'] ?? ''), CONTACTO_LARGO_TELEFONO);
$mensaje = trim(str_replace("\r\n", "\n", (string)($_POST['mensaje'] ?? '')));
$mensaje = contacto_recortar($mensaje, CONTACTO_LARGO_MENSAJE);

if ($nombre === '') {
    terminar(false, 'Falta tu nombre.', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    terminar(false, 'Revisá el correo: no parece una dirección válida.', 422);
}
if (contacto_largo($mensaje) < 10) {
    terminar(false, 'Contanos un poco más en el mensaje.', 422);
}

$cuerpo = "Nuevo mensaje desde arhaus.com.ar\n\n"
    . "Nombre:   $nombre\n"
    . "Correo:   $email\n"
    . ($telefono !== '' ? "Teléfono: $telefono\n" : '')
    . "\n$mensaje\n";

$cabeceras = [
    'From: ARHAUS web <' . CONTACTO_REMITENTE . '>',
    'Reply-To: ' . $nombre . ' <' . $email . '>',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . PHP_VERSION,
];

$asunto = '=?UTF-8?B?' . base64_encode('Consulta web de ' . $nombre) . '?=';

$enviado = @mail(CONTACTO_DESTINO, $asunto, $cuerpo, implode("\r\n", $cabeceras));

if (!$enviado) {
    terminar(false, 'No pudimos enviar el mensaje. Escribinos a ' . CONTACTO_DESTINO . ' y lo vemos.', 500);
}

$_SESSION['contacto_ultimo'] = time();
terminar(true, 'Gracias, ' . $nombre . '. Recibimos tu mensaje y te respondemos a la brevedad.');
