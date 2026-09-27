<?php
/**
 * BookSwap · Visítanos (Página Pública de Punto Físico).
 *
 * Muestra (§8.10):
 * - Dirección física exacta del centro.
 * - Teléfono de contacto y email.
 * - Estado en tiempo real del horario ("Abierto ahora / Cerrado") mediante abierto_ahora().
 * - Tabla con el horario semanal desglosado.
 * - Mapa interactivo sin API key (Google Maps embed u OpenStreetMap según configuración).
 * - Enlace directo "Cómo llegar" con coordenadas GPS.
 */
declare(strict_types=1);

$config = $config ?? [];
$nombreCentro    = (string) ($config['centro_nombre'] ?? 'LibrosBro — Biblioteca Ciudadana');
$direccionCentro = (string) ($config['centro_direccion'] ?? 'Calle de los Libros 42, 28004 Madrid');
$telefonoCentro  = (string) ($config['centro_telefono'] ?? '+34 910 123 456');
$emailCentro     = (string) ($config['centro_email'] ?? 'hola@bookswap.local');
$comoLlegar      = (string) ($config['centro_como_llegar'] ?? '');
$lat             = (string) ($config['centro_mapa_lat'] ?? '40.4168');
$lng             = (string) ($config['centro_mapa_lng'] ?? '-3.7038');
$proveedorMapa   = (string) ($config['centro_mapa_proveedor'] ?? 'google');
$horarioJson     = $config['centro_horario'] ?? null;

// Cálculo en servidor del estado de apertura actual
[$estaAbierto, $mensajeApertura] = abierto_ahora($horarioJson);
$horarioSemanal = horario_parsear($horarioJson);

$nombresDias = [
    'lunes'     => 'Lunes',
    'martes'    => 'Martes',
    'miercoles' => 'Miércoles',
    'jueves'    => 'Jueves',
    'viernes'   => 'Viernes',
    'sabado'    => 'Sábado',
    'domingo'   => 'Domingo',
];
?>

<div class="container-xxl py-5">
  <div class="row align-items-center justify-content-between mb-5 g-4">
    <div class="col-12 col-lg-7">
      <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3 border <?= $estaAbierto ? 'bg-success-subtle text-success border-success-subtle' : 'bg-secondary-subtle text-secondary border-secondary-subtle' ?>">
        <span class="spinner-grow spinner-grow-sm" role="status"></span>
        <strong class="small"><?= e($mensajeApertura) ?></strong>
      </div>
      <h1 class="display-5 fw-bold text-body-emphasis mb-3">Visítanos en nuestro centro</h1>
      <p class="lead text-muted mb-4">
        Un espacio ciudadano abierto para el intercambio de libros, lectura compartida y dinamización cultural comunitaria.
      </p>

      <div class="d-flex flex-wrap gap-3">
        <a href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode($lat . ',' . $lng) ?>"
           target="_blank"
           rel="noopener noreferrer"
           class="btn btn-primary btn-lg fw-bold px-4 shadow-sm">
          <i class="bi bi-compass me-2"></i>Cómo llegar
        </a>
        <a href="tel:<?= e($telefonoCentro) ?>" class="btn btn-outline-secondary btn-lg px-4">
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
      </div>
    </div>
  </div>

  <div class="row g-4 mb-5">
    <!-- Horario Semanal -->
    <div class="col-12 col-md-5 col-lg-4">
      <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
        <div class="card-header bg-white border-bottom p-4">
          <h2 class="h5 fw-bold mb-1 d-flex align-items-center gap-2">
            <i class="bi bi-clock-history text-primary"></i>Horario de Apertura
          </h2>
          <span class="small text-muted">Horarios de atención en sala y préstamos</span>
        </div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush">
            <?php foreach ($nombresDias as $claveDia => $nombreDia): ?>
              <?php $tramos = $horarioSemanal[$claveDia] ?? []; ?>
              <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                <span class="fw-medium text-body"><?= $nombreDia ?></span>
                <?php if (!empty($tramos)): ?>
                  <span class="badge bg-light text-body border rounded-pill small">
                    <?= e(implode(' · ', $tramos)) ?>
                  </span>
                <?php else: ?>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill small">
                    Cerrado
                  </span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>

    <!-- Mapa Interactivo -->
    <div class="col-12 col-md-7 col-lg-8">
      <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
        <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
          <h2 class="h5 fw-bold mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-map text-primary"></i>Localización en el Mapa
          </h2>
          <span class="badge bg-light text-muted border">
            <?= strtoupper(e($proveedorMapa)) ?>
          </span>
        </div>
        <div class="card-body p-0 position-relative" style="min-height: 380px;">
          <?php if ($proveedorMapa === 'osm'): ?>
            <!-- OpenStreetMap Embed -->
            <iframe width="100%"
                    height="100%"
                    style="border:0; min-height: 380px;"
                    loading="lazy"
                    title="Mapa OpenStreetMap"
                    src="https://www.openstreetmap.org/export/embed.html?bbox=<?= ((float)$lng-0.008) ?>%2C<?= ((float)$lat-0.005) ?>%2C<?= ((float)$lng+0.008) ?>%2C<?= ((float)$lat+0.005) ?>&amp;layer=mapnik&amp;marker=<?= urlencode($lat . ',' . $lng) ?>">
            </iframe>
          <?php else: ?>
            <!-- Google Maps Embed sin API Key -->
            <iframe width="100%"
                    height="100%"
                    style="border:0; min-height: 380px;"
                    loading="lazy"
                    title="Mapa Google Maps"
                    src="https://maps.google.com/maps?q=<?= urlencode($lat . ',' . $lng) ?>&amp;hl=es&amp;z=15&amp;output=embed">
            </iframe>
          <?php endif; ?>
        </div>
        <?php if (!empty($comoLlegar)): ?>
          <div class="card-footer bg-light border-top p-4">
            <div class="d-flex align-items-start gap-2">
              <i class="bi bi-info-circle-fill text-primary mt-1"></i>
              <div>
                <strong class="d-block small text-body mb-1">Indicaciones de transporte público:</strong>
                <p class="small text-muted mb-0"><?= e($comoLlegar) ?></p>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
