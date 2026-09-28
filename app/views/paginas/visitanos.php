<?php
/**
 * BookSwap · Información de Contacto del Centro.
 *
 * Muestra:
 * - Nombre del centro.
 * - Dirección física exacta del centro.
 * - Teléfono de contacto y email.
 * - Indicaciones de transporte público si existen.
 * - Información sobre la admisión exclusiva de libros catalogados.
 */
declare(strict_types=1);

$config = $config ?? [];
$nombreCentro    = (string) ($config['centro_nombre'] ?? 'LibrosBro — Biblioteca Ciudadana');
$direccionCentro = (string) ($config['centro_direccion'] ?? 'Calle de los Libros 42, 28004 Madrid');
$telefonoCentro  = (string) ($config['centro_telefono'] ?? '+34 910 123 456');
$emailCentro     = (string) ($config['centro_email'] ?? 'hola@bookswap.local');
$comoLlegar      = (string) ($config['centro_como_llegar'] ?? '');
?>

<div class="container-xxl py-5">
  <div class="row align-items-start justify-content-between mb-5 g-4">
    <div class="col-12 col-lg-7">
      <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-bold mb-3">
        <i class="bi bi-info-circle me-1"></i>Punto de Encuentro e Intercambio
      </span>
      <h1 class="display-5 fw-bold text-body-emphasis mb-3"><?= e($nombreCentro) ?></h1>
      <p class="lead text-muted mb-4">
        Un espacio ciudadano abierto para el intercambio de libros, lectura compartida y dinamización cultural comunitaria.
      </p>

      <!-- Aviso de catálogo cerrado -->
      <div class="card border-0 bg-light rounded-4 p-4 mb-4 shadow-sm">
        <div class="d-flex align-items-start gap-3">
          <div class="p-2 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; min-width: 44px;">
            <i class="bi bi-shield-check fs-5"></i>
          </div>
          <div>
            <h2 class="h5 fw-bold mb-1">Norma de intercambio: Catálogo cerrado</h2>
            <p class="text-muted small mb-0">
              Para garantizar fondos de calidad y alta rotación, <strong>únicamente se admiten los libros que figuran en nuestro catálogo oficial</strong>. No se acepta cualquier libro al azar. Consulta el catálogo antes de traer tus ejemplares al mostrador.
            </p>
          </div>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-3">
        <a href="/catalogo" class="btn btn-primary btn-lg fw-bold px-4 shadow-sm">
          <i class="bi bi-journal-bookmark me-2"></i>Consultar catálogo admitido
        </a>
        <a href="tel:<?= e(str_replace(' ', '', $telefonoCentro)) ?>" class="btn btn-outline-secondary btn-lg px-4">
          <i class="bi bi-telephone me-2"></i><?= e($telefonoCentro) ?>
        </a>
      </div>
    </div>

    <div class="col-12 col-lg-5">
      <div class="card border-0 shadow-sm rounded-4 p-4 bg-light">
        <h2 class="h5 fw-bold mb-3 d-flex align-items-center gap-2">
          <i class="bi bi-geo-alt-fill text-primary"></i>Datos de Contacto
        </h2>

        <ul class="list-unstyled mb-0 d-flex flex-column gap-3">
          <li class="d-flex align-items-start gap-3">
            <div class="bg-white p-2 rounded-3 shadow-xs border text-primary">
              <i class="bi bi-building fs-5"></i>
            </div>
            <div>
              <span class="d-block text-muted small">Ubicación y dirección</span>
              <strong class="text-body"><?= e($direccionCentro) ?></strong>
            </div>
          </li>

          <li class="d-flex align-items-start gap-3">
            <div class="bg-white p-2 rounded-3 shadow-xs border text-primary">
              <i class="bi bi-telephone fs-5"></i>
            </div>
            <div>
              <span class="d-block text-muted small">Atención telefónica</span>
              <strong class="text-body"><?= e($telefonoCentro) ?></strong>
            </div>
          </li>

          <li class="d-flex align-items-start gap-3">
            <div class="bg-white p-2 rounded-3 shadow-xs border text-primary">
              <i class="bi bi-envelope fs-5"></i>
            </div>
            <div>
              <span class="d-block text-muted small">Correo electrónico</span>
              <a href="mailto:<?= e($emailCentro) ?>" class="text-decoration-none fw-bold"><?= e($emailCentro) ?></a>
            </div>
          </li>
        </ul>

        <?php if (!empty($comoLlegar)): ?>
          <hr class="my-4">
          <div class="d-flex align-items-start gap-2">
            <i class="bi bi-bus-front-fill text-primary mt-1"></i>
            <div>
              <strong class="d-block small text-body mb-1">Cómo llegar (transporte público):</strong>
              <p class="small text-muted mb-0"><?= e($comoLlegar) ?></p>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
