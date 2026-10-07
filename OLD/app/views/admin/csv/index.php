<?php
/**
 * BookSwap · Importador CSV (Admin / Personal).
 * Permite la importación masiva de libros al catálogo y de números de socio al pool con informe detallado.
 */
declare(strict_types=1);

$informe = $informe ?? null;
$tipoImportacion = $tipoImportacion ?? 'catalogo';
?>

<div class="container-xxl py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
      <h1 class="h2 fw-800 mb-1">Importación Masiva CSV</h1>
      <p class="text-muted mb-0">Carga por lotes de catálogo bibliográfico.</p>
    </div>
    <div class="d-flex gap-2">

      <a href="/admin/libros" class="btn btn-outline-secondary">
        <i class="bi bi-journal-bookmark me-1"></i>Ver Catálogo
      </a>
    </div>
  </div>



  <!-- Contenido de las pestañas -->
  
    <!-- Pestaña 1: Catálogo -->
    
      <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
          <div>
            <h2 class="h5 fw-bold mb-1">Cargar Archivo CSV de Libros</h2>
            <p class="small text-muted mb-0">Formatos admitidos: <strong>Escolar / Red</strong> (<code>Curso,Título,Autor,Editorial,Comentarios,Cantidad en la red</code>) o <strong>Estándar</strong> (<code>isbn;titulo;autor;editorial;anio;genero;idioma</code>).</p>
          </div>
          <div class="d-flex gap-2">
            <a href="/admin/csv/plantilla-catalogo" class="btn btn-sm btn-outline-primary fw-semibold">
              <i class="bi bi-download me-1"></i>Descargar Plantilla Escolar
            </a>
            <a href="/admin/csv/plantilla-catalogo-estandar" class="btn btn-sm btn-outline-secondary fw-semibold">
              <i class="bi bi-file-earmark-spreadsheet me-1"></i>Plantilla Estándar
            </a>
          </div>
        </div>
        <div class="card-body p-4 pt-2">
          <form method="POST" action="/admin/csv/catalogo" enctype="multipart/form-data">
            <?= csrf_campo() ?>

            <div class="mb-3">
              <label for="archivo-csv-cat" class="form-label small fw-bold">Seleccionar archivo CSV</label>
              <input class="form-control" type="file" id="archivo-csv-cat" name="archivo_csv" accept=".csv,text/csv,text/plain">
              <div class="form-text small">La aplicación extraerá automáticamente los campos necesarios (Título, Autor, Editorial, Curso, Comentarios y Cantidad de copias).</div>
            </div>

            <div class="mb-3">
              <label for="texto-csv-cat" class="form-label small fw-bold">O pegar contenido CSV directamente:</label>
              <textarea class="form-control font-monospace small" id="texto-csv-cat" name="texto_csv" rows="5" 
                        placeholder="Curso,Título,Autor,Editorial,Comentarios,Cantidad en la red&#10;1 ESO,Dr Doolittle,Hugh Lofting,Burlington Books,Activity Reader,6&#10;2 ESO,The Canterville Ghost,Oscar Wilde,Burlington Books,Activity Reader,1"></textarea>
            </div>

            <div class="form-check mb-4">
              <input class="form-check-input" type="checkbox" id="check-detener-cat" name="detener_ante_error" value="1">
              <label class="form-check-label small fw-semibold" for="check-detener-cat">
                Detener ante el primer error (todo-o-nada: revierte los cambios si una fila falla)
              </label>
            </div>

            <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm">
              <i class="bi bi-upload me-2"></i>Procesar Importación de Libros
            </button>
          </form>
        </div>
      </div>
    </div>

    </div></div>

  <!-- Informe detallado de la última importación realizada -->
  <?php if ($informe !== null): ?>
    <div class="card border-0 shadow-sm rounded-4 mt-4 overflow-hidden">
      <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
        <div>
          <h2 class="h5 fw-bold mb-1">Informe de Importación</h2>
          <p class="small text-muted mb-0"><?= e($informe['mensaje'] ?? '') ?></p>
        </div>
        <?php if (!empty($informe['detenido'])): ?>
          <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill fw-bold">
            <i class="bi bi-x-octagon me-1"></i>Importación Cancelada (Rollback)
          </span>
        <?php else: ?>
          <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fw-bold">
            <i class="bi bi-check-circle me-1"></i>Proceso Finalizado
          </span>
        <?php endif; ?>
      </div>

      <div class="card-body p-4 pt-2">
        <!-- Métricas resumidas -->
        <div class="row g-3 text-center mb-4">
          <div class="col-6 col-md-<?= !empty($informe['copias_creadas']) ? '2' : '3' ?>">
            <div class="p-3 bg-light rounded-4">
              <div class="fs-4 fw-800 text-body"><?= (int) ($informe['total_leidas'] ?? 0) ?></div>
              <div class="small text-muted fw-semibold">Filas Leídas</div>
            </div>
          </div>
          <div class="col-6 col-md-<?= !empty($informe['copias_creadas']) ? '3' : '3' ?>">
            <div class="p-3 bg-success-subtle rounded-4 border border-success-subtle">
              <div class="fs-4 fw-800 text-success"><?= (int) ($informe['importadas_count'] ?? 0) ?></div>
              <div class="small text-success fw-semibold">Libros Importados</div>
            </div>
          </div>
          <?php if (!empty($informe['copias_creadas'])): ?>
          <div class="col-6 col-md-3">
            <div class="p-3 bg-primary-subtle rounded-4 border border-primary-subtle">
              <div class="fs-4 fw-800 text-primary">+<?= (int) $informe['copias_creadas'] ?></div>
              <div class="small text-primary fw-semibold">Copias en Red Creadas</div>
            </div>
          </div>
          <?php endif; ?>
          <div class="col-6 col-md-<?= !empty($informe['copias_creadas']) ? '2' : '3' ?>">
            <div class="p-3 bg-warning-subtle rounded-4 border border-warning-subtle">
              <div class="fs-4 fw-800 text-warning"><?= (int) ($informe['duplicados_count'] ?? 0) ?></div>
              <div class="small text-warning fw-semibold">Duplicados Omitidos</div>
            </div>
          </div>
          <div class="col-6 col-md-<?= !empty($informe['copias_creadas']) ? '2' : '3' ?>">
            <div class="p-3 bg-danger-subtle rounded-4 border border-danger-subtle">
              <div class="fs-4 fw-800 text-danger"><?= (int) ($informe['errores_count'] ?? 0) ?></div>
              <div class="small text-danger fw-semibold">Filas con Error</div>
            </div>
          </div>
        </div>

        <!-- Tabla de Duplicados Omitidos -->
        <?php if (!empty($informe['duplicados'])): ?>
          <div class="mb-4">
            <h3 class="h6 fw-bold text-warning-emphasis mb-2">
              <i class="bi bi-exclamation-triangle me-1"></i>Duplicados Omitidos (<?= count($informe['duplicados']) ?>)
            </h3>
            <div class="table-responsive border rounded-3">
              <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width: 70px;">Línea</th>
                    <th>Elemento</th>
                    <th>Motivo de Omisión</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($informe['duplicados'] as $dup): ?>
                    <tr>
                      <td class="fw-bold">L-<?= (int) ($dup['linea'] ?? 0) ?></td>
                      <td>
                        <strong><?= e($dup['titulo'] ?? ($dup['numero'] ?? '')) ?></strong>
                        <?php if (!empty($dup['autor'])): ?>
                          <span class="text-muted small">por <?= e($dup['autor']) ?></span>
                        <?php endif; ?>
                      </td>
                      <td class="text-muted small"><?= e($dup['motivo'] ?? 'Ya existente') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>

        <!-- Tabla de Errores con Línea y Motivo -->
        <?php if (!empty($informe['errores'])): ?>
          <div class="mb-4">
            <h3 class="h6 fw-bold text-danger mb-2">
              <i class="bi bi-x-circle me-1"></i>Filas Erróneas (<?= count($informe['errores']) ?>)
            </h3>
            <div class="table-responsive border rounded-3">
              <table class="table table-sm table-hover mb-0">
                <thead class="table-danger">
                  <tr>
                    <th style="width: 70px;">Línea</th>
                    <th>Motivo del Error</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($informe['errores'] as $err): ?>
                    <tr>
                      <td class="fw-bold text-danger">L-<?= (int) ($err['linea'] ?? 0) ?></td>
                      <td><?= e($err['motivo'] ?? 'Error desconocido') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>

        <!-- Tabla de Importados con Éxito -->
        <?php if (!empty($informe['importados'])): ?>
          <div>
            <h3 class="h6 fw-bold text-success mb-2">
              <i class="bi bi-check-all me-1"></i>Registros Creados Exitosamente (<?= count($informe['importados']) ?>)
            </h3>
            <div class="table-responsive border rounded-3" style="max-height: 250px; overflow-y: auto;">
              <table class="table table-sm table-hover mb-0">
                <thead class="table-light sticky-top">
                  <tr>
                    <th style="width: 70px;">ID</th>
                    <th style="width: 70px;">Línea</th>
                    <th>Título / Código</th>
                    <th>Detalles</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($informe['importados'] as $imp): ?>
                    <tr>
                      <td class="fw-bold">#<?= (int) ($imp['id'] ?? 0) ?></td>
                      <td class="text-muted">L-<?= (int) ($imp['linea'] ?? 0) ?></td>
                      <td>
                        <strong><?= e($imp['titulo'] ?? ($imp['numero'] ?? '')) ?></strong>
                        <?php if (!empty($imp['curso'])): ?>
                          <span class="badge bg-secondary-subtle text-secondary border px-2 py-0 ms-1"><?= e($imp['curso']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($imp['copias'])): ?>
                          <span class="badge bg-success-subtle text-success border px-2 py-0 ms-1">+<?= (int)$imp['copias'] ?> copias</span>
                        <?php endif; ?>
                      </td>
                      <td class="small text-muted"><?= e($imp['autor'] ?? '') ?> <?= !empty($imp['isbn']) ? '(ISBN: ' . e($imp['isbn']) . ')' : '' ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>

      </div>
    </div>
  <?php endif; ?>
</div>
