<?php
/**
 * BookSwap · Edición de Ejemplar Físico (Admin / Personal).
 * Permite cambiar la ubicación, estado o condición de una copia física.
 */
declare(strict_types=1);

$ejemplar = $ejemplar ?? [];
$error = $error ?? null;
$id = (int) ($ejemplar['id'] ?? 0);
?>

<div class="container-xxl py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h2 fw-800 mb-1">Editar Ejemplar Físico #<?= $id ?></h1>
      <p class="text-muted mb-0">Libro: <strong><?= e($ejemplar['libro_titulo'] ?? ('Libro #' . ($ejemplar['libro_id'] ?? ''))) ?></strong></p>
    </div>
    <a href="/admin/ejemplares?libro_id=<?= (int) ($ejemplar['libro_id'] ?? 0) ?>" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Volver a Ejemplares
    </a>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4 shadow-sm" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
  <?php endif; ?>

  <div class="card border-0 shadow-sm rounded-4" style="max-width: 700px;">
    <div class="card-body p-4">
      <form method="POST" action="/admin/ejemplares/editar" class="row g-3">
        <?= csrf_campo() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="libro_id" value="<?= (int) ($ejemplar['libro_id'] ?? 0) ?>">

        <div class="col-12 col-sm-6">
          <label for="campo-ubicacion" class="form-label small fw-bold">Ubicación en Estantería</label>
          <input type="text" id="campo-ubicacion" name="ubicacion" class="form-control font-monospace"
                 value="<?= e($ejemplar['ubicacion'] ?? 'A-01-01') ?>" required>
        </div>

        <div class="col-12 col-sm-6">
          <label for="campo-condicion" class="form-label small fw-bold">Estado de Conservación</label>
          <select id="campo-condicion" name="condicion" class="form-select">
            <option value="como_nuevo" <?= ($ejemplar['condicion'] ?? '') === 'como_nuevo' ? 'selected' : '' ?>>Como nuevo</option>
            <option value="bueno" <?= ($ejemplar['condicion'] ?? '') === 'bueno' ? 'selected' : '' ?>>Buen estado</option>
            <option value="aceptable" <?= ($ejemplar['condicion'] ?? '') === 'aceptable' ? 'selected' : '' ?>>Aceptable</option>
            <option value="nuevo" <?= ($ejemplar['condicion'] ?? '') === 'nuevo' ? 'selected' : '' ?>>Nuevo</option>
            <option value="deteriorado" <?= ($ejemplar['condicion'] ?? '') === 'deteriorado' ? 'selected' : '' ?>>Deteriorado</option>
          </select>
        </div>

        <div class="col-12 col-sm-6">
          <label for="campo-estado" class="form-label small fw-bold">Estado del Ejemplar</label>
          <select id="campo-estado" name="estado" class="form-select">
            <option value="disponible" <?= ($ejemplar['estado'] ?? '') === 'disponible' ? 'selected' : '' ?>>Disponible</option>
            <option value="reservado" <?= ($ejemplar['estado'] ?? '') === 'reservado' ? 'selected' : '' ?>>Reservado</option>
            <option value="retirado" <?= ($ejemplar['estado'] ?? '') === 'retirado' ? 'selected' : '' ?>>Retirado (en préstamo)</option>
            <option value="baja" <?= ($ejemplar['estado'] ?? '') === 'baja' ? 'selected' : '' ?>>Baja (retirado del fondo)</option>
          </select>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2 pt-3 border-top mt-4">
          <a href="/admin/ejemplares?libro_id=<?= (int) ($ejemplar['libro_id'] ?? 0) ?>" class="btn btn-secondary">Cancelar</a>
          <button type="submit" class="btn btn-primary fw-bold px-4">
            <i class="bi bi-save me-1"></i>Actualizar Ejemplar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
