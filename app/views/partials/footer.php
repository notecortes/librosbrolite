<?php
/** Footer global (CANÓNICO) — datos del centro desde $config, horario vía helpers/centro.php. */
declare(strict_types=1);
require_once __DIR__ . '/../../helpers/centro.php';

 $nombre    = $config['centro_nombre']    ?? 'LibrosBro';
 $direccion = $config['centro_direccion'] ?? '';
 $telefono  = $config['centro_telefono']  ?? '';
 $email     = $config['centro_email']     ?? '';
 $horarioJson = $config['centro_horario'] ?? null;
[$abierto, $msgAbierto] = abierto_ahora($horarioJson);
 $nombresDias = ['lunes'=>'Lunes','martes'=>'Martes','miercoles'=>'Miércoles','jueves'=>'Jueves',
                'viernes'=>'Viernes','sabado'=>'Sábado','domingo'=>'Domingo'];
 $diasSemana  = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo'];
 $hoySemana   = ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'][
    (int) (new DateTime('now', new DateTimeZone('Europe/Madrid')))->format('w')];
?>
<footer class="footer-bookswap">
  <div class="container-xxl py-5">
    <div class="row g-4">
      <div class="col-lg-4">
        <div class="d-flex align-items-center gap-2 mb-2">
          <?php $logoVerFooter = @filemtime(dirname(__DIR__, 2) . '/public/assets/img/logo.svg') ?: 3; ?>
          <img src="/assets/img/logo.svg?v=<?= $logoVerFooter ?>" alt="LibrosBro" width="60" height="60" class="footer-logo">
          <strong class="fs-5 text-white"><?= e($nombre) ?></strong>
        </div>
        <p class="mb-0">Intercambia libros, gana tokens y llénate de historias. Los tokens no caducan nunca y no tienen valor monetario.</p>
      </div>
      <div class="col-lg-3">
        <h6><i class="bi bi-geo-alt me-1"></i>Contacto</h6>
        <?php if ($direccion): ?><p class="mb-1"><i class="bi bi-pin-map me-2"></i><?= e($direccion) ?></p><?php endif; ?>
        <?php if ($telefono): ?><p class="mb-1"><a href="tel:<?= e(str_replace(' ', '', $telefono)) ?>"><i class="bi bi-telephone me-2"></i><?= e($telefono) ?></a></p><?php endif; ?>
        <?php if ($email): ?><p class="mb-0"><a href="mailto:<?= e($email) ?>"><i class="bi bi-envelope me-2"></i><?= e($email) ?></a></p><?php endif; ?>
      </div>
      <div class="col-lg-3">
        <h6><i class="bi bi-clock me-1"></i>Horario</h6>
        <span class="chip-abierto <?= $abierto ? 'si' : 'no' ?> mb-2">
          <i class="bi <?= $abierto ? 'bi-door-open' : 'bi-door-closed' ?>"></i><?= e($msgAbierto) ?>
        </span>
        <details>
          <summary class="mb-1" style="cursor:pointer">Ver horario semanal</summary>
          <ul class="lista-horario">
            <?php foreach (horario_parsear($horarioJson) as $dia => $rangos): ?>
              <li class="<?= $dia === $hoySemana ? 'hoy' : '' ?>">
                <span><?= $nombresDias[$dia] ?></span>
                <span><?= $rangos ? e(implode(' · ', $rangos)) : 'Cerrado' ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </details>
      </div>
      <div class="col-lg-2">
        <h6>Enlaces</h6>
        <p class="mb-1"><a href="/visitanos">Visítanos</a></p>
        <p class="mb-1"><a href="/catalogo">Catálogo</a></p>
        <p class="mb-1"><a href="/privacidad">Privacidad</a></p>
        <p class="mb-1"><a href="/como-funciona">Cómo funciona</a></p>
        <p class="mb-0"><a href="/ayuda">Centro de Ayuda</a></p>
      </div>
    </div>
    <hr style="border-color:#263449">
    <div class="d-flex flex-wrap justify-content-between gap-2 small" style="color:#94A3B8">
      <span>© <?= date('Y') ?> <?= e($nombre) ?></span>
      <span><i class="bi bi-coin me-1"></i>1 token = un libro circulando de nuevo</span>
    </div>
  </div>
</footer>