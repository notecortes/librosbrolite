<?php
/**
 * BookSwap · Gestión de Copias de Seguridad (Panel de Administración).
 *
 * Funcionalidades (§12):
 * - Generación de copias manuales SQL completas en un solo clic.
 * - Listado histórico con tamaño, tipo y usuario autor.
 * - Descarga de volcados para custodia externa.
 * - Restauración con confirmación directa sobre la base de datos.
 */
declare(strict_types=1);

$backups = $backups ?? [];
$config = $config ?? [];
?>

<div class="container-xxl py-4">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-2 border-bottom">
    <div>
      <h1 class="h3 fw-bold mb-1">
        <i class="bi bi-database-check text-primary me-2"></i>Copias de Seguridad (Backups)
      </h1>
      <p class="text-muted small mb-0">Genera respaldos completos de la base de datos y restaura el estado del sistema.</p>
    </div>

    <div class="d-flex gap-2">
      <!-- Botón para generar backup manual -->
      <form method="POST" action="/admin/backups/generar">
        <?= csrf_campo() ?>
        <button type="submit" class="btn btn-primary fw-bold shadow-sm">
          <i class="bi bi-plus-circle me-1"></i>Crear Backup Ahora
        </button>
      </form>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <!-- Información de configuración de backups -->
    <div class="col-12 col-md-4">
      <div class="card border-0 shadow-sm rounded-4 h-100 p-4 bg-light">
        <h2 class="h6 fw-bold mb-3 d-flex align-items-center gap-2">
          <i class="bi bi-gear-fill text-muted"></i>Política Activa
        </h2>
        <ul class="list-unstyled small mb-3 d-flex flex-column gap-2 text-muted">
          <li>Frecuencia automática: <strong>Cada <?= (int) ($config['dias_backup_auto'] ?? 7) ?> días</strong></li>
          <li>Retención máxima: <strong><?= (int) ($config['retencion_backups'] ?? 10) ?> archivos</strong></li>
          <li>Último automático: <strong><?= e($config['ultimo_backup_auto'] ?? 'Ninguno') ?></strong></li>
          <li>Directorio seguro: <code>/backups/</code> <span class="badge bg-success-subtle text-success">Protegido (.htaccess)</span></li>
        </ul>
        <a href="/admin/configuracion" class="btn btn-outline-secondary btn-sm mt-auto">
          Modificar Política
        </a>
      </div>
    </div>

    <!-- Subida manual de archivo SQL para restaurar -->
    <div class="col-12 col-md-8">
      <div class="card border-0 shadow-sm rounded-4 h-100 p-4">
        <h2 class="h6 fw-bold mb-2 text-danger d-flex align-items-center gap-2">
          <i class="bi bi-arrow-counterclockwise"></i>Restaurar desde Fichero SQL Externo
        </h2>
        <p class="small text-muted mb-3">
          Sube un archivo de copia de seguridad <code>.sql</code> previamente descargado para sobrescribir los datos actuales.
        </p>

        <form method="POST" action="/admin/backups/restaurar" enctype="multipart/form-data" onsubmit="return confirm('¡ATENCIÓN! La restauración reemplazará todas las tablas y datos actuales de BookSwap con los del archivo seleccionado. ¿Deseas continuar?');">
          <?= csrf_campo() ?>
          <div class="input-group">
            <input type="file" name="archivo_sql" class="form-control" accept=".sql" required>
            <button type="submit" class="btn btn-outline-danger fw-bold">
              <i class="bi bi-upload me-1"></i>Restaurar
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Tabla de copias existentes -->
  <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white border-bottom p-4">
      <h2 class="h5 fw-bold mb-0">Historial de Copias de Seguridad</h2>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light small text-uppercase">
          <tr>
            <th class="ps-4">Archivo</th>
            <th>Tipo</th>
            <th>Tamaño</th>
            <th>Fecha</th>
            <th>Generado por</th>
            <th class="text-end pe-4">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($backups)): ?>
            <tr>
              <td colspan="6" class="text-center py-5 text-muted">
                <i class="bi bi-database-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                No se ha registrado ninguna copia de seguridad todavía.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($backups as $b): ?>
              <tr>
                <td class="ps-4">
                  <div class="fw-bold font-monospace small"><?= e($b['archivo']) ?></div>
                </td>
                <td>
                  <span class="badge bg-<?= $b['tipo'] === 'manual' ? 'primary' : 'secondary' ?>-subtle text-<?= $b['tipo'] === 'manual' ? 'primary' : 'secondary' ?> border rounded-pill">
                    <?= e($b['tipo']) ?>
                  </span>
                </td>
                <td class="small">
                  <?= number_format(((int) ($b['tamano'] ?? 0)) / 1024, 1) ?> KB
                </td>
                <td class="small text-muted">
                  <?= e($b['fecha']) ?>
                </td>
                <td class="small">
                  <?= e($b['usuario_nombre'] ?? 'Sistema (Cron)') ?>
                </td>
                <td class="text-end pe-4">
                  <div class="btn-group btn-group-sm">
                    <a href="/admin/backups/descargar?id=<?= (int) $b['id'] ?>" class="btn btn-outline-secondary" title="Descargar">
                      <i class="bi bi-download"></i>
                    </a>
                    <form method="POST" action="/admin/backups/restaurar-id" class="d-inline" onsubmit="return confirm('¿Restaurar la copia «<?= e($b['archivo']) ?>»? Esta acción sobrescribirá la base de datos actual.');">
                      <?= csrf_campo() ?>
                      <input type="hidden" name="backup_id" value="<?= (int) $b['id'] ?>">
                      <button type="submit" class="btn btn-outline-danger" title="Restaurar esta copia">
                        <i class="bi bi-arrow-counterclockwise"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
