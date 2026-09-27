<?php
/**
 * BookSwap · Confirmar Reserva (Usuario).
 * Pantalla previa para validar los términos de la reserva de un ejemplar antes de confirmarla.
 */
declare(strict_types=1);

$libro = $libro ?? [];
$config = $config ?? [];
$costeLibro = (int) ($config['coste_libro'] ?? 1);
$horasReserva = (int) ($config['horas_reserva'] ?? 72);
?>

<div class="container-xxl py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
      <div class="card border-0 shadow rounded-4 overflow-hidden">
        <div class="card-header bg-primary text-white p-4">
          <div class="d-flex align-items-center gap-2 mb-1">
            <i class="bi bi-bookmark-plus fs-4"></i>
            <h1 class="h4 fw-bold mb-0">Confirmar Reserva</h1>
          </div>
          <p class="small text-white-50 mb-0">Guarda una copia a tu nombre para recogerla en el mostrador del centro.</p>
        </div>

        <div class="card-body p-4">
          <!-- Detalles del libro -->
          <div class="d-flex gap-3 align-items-center p-3 bg-light rounded-3 mb-4 border">
            <div style="width: 70px;" class="flex-shrink-0">
              <div class="ratio ratio-2x3 rounded-2 shadow-xs overflow-hidden bg-white border">
                <?php if (!empty($libro['portada_url'])): ?>
                  <img src="<?= e($libro['portada_url']) ?>" alt="<?= e($libro['titulo']) ?>" width="70" height="105" loading="lazy" class="object-fit-cover w-100 h-100">
                <?php else: ?>
                  <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                    <i class="bi bi-book fs-4"></i>
                  </div>
                <?php endif; ?>
              </div>
            </div>
            <div>
              <h2 class="h6 fw-bold mb-1"><?= e($libro['titulo']) ?></h2>
              <div class="small text-muted mb-1"><?= e($libro['autor'] ?? '') ?></div>
              <?php if (!empty($libro['genero'])): ?>
                <span class="badge bg-white text-secondary border rounded-pill small"><?= e($libro['genero']) ?></span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Condiciones de la reserva -->
          <h3 class="h6 fw-bold mb-3 text-uppercase text-muted" style="letter-spacing: 0.5px; font-size: 0.75rem;">
            Condiciones del préstamo ciudadano
          </h3>

          <ul class="list-group list-group-flush mb-4 rounded-3 border">
            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
              <div>
                <strong class="d-block small">Coste al reservar</strong>
                <span class="text-muted small">Se descuenta temporalmente hasta que recojas o anules la reserva.</span>
              </div>
              <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill fs-6 px-3">
                🪙 <?= $costeLibro ?> token
              </span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
              <div>
                <strong class="d-block small">Abono en mostrador al recoger</strong>
                <span class="text-muted small">Ya pagado por adelantado mediante el bloqueo del token.</span>
              </div>
              <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill fs-6 px-3 fw-bold">
                0 tokens (pagada)
              </span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
              <div>
                <strong class="d-block small">Plazo de recogida</strong>
                <span class="text-muted small">Tras este tiempo, el ejemplar volverá a estar libre.</span>
              </div>
              <span class="badge bg-light text-body border rounded-pill fs-6 px-3 fw-bold">
                <i class="bi bi-clock me-1"></i><?= $horasReserva ?> horas
              </span>
            </li>
          </ul>

          <!-- Formulario de confirmación -->
          <form method="POST" action="/reservar">
            <?= csrf_campo() ?>
            <input type="hidden" name="libro_id" value="<?= (int) $libro['id'] ?>">

            <div class="d-grid gap-2">
              <button type="submit" class="btn btn-primary btn-lg fw-bold shadow-sm">
                <i class="bi bi-check2-circle me-2"></i>Confirmar y Obtener Código
              </button>
              <a href="/libro/<?= (int) $libro['id'] ?>" class="btn btn-outline-secondary">
                Volver a la ficha del libro
              </a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
