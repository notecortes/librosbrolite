<?php
/**
 * BookSwap · Trazabilidad de Ejemplar Físico (v4.6).
 * Timeline vertical con todo el ciclo de vida de la copia (§2.3).
 */
declare(strict_types=1);

$ejemplar = $ejemplar ?? [];
$eventos = $eventos ?? [];
$libroId = (int) ($ejemplar['libro_id'] ?? 0);
$portada = catalogo_resolver_url_portada($ejemplar);
$fallbackSvg = '/portada-svg?titulo=' . urlencode((string)($ejemplar['libro_titulo'] ?? '')) . '&autor=' . urlencode((string)($ejemplar['libro_autor'] ?? ''));

$badgeEstadoClass = [
    'disponible' => 'bg-success',
    'reservado'  => 'bg-warning text-dark',
    'retirado'   => 'bg-info text-dark',
    'baja'       => 'bg-danger',
];
?>

<div class="container-xxl py-4">
  <!-- Migas de pan -->
  <nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/dashboard" class="text-decoration-none">Inicio</a></li>
      <li class="breadcrumb-item"><a href="/catalogo" class="text-decoration-none">Catálogo</a></li>
      <li class="breadcrumb-item"><a href="/libro/<?= $libroId ?>" class="text-decoration-none"><?= e($ejemplar['libro_titulo'] ?? 'Libro') ?></a></li>
      <li class="breadcrumb-item active" aria-current="page">Copia #<?= (int) $ejemplar['id'] ?></li>
    </ol>
  </nav>

  <!-- Encabezado -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 border-bottom pb-3">
    <div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary px-3 py-2 fs-6 rounded-pill">
          <i class="bi bi-clock-history me-1"></i> Trazabilidad
        </span>
        <h1 class="h3 fw-bold mb-0">Copia Física #<?= (int) $ejemplar['id'] ?></h1>
      </div>
      <p class="text-muted small mb-0 mt-1">
        Línea temporal completa y auditoría de vida de este ejemplar en el centro.
      </p>
    </div>
    <div class="d-flex gap-2">
      <a href="/libro/<?= $libroId ?>" class="btn btn-outline-primary fw-semibold">
        <i class="bi bi-arrow-left me-1"></i>Volver a la ficha del libro
      </a>
      <a href="/libros/entrada?libro_id=<?= $libroId ?>" class="btn btn-success fw-semibold">
        <i class="bi bi-plus-lg me-1"></i>Añadir más copias
      </a>
    </div>
  </div>

  <div class="row g-4">
    <!-- Columna Izquierda: Tarjeta de la copia y libro -->
    <div class="col-12 col-lg-4">
      <div class="card border-0 shadow-sm rounded-4 overflow-hidden sticky-top bg-surface" style="top: 90px;">
        <div class="p-3 bg-body-tertiary text-center border-bottom">
          <div class="ratio ratio-3x4 rounded-3 overflow-hidden shadow-xs mx-auto border" style="max-width: 140px;">
            <img src="<?= e($portada) ?>" alt="Portada" class="object-fit-cover w-100 h-100"
                 data-fallback="<?= e($fallbackSvg) ?>"
                 onerror="this.onerror=null; this.src=this.dataset.fallback;">
          </div>
        </div>

        <div class="card-body p-4">
          <span class="badge <?= $badgeEstadoClass[$ejemplar['estado']] ?? 'bg-secondary' ?> px-3 py-1 rounded-pill mb-2 fw-semibold">
            Estado actual: <?= e(ucfirst((string) $ejemplar['estado'])) ?>
          </span>

          <h2 class="h5 fw-bold mb-1">
            <a href="/libro/<?= $libroId ?>" class="text-body text-decoration-none">
              <?= e($ejemplar['libro_titulo'] ?? '') ?>
            </a>
          </h2>
          <div class="text-muted small mb-3"><?= e($ejemplar['libro_autor'] ?? '') ?></div>

          <hr class="my-3">

          <div class="d-flex flex-column gap-2 small">
            <div>
              <span class="text-muted">Condición:</span>
              <strong class="text-capitalize"><?= e((string) $ejemplar['condicion']) ?></strong>
            </div>
            <div>
              <span class="text-muted">Ubicación actual:</span>
              <code class="fw-bold"><?= e((string) ($ejemplar['ubicacion'] ?: 'MOSTRADOR')) ?></code>
            </div>
            <div>
              <span class="text-muted">Fecha de ingreso:</span>
              <span><?= e(substr((string) $ejemplar['fecha_ingreso'], 0, 16)) ?></span>
            </div>
            <div>
              <span class="text-muted">Depositante original:</span>
              <?php if (!empty($ejemplar['depositante_nombre'])): ?>
                <strong><?= e($ejemplar['depositante_nombre']) ?></strong>
                
              <?php else: ?>
                <span class="badge bg-light text-secondary border">Fondo propio biblioteca</span>
              <?php endif; ?>
            </div>
          </div>

          <hr class="my-3">

          <!-- Acciones en línea de la copia -->
          <div class="d-flex flex-column gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill w-100" data-bs-toggle="modal" data-bs-target="#modalEditarCopia">
              <i class="bi bi-pencil me-1"></i>Editar condición / ubicación
            </button>

            <?php if ($ejemplar['estado'] !== 'baja'): ?>
              <button type="button" class="btn btn-outline-danger btn-sm rounded-pill w-100" data-bs-toggle="modal" data-bs-target="#modalBajaCopia">
                <i class="bi bi-slash-circle me-1"></i>Dar de baja esta copia
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Columna Derecha: Timeline Vertical de Trazabilidad -->
    <div class="col-12 col-lg-8">
      <div class="card border-0 shadow-sm rounded-4 p-4 bg-surface">
        <h2 class="h5 fw-bold mb-4">
          <i class="bi bi-bezier2 text-primary me-2"></i>Línea de Vida y Movimientos
        </h2>

        <?php if (empty($eventos)): ?>
          <div class="text-center py-5 text-muted">
            <i class="bi bi-hourglass-split display-6 d-block mb-2"></i>
            <p>No hay eventos registrados para esta copia física.</p>
          </div>
        <?php else: ?>
          <div class="timeline-v py-2">
            <?php foreach ($eventos as $ev): ?>
              <div class="timeline-v-item d-flex gap-3 mb-4 position-relative">
                <!-- Icono/Badge circular -->
                <div class="timeline-v-badge flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle <?= e($ev['badge_class'] ?? 'bg-primary') ?> text-white shadow-xs"
                     style="width: 42px; height: 42px; z-index: 2;">
                  <i class="bi <?= e($ev['icono'] ?? 'bi-dot') ?> fs-5"></i>
                </div>

                <!-- Tarjeta del evento -->
                <div class="timeline-v-content flex-grow-1 card border rounded-3 p-3 bg-surface shadow-xs">
                  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                    <span class="badge <?= e($ev['badge_class'] ?? 'bg-secondary') ?> rounded-pill small">
                      <?= e($ev['badge'] ?? 'Evento') ?>
                    </span>
                    <span class="small text-muted">
                      <i class="bi bi-clock me-1"></i><?= e((string) ($ev['fecha'] ?? '')) ?>
                    </span>
                  </div>

                  <h3 class="h6 fw-bold mb-1 text-body"><?= e($ev['titulo'] ?? '') ?></h3>
                  <p class="small text-secondary mb-0"><?= e($ev['descripcion'] ?? '') ?></p>

                  <?php if (!empty($ev['motivo'])): ?>
                    <div class="mt-2 p-2 bg-light rounded small border border-danger">
                      <strong>Motivo:</strong> <?= e($ev['motivo']) ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Modal para Editar Copia -->
