<?php
/**
 * BookSwap · notificaciones.php — Vista de notificaciones in-app del usuario (v4.1).
 *
 * Muestra:
 * - Listado de notificaciones recibidas (reservas, expiraciones, bonos, avisos de cuenta).
 * - Distinción visual entre avisos leídos y no leídos.
 * - Enlace directo a la acción asociada (si existe).
 * - Botón para marcar todas las notificaciones como leídas.
 */
declare(strict_types=1);

$notificaciones = $notificaciones ?? [];
$totalNotificaciones = count($notificaciones);
$noLeidas = 0;
foreach ($notificaciones as $n) {
    if (empty($n['leida'])) {
        $noLeidas++;
    }
}
?>

<div class="container-xxl py-4">
  <!-- Encabezado de la página -->
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2 bg-primary-subtle text-primary border border-primary-subtle small fw-bold">
        <i class="bi bi-bell-fill"></i>
        <span>Centro de Avisos</span>
      </div>
      <h1 class="h2 fw-bold text-body mb-1">Mis Notificaciones</h1>
      <p class="text-muted mb-0 small">
        Avisos sobre el estado de tus reservas, activaciones y saldo en el centro.
      </p>
    </div>

    <?php if ($noLeidas > 0): ?>
      <div>
        <form method="POST" action="/notificaciones/marcar-leidas" class="d-inline">
          <?= csrf_campo() ?>
          <button type="submit" class="btn btn-outline-primary btn-sm fw-semibold px-3 rounded-pill shadow-xs">
            <i class="bi bi-check2-all me-1"></i>Marcar todas como leídas
          </button>
        </form>
      </div>
    <?php endif; ?>
  </div>

  <?php if (empty($notificaciones)): ?>
    <!-- Estado vacío -->
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-surface my-4">
      <div class="mb-3 text-muted">
        <i class="bi bi-bell-slash fs-1"></i>
      </div>
      <h2 class="h5 fw-bold text-body mb-2">No tienes notificaciones</h2>
      <p class="text-muted small mb-4" style="max-width: 420px; margin: 0 auto;">
        Aquí aparecerán los avisos sobre tus reservas activas, la disponibilidad de nuevos títulos y movimientos de tokens en el centro.
      </p>
      <div>
        <a href="/catalogo" class="btn btn-primary px-4 fw-bold rounded-pill">
          <i class="bi bi-book me-2"></i>Explorar el Catálogo
        </a>
      </div>
    </div>
  <?php else: ?>
    <!-- Lista de notificaciones -->
    <div class="card border-0 shadow-sm rounded-4 bg-surface overflow-hidden">
      <div class="card-header bg-surface-2 border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
        <span class="fw-bold small text-body">
          Historial de avisos (<?= $totalNotificaciones ?>)
        </span>
        <?php if ($noLeidas > 0): ?>
          <span class="badge bg-primary rounded-pill px-3 py-1">
            <?= $noLeidas ?> <?= $noLeidas === 1 ? 'pendiente' : 'pendientes' ?>
          </span>
        <?php else: ?>
          <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-1">
            Todas leídas
          </span>
        <?php endif; ?>
      </div>

      <div class="list-group list-group-flush">
        <?php foreach ($notificaciones as $item): ?>
          <?php
            $esLeida = !empty($item['leida']);
            $fechaFmt = date('d/m/Y H:i', strtotime($item['fecha']));
          ?>
          <div class="list-group-item p-4 border-bottom transition-all <?= $esLeida ? 'opacity-75' : 'bg-primary-subtle bg-opacity-10 border border-primary' ?>">
            <div class="d-flex align-items-start gap-3">
              <div class="p-2 rounded-circle <?= $esLeida ? 'bg-secondary-subtle text-muted' : 'bg-primary text-white shadow-xs' ?>">
                <i class="bi <?= $esLeida ? 'bi-bell' : 'bi-bell-fill' ?> fs-5"></i>
              </div>

              <div class="flex-grow-1">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                  <span class="small text-muted d-flex align-items-center gap-1">
                    <i class="bi bi-clock"></i>
                    <span><?= e($fechaFmt) ?></span>
                  </span>

                  <?php if (!$esLeida): ?>
                    <span class="badge bg-primary text-white rounded-pill small px-2 py-1">
                      Nueva
                    </span>
                  <?php endif; ?>
                </div>

                <p class="mb-3 text-body <?= $esLeida ? '' : 'fw-semibold' ?>">
                  <?= e($item['mensaje']) ?>
                </p>

                <div class="d-flex flex-wrap align-items-center gap-2">
                  <?php if (!empty($item['url'])): ?>
                    <a href="<?= $esLeida ? e($item['url']) : '/notificaciones/abrir?id=' . (int)$item['id'] ?>" class="btn btn-sm <?= $esLeida ? 'btn-outline-secondary' : 'btn-primary' ?> rounded-pill px-3 fw-semibold">
                      <span>Ver detalle</span>
                      <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                  <?php endif; ?>

                  <?php if (!$esLeida): ?>
                    <a href="/notificaciones/marcar-leida?id=<?= (int)$item['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
                      <i class="bi bi-check2 me-1"></i>Marcar como leída
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
