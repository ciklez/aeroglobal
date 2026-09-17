<?php
/**
 * Aero Global Logistics — enquiry form handler
 * -------------------------------------------------------------
 * Drop this next to index.html on any PHP-capable host (cPanel,
 * Plesk, shared hosting). Nothing else to install.
 *
 * Enquiries are delivered to cs-logistics@aeroglobal.com.my.
 * $FROM_DOMAIN must be the domain the site is hosted on.
 */
$TO_ADDRESS = 'cs-logistics@aeroglobal.com.my';   // where enquiries land
$FROM_DOMAIN = 'aeroglobal.com.my';          // your real hosted domain
/* ------------------------------------------------------------- */

// Optional: keep a local copy of every enquiry. Set to null to disable.
$LOG_FILE = __DIR__ . '/enquiries.log';

header('X-Content-Type-Options: nosniff');

$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'fetch');

function respond($ok, $message, $isAjax) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $message]);
        exit;
    }
    // Plain (no-JavaScript) fallback page.
    $colour = $ok ? '#001668' : '#d40000';
    $title  = $ok ? 'Enquiry sent' : 'Could not send';
    header('Content-Type: text/html; charset=utf-8');
    // Light page, so use the light-background emblem with AERO in brand blue.
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . $title . ' — Aero Global Logistics</title>'
       . '<link rel="icon" href="favicon.ico" sizes="48x48">'
       . '<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@800;900&display=swap" rel="stylesheet">'
       . '<style>body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#F3F5FA;color:#0A1238;'
       . 'display:grid;place-items:center;min-height:100vh;margin:0;padding:2rem;text-align:center}'
       . '.box{background:#ffffff;border:1px solid #E1E5EF;border-top:4px solid #001668;border-radius:8px;padding:2.5rem;max-width:520px}'
       . '.logo{display:inline-flex;align-items:center;gap:.7rem;margin-bottom:1.8rem;font-family:Archivo,system-ui,sans-serif;'
       . 'font-weight:900;font-size:1.4rem;text-transform:uppercase;letter-spacing:-.01em;text-decoration:none}'
       . '.logo img{width:48px;height:48px}.aero{color:#001668}.global{color:#d40000}'
       . 'h1{font-size:1.5rem;margin:0 0 .8rem;color:' . $colour . '}p{color:#55607F;line-height:1.6;margin:0 0 1.6rem}'
       . '.back{display:inline-block;background:#d40000;color:#ffffff;text-decoration:none;padding:.85rem 1.6rem;'
       . 'border-radius:4px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;font-size:.8rem}'
       . '.back:hover{background:#001668}</style>'
       . '</head><body><div class="box">'
       . '<a class="logo" href="index.html"><img src="images/logo/aero-emblem-light-bg.png" alt="">'
       . '<span><span class="aero">Aero</span> <span class="global">Global</span></span></a>'
       . '<h1>' . $title . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
       . '</p><a class="back" href="index.html#contact">Back to the site</a></div></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'This page only accepts form submissions.', $isAjax);
}

// --- honeypot: bots fill hidden fields, people do not -----------
if (!empty($_POST['website'])) {
    respond(true, 'Thank you.', $isAjax);   // silently swallow
}

// --- collect + clean -------------------------------------------
function clean($key, $max = 2000, $multiline = false) {
    $v = isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
    $v = str_replace(["\r", "\0"], '', $v);
    // Anything that lands in a mail header must not contain a line feed,
    // or a crafted name field could inject extra headers (Bcc, Content-Type).
    if (!$multiline) { $v = str_replace("\n", ' ', $v); }
    return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
}

$name    = clean('name', 120);
$company = clean('company', 160);
$email   = clean('email', 190);
$phone   = clean('phone', 60);
$service = clean('service', 80);
$message = clean('message', 5000, true);

// --- validate ---------------------------------------------------
$errors = [];
if ($name === '')                                     { $errors[] = 'your name'; }
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'a valid email address'; }
if ($service === '')                                  { $errors[] = 'the service you need'; }
if ($message === '')                                  { $errors[] = 'your shipment details'; }

if ($errors) {
    respond(false, 'Please provide ' . implode(', ', $errors) . '.', $isAjax);
}

// --- build the email -------------------------------------------
$subject = 'Website enquiry: ' . $service . ' — ' . $name;

$lines = [
    'New enquiry from the Aero Global Logistics website',
    str_repeat('=', 52),
    '',
    'Name        : ' . $name,
    'Company     : ' . ($company !== '' ? $company : '-'),
    'Email       : ' . $email,
    'Phone       : ' . ($phone !== '' ? $phone : '-'),
    'Service     : ' . $service,
    '',
    'Shipment details',
    str_repeat('-', 52),
    $message,
    '',
    str_repeat('-', 52),
    'Submitted   : ' . date('D, d M Y H:i:s') . ' (server time)',
    'IP address  : ' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown'),
];
$body = implode("\n", $lines);

// Envelope sender must belong to the hosting domain or the mail
// will be rejected as spoofed. The visitor goes in Reply-To.
$sender = 'no-reply@' . $FROM_DOMAIN;

$headers = [
    'From: Aero Global Website <' . $sender . '>',
    'Reply-To: =?UTF-8?B?' . base64_encode($name) . '?= <' . $email . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'X-Mailer: PHP/' . phpversion(),
];

$sent = @mail(
    $TO_ADDRESS,
    '=?UTF-8?B?' . base64_encode($subject) . '?=',
    $body,
    implode("\r\n", $headers),
    '-f' . $sender
);

// --- log a copy regardless of mail success ---------------------
if ($LOG_FILE) {
    @file_put_contents(
        $LOG_FILE,
        '[' . date('c') . '] ' . ($sent ? 'SENT' : 'FAILED') . "\n" . $body . "\n\n",
        FILE_APPEND | LOCK_EX
    );
}

if ($sent) {
    respond(true, 'Thank you. We will reply within one business day.', $isAjax);
}

respond(false, 'The message could not be sent. Please email cs-logistics@aeroglobal.com.my or call 03-3342 7963.', $isAjax);