<div class="modal fade" id="modalEditarCopia" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <form method="POST" action="/ejemplar/<?= (int) $ejemplar['id'] ?>/editar">
        <?= csrf_campo() ?>
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">
            <i class="bi bi-pencil text-primary me-2"></i>Editar Copia #<?= (int) $ejemplar['id'] ?>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body py-3">
          <div class="mb-3">
            <label class="form-label small fw-bold">Condición física:</label>
            <select name="condicion" class="form-select">
              <option value="nuevo" <?= $ejemplar['condicion'] === 'nuevo' ? 'selected' : '' ?>>Nuevo (impecable)</option>
              <option value="como_nuevo" <?= $ejemplar['condicion'] === 'como_nuevo' ? 'selected' : '' ?>>Como nuevo</option>
              <option value="bueno" <?= $ejemplar['condicion'] === 'bueno' ? 'selected' : '' ?>>Buen estado</option>
              <option value="aceptable" <?= $ejemplar['condicion'] === 'aceptable' ? 'selected' : '' ?>>Aceptable (con uso)</option>
              <option value="deteriorado" <?= $ejemplar['condicion'] === 'deteriorado' ? 'selected' : '' ?>>Deteriorado</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Ubicación en biblioteca:</label>
            <input type="text" name="ubicacion" class="form-control" value="<?= e((string) ($ejemplar['ubicacion'] ?: 'MOSTRADOR')) ?>" required>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary rounded-pill fw-bold px-4">Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal para Dar de Baja -->
<div class="modal fade" id="modalBajaCopia" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <form method="POST" action="/ejemplar/<?= (int) $ejemplar['id'] ?>/baja">
        <?= csrf_campo() ?>
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold text-danger">
            <i class="bi bi-slash-circle me-2"></i>Dar de Baja Copia #<?= (int) $ejemplar['id'] ?>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body py-3">
          <?php if ($ejemplar['estado'] === 'reservado'): ?>
            <div class="alert alert-warning mb-0">
              <i class="bi bi-exclamation-triangle me-1"></i>
              Esta copia se encuentra actualmente <strong>reservada</strong>. Debes cancelar primero la reserva antes de darla de baja.
            </div>
          <?php else: ?>
            <p class="text-muted small mb-3">
              Indica el motivo obligatorio de la baja (ej: deterioro grave, extravío, expurgo). Esta acción quedará auditada.
            </p>
            <div class="mb-3">
              <label class="form-label small fw-bold">Motivo de la baja:</label>
              <textarea name="motivo" class="form-control" rows="3" placeholder="Escribe el motivo..." required></textarea>
            </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancelar</button>
          <?php if ($ejemplar['estado'] !== 'reservado'): ?>
            <button type="submit" class="btn btn-danger rounded-pill fw-bold px-4">Confirmar Baja</button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
.timeline-v {
  position: relative;
}
.timeline-v::before {
  content: '';
  position: absolute;
  top: 15px;
  bottom: 15px;
  left: 20px;
  width: 2px;
  background-color: var(--bs-border-color, #dee2e6);
  z-index: 1;
}
</style>
