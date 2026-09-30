<?php
/**
 * Página de mantenimiento — se muestra al público mientras MAINTENANCE_MODE = true
 * (Configuración > general). Autocontenida: no depende del CSS compilado ni de la BD
 * más allá de los settings ya cargados.
 */
$phone    = (string) setting('CONTACT_PHONE');
$email    = (string) setting('CONTACT_EMAIL');
$whatsapp = preg_replace('/\D/', '', (string) setting('CONTACT_WHATSAPP'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= e(SITE_NAME) ?> — En mantenimiento</title>
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --teal: #326666;
            --teal-dark: #244B4B;
            --olive: #9A9864;
            --bg: #f7f8f5;
            --text: #2b3a3a;
            --muted: #6b7777;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: 'Poppins', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            overflow-x: hidden;
            position: relative;
        }
        /* Cuadros decorativos inspirados en el logo */
        .shape { position: fixed; border-radius: 22px 0 22px 0; opacity: .12; z-index: 0; }
        .s1 { width: 220px; height: 150px; background: var(--teal);  top: -40px;  right: -40px; }
        .s2 { width: 120px; height: 90px;  background: var(--olive); top: 140px;  right: 160px; }
        .s3 { width: 260px; height: 170px; background: var(--teal);  bottom: -60px; left: -60px; }
        .s4 { width: 110px; height: 80px;  background: var(--olive); bottom: 150px; left: 180px; }

        main { position: relative; z-index: 1; width: 100%; max-width: 640px; text-align: center; }
        .logo { width: 100%; max-width: 420px; height: auto; margin-bottom: 48px; }
        .badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(50, 102, 102, .1); color: var(--teal);
            font-size: 13px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase;
            padding: 8px 16px; border-radius: 999px; margin-bottom: 20px;
        }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--olive); animation: pulse 1.6s ease-in-out infinite; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: .3; } }
        h1 { font-size: clamp(26px, 5vw, 38px); font-weight: 600; color: var(--teal-dark); line-height: 1.2; margin-bottom: 16px; }
        p { font-size: 17px; line-height: 1.6; color: var(--muted); max-width: 480px; margin: 0 auto; }
        .bar { width: 180px; height: 4px; margin: 36px auto 0; background: rgba(154, 152, 100, .25); border-radius: 4px; overflow: hidden; }
        .bar span { display: block; width: 40%; height: 100%; background: var(--teal); border-radius: 4px; animation: load 1.8s ease-in-out infinite; }
        @keyframes load { 0% { transform: translateX(-100%); } 100% { transform: translateX(250%); } }
        .contact { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; margin-top: 36px; }
        .contact a {
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
            color: var(--teal); background: #fff; border: 1px solid rgba(50, 102, 102, .2);
            padding: 10px 18px; border-radius: 999px; font-size: 14px; font-weight: 500;
        }
        .contact a:hover { border-color: var(--teal); }
        footer { position: relative; z-index: 1; margin-top: 56px; font-size: 13px; color: var(--muted); }
        @media (prefers-reduced-motion: reduce) { .dot, .bar span { animation: none; } }
    </style>
</head>
<body>
    <div class="shape s1"></div>
    <div class="shape s2"></div>
    <div class="shape s3"></div>
    <div class="shape s4"></div>

    <main>
        <img class="logo" src="/assets/images/logo.png" alt="<?= e(SITE_NAME) ?>">
        <div class="badge"><span class="dot"></span> En mantenimiento</div>
        <h1>Estamos preparando algo nuevo</h1>
        <p>Nuestro sitio web está en construcción. Muy pronto podrás consultar nuestro catálogo de insumos médicos en línea.</p>
        <div class="bar"><span></span></div>

        <?php if ($phone !== '' || $email !== '' || $whatsapp !== ''): ?>
            <div class="contact">
                <?php if ($whatsapp !== ''): ?>
                    <a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener">WhatsApp</a>
                <?php endif; ?>
                <?php if ($phone !== ''): ?>
                    <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a>
                <?php endif; ?>
                <?php if ($email !== ''): ?>
                    <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. Todos los derechos reservados.</footer>
</body>
</html>
