<?php
/**
 * BookSwap · Listado de Ejemplares Físicos (Admin / Personal).
 * Muestra el inventario de copias físicas con su estado, ubicación y condición.
 */
declare(strict_types=1);

$ejemplares = $ejemplares ?? [];
$libro = $libro ?? null;
?>

<div class="container-xxl py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
      <h1 class="h2 fw-800 mb-1">
        Gestión de Ejemplares Físicos
        <?php if ($libro): ?>
          <span class="fs-5 text-muted fw-normal d-block">Filtrado por: <?= e($libro['titulo']) ?></span>
        <?php endif; ?>
      </h1>
      <p class="text-muted mb-0">Control de inventario, estanterías, condiciones de conservación y estado.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="/admin/ejemplares/nuevo<?= $libro ? '?libro_id=' . (int) $libro['id'] : '' ?>" class="btn btn-primary fw-bold shadow-sm">
        <i class="bi bi-plus-lg me-1"></i>Añadir Copia Física
      </a>
      <a href="/admin/libros" class="btn btn-outline-secondary">
        <i class="bi bi-journal-bookmark me-1"></i>Catálogo de Libros
      </a>
    </div>
  </div>

  <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col" style="width: 70px;">ID</th>
              <th scope="col">Libro Asociado</th>
              <th scope="col">Ubicación</th>
              <th scope="col">Condición</th>
              <th scope="col">Estado</th>
              <th scope="col">Fecha Ingreso</th>
              <th scope="col" class="text-end" style="width: 140px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($ejemplares)): ?>
              <tr>
                <td colspan="7" class="text-center py-5 text-muted">
                  <i class="bi bi-bookshelf display-6 d-block mb-2"></i>
                  No hay ejemplares registrados <?= $libro ? 'para este libro.' : 'en el sistema.' ?>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($ejemplares as $ej): ?>
                <tr>
                  <td class="fw-bold text-muted">#<?= (int) $ej['id'] ?></td>
                  <td>
                    <a href="/libro/<?= (int) $ej['libro_id'] ?>" class="fw-bold text-body text-decoration-none">
                      <?= e($ej['libro_titulo'] ?? ('Libro #' . $ej['libro_id'])) ?>
                    </a>
                    <span class="small text-muted d-block"><?= e($ej['libro_autor'] ?? '') ?></span>
                  </td>
                  <td><code><?= e($ej['ubicacion'] ?? '—') ?></code></td>
                  <td>
                    <span class="badge bg-light text-dark border">
                      <?= e($ej['condicion'] ?? 'bueno') ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($ej['estado'] === 'disponible'): ?>
                      <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Disponible</span>
                    <?php elseif ($ej['estado'] === 'reservado'): ?>
                      <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill">Reservado</span>
                    <?php elseif ($ej['estado'] === 'retirado'): ?>
                      <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill">Retirado</span>
                    <?php else: ?>
                      <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">Baja</span>
                    <?php endif; ?>
                  </td>
                  <td class="small text-muted"><?= e(substr((string)($ej['fecha_ingreso'] ?? ''), 0, 10)) ?></td>
                  <td class="text-end">
                    <a href="/admin/ejemplares/editar?id=<?= (int) $ej['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Editar">
                      <i class="bi bi-pencil"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
