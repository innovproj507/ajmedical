<?php
/** @var array|null $entry  Página "contacto" (su contenido se muestra sobre el formulario) */

$bannerImage = '';
$bannerTitle = 'Contacto';
$breadcrumb  = [['label' => 'Inicio', 'href' => '/'], ['label' => 'Contacto']];
include __DIR__ . '/partials/page-banner.php';

$phone    = (string) setting('CONTACT_PHONE');
$email    = (string) setting('CONTACT_EMAIL');
$address  = (string) setting('CONTACT_ADDRESS');
$hours    = (string) setting('CONTACT_HOURS');
$whatsapp = preg_replace('/\D/', '', (string) setting('CONTACT_WHATSAPP'));

$input = 'w-full rounded-xl border border-gray-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-aj-teal';
?>
<section class="max-w-7xl mx-auto px-5 md:px-10 py-14">
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-10">
        <div class="lg:col-span-2 space-y-5">
            <h2 class="text-2xl font-extrabold text-gray-900">Hablemos</h2>
            <?php if (!empty($entry['data']['contenido'])): ?>
                <div class="prose prose-sm max-w-none text-gray-600"><?= $entry['data']['contenido'] ?></div>
            <?php endif; ?>

            <ul class="space-y-4 pt-2">
                <?php foreach ([
                    ['icon' => 'phone',    'label' => 'Teléfono',  'value' => $phone,   'href' => 'tel:' . preg_replace('/[^0-9+]/', '', $phone)],
                    ['icon' => 'whatsapp', 'label' => 'WhatsApp',  'value' => $whatsapp ? '+' . $whatsapp : '', 'href' => 'https://wa.me/' . $whatsapp],
                    ['icon' => 'mail',     'label' => 'Correo',    'value' => $email,   'href' => 'mailto:' . $email],
                    ['icon' => 'map-pin',  'label' => 'Dirección', 'value' => $address, 'href' => ''],
                    ['icon' => 'clock',    'label' => 'Horario',   'value' => $hours,   'href' => ''],
                ] as $item): if ($item['value'] === '') continue; ?>
                    <li class="flex items-start gap-4">
                        <span class="w-12 h-12 rounded-tl-2xl rounded-br-2xl rounded-tr-md rounded-bl-md bg-aj-teal-light text-aj-teal flex items-center justify-center shrink-0"><?= ajm_icon($item['icon'], 'w-5 h-5') ?></span>
                        <div>
                            <p class="text-xs text-gray-400"><?= e($item['label']) ?></p>
                            <?php if ($item['href']): ?>
                                <a href="<?= e($item['href']) ?>" <?= str_starts_with($item['href'], 'http') ? 'target="_blank" rel="noopener"' : '' ?> class="font-semibold text-gray-800 hover:text-aj-teal"><?= e($item['value']) ?></a>
                            <?php else: ?>
                                <p class="font-semibold text-gray-800"><?= nl2br(e($item['value'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="lg:col-span-3">
            <?php if (!empty($_GET['enviado'])): ?>
                <div class="mb-5 rounded-xl bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">¡Gracias! Recibimos tu mensaje y te responderemos pronto.</div>
            <?php elseif (!empty($_GET['error'])): ?>
                <div class="mb-5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">Revisa los campos obligatorios: nombre, un correo válido y el mensaje.</div>
            <?php endif; ?>

            <form method="post" action="/contacto" class="rounded-3xl border border-gray-200 p-6 md:p-8 space-y-4">
                <div class="hidden" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <input type="text" name="nombre" required placeholder="Nombre *" class="<?= $input ?>">
                    <input type="email" name="correo" required placeholder="Correo *" class="<?= $input ?>">
                    <input type="tel" name="telefono" placeholder="Teléfono" class="<?= $input ?>">
                    <input type="text" name="asunto" placeholder="Asunto" class="<?= $input ?>">
                </div>
                <textarea name="mensaje" rows="6" required placeholder="¿En qué podemos ayudarte? *" class="<?= $input ?>"></textarea>
                <button type="submit" class="inline-flex items-center gap-2 bg-aj-teal hover:bg-aj-teal-dark transition text-white font-semibold px-7 py-3 rounded-full">
                    Enviar mensaje <?= ajm_icon('arrow-right', 'w-4 h-4') ?>
                </button>
            </form>
        </div>
    </div>
</section>
