<?php
/** Footer global (CANÓNICO) — datos del centro desde $config. */
declare(strict_types=1);

$nombre    = $config['centro_nombre']    ?? 'LibrosBro';
$direccion = $config['centro_direccion'] ?? '';
$telefono  = $config['centro_telefono']  ?? '';
$email     = $config['centro_email']     ?? '';
?>
<footer class="footer-bookswap">
  <div class="container-xxl py-5">
    <div class="row g-4">
      <div class="col-lg-5">
        <div class="d-flex align-items-center gap-2 mb-2">
          <?php $logoVerFooter = @filemtime(dirname(__DIR__, 2) . '/public/assets/img/logo.svg') ?: 3; ?>
          <img src="/assets/img/logo.svg?v=<?= $logoVerFooter ?>" alt="LibrosBro" width="60" height="60" class="footer-logo">
          <strong class="fs-5 text-white"><?= e($nombre) ?></strong>
        </div>
        <p class="mb-0">Intercambia libros, gana tokens y llénate de historias. Recuerda que solo se admiten ejemplares de los títulos incluidos en nuestro catálogo. Los tokens no caducan nunca y no tienen valor monetario.</p>
      </div>
      <div class="col-lg-4">
        <h6><i class="bi bi-geo-alt me-1"></i>Contacto</h6>
        <?php if ($direccion): ?><p class="mb-1"><i class="bi bi-pin-map me-2"></i><?= e($direccion) ?></p><?php endif; ?>
        <?php if ($telefono): ?><p class="mb-1"><a href="tel:<?= e(str_replace(' ', '', $telefono)) ?>"><i class="bi bi-telephone me-2"></i><?= e($telefono) ?></a></p><?php endif; ?>
        <?php if ($email): ?><p class="mb-0"><a href="mailto:<?= e($email) ?>"><i class="bi bi-envelope me-2"></i><?= e($email) ?></a></p><?php endif; ?>
      </div>
      <div class="col-lg-3">
        <h6>Enlaces</h6>
        <p class="mb-1"><a href="/catalogo">Catálogo de libros</a></p>
        <p class="mb-1"><a href="/como-funciona">Cómo funciona</a></p>
        <p class="mb-1"><a href="/ayuda">Centro de Ayuda</a></p>
        <p class="mb-0"><a href="/privacidad">Privacidad</a></p>
      </div>
    </div>
    <hr style="border-color:#263449">
    <div class="d-flex flex-wrap justify-content-between gap-2 small" style="color:#94A3B8">
      <span>© <?= date('Y') ?> <?= e($nombre) ?></span>
      <span><i class="bi bi-coin me-1"></i>1 token = un libro circulando de nuevo</span>
    </div>
  </div>
</footer>