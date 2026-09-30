<?php
/** @var string $message */
?>
<section class="relative overflow-hidden max-w-3xl mx-auto px-5 py-24 text-center">
    <span class="absolute top-10 left-6 w-28 h-20 rounded-tl-3xl rounded-br-3xl bg-aj-olive/15"></span>
    <span class="absolute bottom-10 right-6 w-36 h-24 rounded-tl-3xl rounded-br-3xl bg-aj-teal/10"></span>
    <p class="relative text-7xl font-extrabold text-aj-teal">404</p>
    <h1 class="relative text-2xl font-bold text-gray-900 mt-4"><?= e($message ?? 'Página no encontrada') ?></h1>
    <p class="relative text-gray-500 mt-2">Es posible que el enlace haya cambiado o que el contenido ya no esté disponible.</p>
    <div class="relative flex flex-wrap justify-center gap-3 mt-8">
        <a href="/" class="bg-aj-teal hover:bg-aj-teal-dark transition text-white font-semibold px-6 py-3 rounded-full">Ir al inicio</a>
        <a href="/catalogo" class="border border-aj-teal text-aj-teal hover:bg-aj-teal hover:text-white transition font-semibold px-6 py-3 rounded-full">Ver catálogo</a>
    </div>
</section>
