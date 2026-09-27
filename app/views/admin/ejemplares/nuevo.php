<?php
/**
 * BookSwap · Añadir Ejemplar Físico (Admin / Personal).
 * Formulario para registrar una nueva copia física de un libro en el inventario.
 */
declare(strict_types=1);

$libros = $libros ?? [];
$libroSeleccionado = $libroSeleccionado ?? null;
$error = $error ?? null;
?>

<div class="container-xxl py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h2 fw-800 mb-1">Añadir Ejemplar Físico</h1>
      <p class="text-muted mb-0">Registra una nueva copia en estantería para un libro admitido.</p>
    </div>
    <a href="/admin/ejemplares" class="btn btn-outline-secondary">
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
      <form method="POST" action="/admin/ejemplares/nuevo" class="row g-3">
        <?= csrf_campo() ?>

        <div class="col-12">
          <label for="campo-libro" class="form-label small fw-bold">Libro del Catálogo <span class="text-danger">*</span></label>
          <select id="campo-libro" name="libro_id" class="form-select" required>
            <option value="">-- Selecciona un libro --</option>
            <?php foreach ($libros as $lib): ?>
              <?php $sel = ($libroSeleccionado && (int)$libroSeleccionado['id'] === (int)$lib['id']) ? 'selected' : ''; ?>
              <option value="<?= (int) $lib['id'] ?>" <?= $sel ?>>
                <?= e($lib['titulo']) ?> — <?= e($lib['autor']) ?> (ISBN: <?= e($lib['isbn13'] ?? 'S/N') ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-sm-6">
          <label for="campo-ubicacion" class="form-label small fw-bold">Ubicación en Estantería</label>
          <input type="text" id="campo-ubicacion" name="ubicacion" class="form-control font-monospace"
                 value="A-01-01" placeholder="Ej: B-02-04" required>
          <div class="form-text">Código de balda o sección física del centro.</div>
        </div>

        <div class="col-12 col-sm-6">
          <label for="campo-condicion" class="form-label small fw-bold">Estado de Conservación</label>
          <select id="campo-condicion" name="condicion" class="form-select">
            <option value="como_nuevo">Como nuevo</option>
            <option value="bueno" selected>Buen estado</option>
            <option value="aceptable">Aceptable</option>
            <option value="nuevo">Nuevo</option>
            <option value="deteriorado">Deteriorado</option>
          </select>
        </div>

        <div class="col-12 col-sm-6">
          <label for="campo-estado" class="form-label small fw-bold">Estado Inicial</label>
          <select id="campo-estado" name="estado" class="form-select">
            <option value="disponible" selected>Disponible para reserva</option>
            <option value="reservado">Reservado</option>
            <option value="retirado">Retirado</option>
            <option value="baja">Baja</option>
          </select>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2 pt-3 border-top mt-4">
          <a href="/admin/ejemplares" class="btn btn-secondary">Cancelar</a>
          <button type="submit" class="btn btn-primary fw-bold px-4">
            <i class="bi bi-check-lg me-1"></i>Registrar Ejemplar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
