<?php
/**
 * BookSwap · admin/roles.php — Matriz de permisos del personal (v4.5).
 * Permite al ADMIN configurar de forma granular las capacidades del rol PERSONAL.
 */
declare(strict_types=1);

$dict = $dict ?? permisos_dict();
$permisosPersonal = $permisosPersonal ?? [];
$permisosBase = permisos_base();
$avisoMostrador = $avisoMostrador ?? null;

// Agrupar permisos por su clave de grupo
$grupos = [];
foreach ($dict as $codigo => $info) {
    $grupos[$info['grupo']][$codigo] = $info;
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1 small">
        <li class="breadcrumb-item"><a href="/admin">Administración</a></li>
        <li class="breadcrumb-item active" aria-current="page">Roles y Permisos</li>
      </ol>
    </nav>
    <h1 class="h2 fw-800 mb-1">Matriz de Permisos del Personal</h1>
    <p class="text-muted mb-0">Configura de manera flexible las capacidades y accesos para el equipo del centro.</p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <button type="button" class="btn btn-outline-danger fw-semibold" data-bs-toggle="modal" data-bs-target="#modalRestaurar">
      <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar por defecto
    </button>
    <button type="submit" form="form-roles" class="btn btn-primary fw-bold shadow-sm">
      <i class="bi bi-check2-circle me-1"></i>Guardar Cambios
    </button>
  </div>
</div>

<?php if (!empty($avisoMostrador)): ?>
  <div class="alert alert-warning border-0 shadow-sm rounded-4 d-flex align-items-center gap-3 mb-4" role="alert">
    <div class="fs-3 text-warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
    <div>
      <strong class="d-block">Aviso de configuración operativa</strong>
      <span><?= e($avisoMostrador) ?></span>
    </div>
  </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
  <div class="card-header bg-surface py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-shield-check text-primary fs-5"></i>
      <span class="fw-bold">Capacidades asignadas por rol</span>
    </div>
    <span class="badge bg-light text-muted border">RBAC Canónico</span>
  </div>

  <form id="form-roles" method="post" action="/admin/roles">
    <?= csrf_campo() ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light small text-uppercase fw-bold">
          <tr>
            <th style="min-width: 300px;">Permiso y Descripción</th>
            <th class="text-center" style="width: 140px;">
              <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">ADMIN</span>
              <div class="small text-muted fw-normal mt-1" style="font-size:0.65rem;">Total (Lectura)</div>
            </th>
            <th class="text-center bg-primary-subtle" style="width: 160px;">
              <span class="badge bg-primary text-white px-2 py-1">PERSONAL</span>
              <div class="small text-primary fw-semibold mt-1" style="font-size:0.65rem;">Configurable</div>
            </th>
            <th class="text-center" style="width: 140px;">
              <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">USUARIO</span>
              <div class="small text-muted fw-normal mt-1" style="font-size:0.65rem;">Base (Fijo)</div>
            </th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($grupos as $nombreGrupo => $permisosGrupo): ?>
            <tr class="table-group-divider bg-light">
              <td colspan="4" class="fw-bold text-uppercase small text-muted px-4 py-2" style="letter-spacing: 0.5px;">
                <i class="bi bi-folder2-open me-2 text-primary"></i>Grupo: <?= e($nombreGrupo) ?>
                <?php if ($nombreGrupo === 'BASE'): ?>
                  <span class="badge bg-secondary-subtle text-secondary ms-2 fw-normal" style="font-size:0.7rem;">Obligatorios para todo usuario</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php foreach ($permisosGrupo as $codigo => $info): ?>
              <?php
                $esBase = in_array($codigo, $permisosBase, true);
                $activoPersonal = in_array($codigo, $permisosPersonal, true) || $esBase;
              ?>
              <tr>
                <td class="px-4 py-3">
                  <div class="fw-bold text-body mb-1">
                    <?= e($info['nombre']) ?>
                    <span class="font-monospace text-muted small fw-normal ms-2">(<?= e($codigo) ?>)</span>
                  </div>
                  <div class="small text-muted"><?= e($info['descripcion']) ?></div>
                </td>

                <!-- Columna ADMIN -->
                <td class="text-center py-3">
                  <div class="form-check d-inline-block">
                    <input class="form-check-input" type="checkbox" checked disabled aria-label="Permiso <?= e($codigo) ?> activo para Admin">
                  </div>
                </td>

                <!-- Columna PERSONAL -->
                <td class="text-center py-3 bg-primary-subtle bg-opacity-25">
                  <div class="form-check d-inline-block">
                    <?php if ($esBase): ?>
                      <input class="form-check-input" type="checkbox" checked disabled id="p_<?= e(str_replace('.', '_', $codigo)) ?>" aria-label="Permiso base <?= e($codigo) ?> activo para Personal">
                      <input type="hidden" name="permisos[]" value="<?= e($codigo) ?>">
                    <?php else: ?>
                      <input class="form-check-input" type="checkbox" name="permisos[]" value="<?= e($codigo) ?>"
                             id="p_<?= e(str_replace('.', '_', $codigo)) ?>"
                             <?= $activoPersonal ? 'checked' : '' ?>
                             aria-label="Activar permiso <?= e($codigo) ?> para Personal">
                    <?php endif; ?>
                  </div>
                </td>

                <!-- Columna USUARIO -->
                <td class="text-center py-3">
                  <div class="form-check d-inline-block">
                    <input class="form-check-input" type="checkbox" <?= $esBase ? 'checked' : '' ?> disabled aria-label="Permiso <?= e($codigo) ?> <?= $esBase ? 'activo' : 'inactivo' ?> para Usuario">
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-surface py-3 px-4 d-flex justify-content-between align-items-center">
      <span class="small text-muted">
        <i class="bi bi-info-circle me-1"></i>Los permisos BASE permanecen siempre activos y no pueden revocarse.
      </span>
      <button type="submit" class="btn btn-primary fw-bold shadow-sm">
        <i class="bi bi-check2-circle me-1"></i>Guardar Matriz
      </button>
    </div>
  </form>
</div>

<!-- Modal Confirmación para Restaurar Valores por Defecto -->
<div class="modal fade" id="modalRestaurar" tabindex="-1" aria-labelledby="modalRestaurarLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fs-5 fw-bold" id="modalRestaurarLabel">
          <i class="bi bi-exclamation-octagon text-danger me-2"></i>Restaurar Valores por Defecto
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body p-4">
        <p class="text-muted mb-3">
          ¿Estás seguro de que deseas restablecer la matriz de permisos del rol <strong>PERSONAL</strong> a los valores iniciales del sistema?
        </p>
        <p class="small text-muted mb-0">
          Se volverán a activar las capacidades operativas habituales de catálogo, mostrador e importaciones. Esta acción quedará registrada en el log de auditoría.
        </p>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
        <form method="post" action="/admin/roles/restaurar" class="m-0">
          <?= csrf_campo() ?>
          <button type="submit" class="btn btn-danger fw-bold rounded-3">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Confirmar y Restaurar
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
