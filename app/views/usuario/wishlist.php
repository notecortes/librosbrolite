<?php
/**
 * BookSwap · wishlist.php — Vista de la lista de deseos del usuario.
 *
 * Muestra:
 * - Lista de libros que el usuario está esperando.
 * - Disponibilidad en tiempo real (si ya hay ejemplares disponibles, permite reservar).
 * - Opción para quitar libros de la lista de avisos.
 * - Estado vacío si no hay ningún libro en seguimiento.
 */
declare(strict_types=1);

$items = $items ?? [];
$totalItems = count($items);
?>

<div class="container-xxl py-4">
  <!-- Encabezado de la página -->
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2 bg-primary-subtle text-primary border border-primary-subtle small fw-bold">
        <i class="bi bi-bell-fill"></i>
        <span>Avisos de Disponibilidad</span>
      </div>
      <h1 class="h2 fw-bold text-body mb-1">Mi Lista de Deseos</h1>
      <p class="text-muted mb-0 small">
        Libros que tienes en seguimiento. Te enviaremos un aviso en cuanto llegue un ejemplar disponible.
      </p>
    </div>

    <div>
      <a href="/catalogo" class="btn btn-outline-primary btn-sm fw-semibold px-3 rounded-pill shadow-xs">
        <i class="bi bi-search me-1"></i>Explorar Catálogo
      </a>
    </div>
  </div>

  <?php if (empty($items)): ?>
    <!-- Estado vacío -->
    <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-surface my-4">
      <div class="mb-3 text-muted">
        <i class="bi bi-bookmark-heart fs-1"></i>
      </div>
      <h2 class="h5 fw-bold text-body mb-2">Tu lista de deseos está vacía</h2>
      <p class="text-muted small mb-4" style="max-width: 440px; margin: 0 auto;">
        Cuando un libro del catálogo no tenga ejemplares disponibles, pulsa sobre <strong>«Avisarme cuando esté disponible»</strong> y aparecerá aquí. En cuanto un lector lo deposite, recibirás una notificación automática.
      </p>
      <div>
        <a href="/catalogo" class="btn btn-primary px-4 fw-bold rounded-pill">
          <i class="bi bi-journal-bookmark me-2"></i>Ver Catálogo de Libros
        </a>
      </div>
    </div>
  <?php else: ?>
    <!-- Tarjeta con el listado -->
    <div class="card border-0 shadow-sm rounded-4 bg-surface overflow-hidden">
      <div class="card-header bg-surface-2 border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
        <span class="fw-bold small text-body">
          Libros en seguimiento (<?= $totalItems ?>)
        </span>
        <span class="text-muted small">
          Aviso automático activo
        </span>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light small text-uppercase text-muted border-bottom">
            <tr>
              <th scope="col" style="width: 70px;">Portada</th>
              <th scope="col">Libro</th>
              <th scope="col">Disponibilidad</th>
              <th scope="col" class="d-none d-md-table-cell">Añadido el</th>
              <th scope="col" class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
              <?php $disponibles = (int) ($item['num_disponibles'] ?? 0); ?>
              <tr>
                <td>
                  <?php if (!empty($item['portada_url'])): ?>
                    <img src="<?= e($item['portada_url']) ?>" alt="Portada" class="rounded shadow-xs" width="46" height="64" style="object-fit: cover;" loading="lazy">
                  <?php else: ?>
                    <div class="bg-secondary-subtle rounded d-flex align-items-center justify-content-center text-secondary shadow-xs" style="width: 46px; height: 64px;">
                      <i class="bi bi-book fs-4"></i>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="/libro/<?= (int) $item['libro_id'] ?>" class="fw-bold text-decoration-none text-body d-block mb-1">
                    <?= e($item['titulo']) ?>
                  </a>
                  <div class="text-muted small">
                    <i class="bi bi-pen me-1"></i><?= e($item['autor']) ?>
                    <?php if (!empty($item['editorial'])): ?>
                      · <span class="text-secondary"><?= e($item['editorial']) ?></span>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <?php if ($disponibles > 0): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
                      <i class="bi bi-check-circle-fill me-1"></i>¡<?= $disponibles ?> disponible(s)!
                    </span>
                  <?php else: ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1 rounded-pill">
                      <i class="bi bi-clock-history me-1"></i>Agotado temporalmente
                    </span>
                  <?php endif; ?>
                </td>
                <td class="d-none d-md-table-cell text-muted small">
                  <?= date('d/m/Y', strtotime($item['fecha_deseo'])) ?>
                </td>
                <td class="text-end">
                  <div class="d-inline-flex gap-2">
                    <?php if ($disponibles > 0): ?>
                      <a href="/libro/<?= (int) $item['libro_id'] ?>" class="btn btn-sm btn-primary fw-semibold shadow-xs">
                        <i class="bi bi-bookmark-plus me-1"></i>Reservar
                      </a>
                    <?php else: ?>
                      <a href="/libro/<?= (int) $item['libro_id'] ?>" class="btn btn-sm btn-outline-secondary" title="Ver ficha del libro">
                        <i class="bi bi-eye"></i>
                      </a>
                    <?php endif; ?>

                    <form method="POST" action="/wishlist/eliminar" class="d-inline" onsubmit="return confirm('¿Dejar de recibir avisos para este libro?');">
                      <?= csrf_campo() ?>
                      <input type="hidden" name="libro_id" value="<?= (int) $item['libro_id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger" title="Quitar de la lista de deseos">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
