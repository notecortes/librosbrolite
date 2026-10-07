<?php
/**
 * BookSwap · Gestión de Libros del Catálogo (Admin / Personal).
 * Listado de libros para el equipo del centro con acciones de edición y copias.
 */
declare(strict_types=1);

$libros = $libros ?? [];
$filtros = $filtros ?? [];
$pagina = (int) ($pagina ?? 1);
$totalPaginas = (int) ($totalPaginas ?? 1);
$total = (int) ($total ?? 0);
?>

<div class="container-xxl py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
      <h1 class="h2 fw-800 mb-1">Gestión de Catálogo de Libros</h1>
      <p class="text-muted mb-0">Administra los títulos admitidos, consulta disponibilidad y gestiona ejemplares.</p>
    </div>
    <div class="d-flex gap-2">
      <?php if (puede('csv.importar')): ?>
      <a href="/admin/csv?tipo=catalogo" class="btn btn-outline-success fw-bold shadow-sm">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Importar CSV
      </a>
      <?php endif; ?>
      <a href="/admin/libros/nuevo" class="btn btn-primary fw-bold shadow-sm">
        <i class="bi bi-plus-lg me-1"></i>Nuevo Libro (Escáner/ISBN)
      </a>
      <a href="/admin/ejemplares" class="btn btn-outline-secondary fw-semibold">
        <i class="bi bi-bookshelf me-1"></i>Ver Ejemplares
      </a>
    </div>
  </div>

  <!-- Buscador rápido -->
  <div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
      <form method="GET" action="/admin/libros" class="row g-2 align-items-center">
        <div class="col-12 col-md-10">
          <div class="input-group">
            <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" name="q" class="form-control border-start-0" 
                   placeholder="Buscar por título, autor, grupo/género o ISBN..." 
                   value="<?= e($filtros['q'] ?? '') ?>">
          </div>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary w-100 fw-semibold">Buscar</button>
          <?php if (!empty($filtros['q'])): ?>
            <a href="/admin/libros" class="btn btn-outline-secondary" title="Limpiar"><i class="bi bi-x-lg"></i></a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Tabla de libros -->
  <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col" style="width: 70px;">ID</th>
              <th scope="col" style="width: 60px;">Portada</th>
              <th scope="col">Título y Autor</th>
              <th scope="col">ISBN-13</th>
              <th scope="col">Grupo / Género</th>
              <th scope="col" class="text-center">Ejemplares</th>
              <th scope="col" class="text-end" style="width: 210px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($libros)): ?>
              <tr>
                <td colspan="7" class="text-center py-5 text-muted">
                  <i class="bi bi-journal-x display-6 d-block mb-2"></i>
                  <p class="mb-2">No se encontraron libros en el catálogo con los filtros actuales.</p>
                  <?php 
                    $qLimpio = trim((string) ($filtros['q'] ?? ''));
                    $esPosibleIsbn = (bool) preg_match('/^[0-9Xx\- ]{9,18}$/', $qLimpio);
                  ?>
                  <?php if ($esPosibleIsbn): ?>
                    <?php $isbnNormalizado = preg_replace('/[^0-9Xx]/', '', $qLimpio); ?>
                    <div class="mt-3">
                      <p class="small text-muted mb-2">¿Deseas buscarlo externamente e incorporarlo al catálogo?</p>
                      <a href="/admin/libros/nuevo?isbn=<?= e($isbnNormalizado) ?>" class="btn btn-primary btn-sm fw-bold shadow-sm">
                        <i class="bi bi-upc-scan me-1"></i>Incorporar libro con ISBN «<?= e($isbnNormalizado) ?>»
                      </a>
                    </div>
                  <?php else: ?>
                    <a href="/admin/libros/nuevo" class="btn btn-outline-primary btn-sm fw-semibold mt-2">
                      <i class="bi bi-plus-lg me-1"></i>Dar de alta nuevo libro
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($libros as $l): ?>
                <?php
                  $disp = (int) ($l['disponibles_count'] ?? 0);
                  $tot = (int) ($l['total_ejemplares'] ?? 0);
                  $portada = catalogo_resolver_url_portada($l);
                  $fallbackSvg = '/portada-svg?titulo=' . urlencode($l['titulo']) . '&autor=' . urlencode($l['autor']);
                ?>
                <tr>
                  <td class="fw-bold text-muted">#<?= (int) $l['id'] ?></td>
                  <td>
                    <img src="<?= e($portada) ?>" alt="" width="40" height="55" class="rounded object-fit-cover shadow-xs border"
                         referrerpolicy="no-referrer"
                         data-fallback="<?= e($fallbackSvg) ?>"
                         onerror="this.onerror=null; this.src=this.dataset.fallback;">
                  </td>
                  <td>
                    <div class="d-flex align-items-center gap-1">
                      <a href="/libro/<?= (int) $l['id'] ?>" class="fw-bold text-body text-decoration-none">
                        <?= e($l['titulo']) ?>
                      </a>
                      <?php if (($l['estado'] ?? 'activo') === 'baja'): ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle small px-2 py-0" style="font-size: 0.72rem;">Baja</span>
                      <?php endif; ?>
                    </div>
                    <span class="small text-muted"><?= e($l['autor']) ?> <?= !empty($l['anio']) ? '(' . e((string)$l['anio']) . ')' : '' ?></span>
                  </td>
                  <td>
                    <?php if (!empty($l['isbn13'])): ?>
                      <code class="small text-dark"><?= e($l['isbn13']) ?></code>
                    <?php else: ?>
                      <span class="text-muted small">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($l['genero'])): ?>
                      <span class="badge bg-light text-secondary border small"><?= e($l['genero']) ?></span>
                    <?php else: ?>
                      <span class="text-muted small">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <div class="d-flex flex-wrap justify-content-center gap-1">
                      <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill small" title="Disponibles">
                        <?= (int) ($l['disponibles_count'] ?? 0) ?> disp.
                      </span>
                      <?php if (!empty($l['reservadas_count'])): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill small" title="Reservadas">
                          <?= (int) $l['reservadas_count'] ?> res.
                        </span>
                      <?php endif; ?>
                      <?php if (!empty($l['retiradas_count'])): ?>
                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill small" title="En préstamo">
                          <?= (int) $l['retiradas_count'] ?> prést.
                        </span>
                      <?php endif; ?>
                      <?php if (!empty($l['bajas_count'])): ?>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill small" title="Baja">
                          <?= (int) $l['bajas_count'] ?> baja
                        </span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <a href="/libros/entrada?libro_id=<?= (int) $l['id'] ?>" class="btn btn-outline-success" title="Añadir copias">
                        <i class="bi bi-box-arrow-in-down me-1"></i>Añadir copias
                      </a>
                      <a href="/admin/libros/editar?id=<?= (int) $l['id'] ?>" class="btn btn-outline-secondary" title="Editar metadatos">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <a href="/libro/<?= (int) $l['id'] ?>" class="btn btn-outline-primary" title="Ver ficha del libro">
                        <i class="bi bi-eye"></i>
                      </a>
                      <?php if (($l['estado'] ?? 'activo') === 'baja'): ?>
                        <button type="button" class="btn btn-outline-success" title="Reactivar libro"
                                data-bs-toggle="modal" data-bs-target="#modalReactivarLibroIndex"
                                data-libro-id="<?= (int) $l['id'] ?>"
                                data-libro-titulo="<?= e($l['titulo']) ?>">
                          <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                      <?php else: ?>
                        <button type="button" class="btn btn-outline-warning" title="Dar de baja libro"
                                data-bs-toggle="modal" data-bs-target="#modalBajaLibroIndex"
                                data-libro-id="<?= (int) $l['id'] ?>"
                                data-libro-titulo="<?= e($l['titulo']) ?>">
                          <i class="bi bi-slash-circle"></i>
                        </button>
                      <?php endif; ?>
                      <button type="button" class="btn btn-outline-danger" title="Eliminar del catálogo"
                              data-bs-toggle="modal" data-bs-target="#modalEliminarLibroIndex"
                              data-libro-id="<?= (int) $l['id'] ?>"
                              data-libro-titulo="<?= e($l['titulo']) ?>">
                        <i class="bi bi-trash3"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Paginación -->
    <?php if ($totalPaginas > 1): ?>
      <div class="card-footer bg-transparent border-top p-3 d-flex justify-content-between align-items-center">
        <span class="small text-muted">Total: <?= $total ?> libros catalogados</span>
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="/admin/libros?<?= http_build_query(array_merge($filtros, ['pagina' => $pagina - 1])) ?>">Anterior</a>
          </li>
          <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
            <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
              <a class="page-link" href="/admin/libros?<?= http_build_query(array_merge($filtros, ['pagina' => $p])) ?>"><?= $p ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
            <a class="page-link" href="/admin/libros?<?= http_build_query(array_merge($filtros, ['pagina' => $pagina + 1])) ?>">Siguiente</a>
          </li>
        </ul>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal: Confirmar Eliminación de Libro (Index) -->
<div class="modal fade" id="modalEliminarLibroIndex" tabindex="-1" aria-labelledby="modalEliminarLibroIndexLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <form method="POST" action="/admin/libros/eliminar">
        <?= csrf_campo() ?>
        <input type="hidden" name="libro_id" id="eliminarLibroId" value="">
        <input type="hidden" name="retorno" value="/admin/libros">
        
        <div class="modal-header border-0 pb-0">
          <h2 class="h5 fw-bold modal-title text-danger" id="modalEliminarLibroIndexLabel">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>Eliminar Libro del Catálogo
          </h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p class="mb-3">
            ¿Estás seguro de que deseas eliminar permanentemente el libro <strong id="eliminarLibroTitulo" class="text-body"></strong> del catálogo?
          </p>
          <div class="alert alert-warning small border-0 rounded-3 mb-0">
            <ul class="mb-0 ps-3">
              <li>Se eliminarán todas sus copias físicas y registros del catálogo.</li>
              <li>Si existen <strong>reservas activas</strong>, se cancelarán automáticamente y se reembolsarán los tokens bloqueados a los lectores con notificación.</li>
              <li>Esta acción no se puede deshacer.</li>
            </ul>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-danger fw-bold">
            <i class="bi bi-trash3 me-1"></i>Sí, Eliminar Libro
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Confirmar Baja de Libro (Index) -->
<div class="modal fade" id="modalBajaLibroIndex" tabindex="-1" aria-labelledby="modalBajaLibroIndexLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <form method="POST" action="/admin/libros/baja">
        <?= csrf_campo() ?>
        <input type="hidden" name="libro_id" id="bajaLibroId" value="">
        <input type="hidden" name="retorno" value="/admin/libros">
        
        <div class="modal-header border-0 pb-0">
          <h2 class="h5 fw-bold modal-title text-warning-emphasis" id="modalBajaLibroIndexLabel">
            <i class="bi bi-slash-circle me-2 text-warning"></i>Dar de Baja Libro del Catálogo
          </h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p class="mb-3">
            ¿Confirmas la baja del libro <strong id="bajaLibroTitulo" class="text-body"></strong> del catálogo?
          </p>
          <div class="alert alert-warning small border-0 rounded-3 mb-3">
            <ul class="mb-0 ps-3">
              <li>El libro pasará al estado <code>baja</code> y dejará de ofrecerse en búsquedas públicas.</li>
              <li>Todas sus copias físicas asociadas se marcarán en estado <code>baja</code>.</li>
              <li>Si existen <strong>reservas activas pendientes</strong>, se cancelarán de inmediato y se devolverán los tokens bloqueados a los lectores con notificación.</li>
            </ul>
          </div>
          <div class="mb-2">
            <label for="motivo-baja-libro-index" class="form-label small fw-bold">Motivo de la baja (obligatorio):</label>
            <textarea id="motivo-baja-libro-index" name="motivo" class="form-control" rows="3" required
                      placeholder="Ej: Retirado del currículo escolar, libros obsoletos, extraviados o deteriorados..."></textarea>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning fw-bold text-dark">
            <i class="bi bi-slash-circle me-1"></i>Confirmar Baja
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Reactivar Libro (Index) -->
<div class="modal fade" id="modalReactivarLibroIndex" tabindex="-1" aria-labelledby="modalReactivarLibroIndexLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <form method="POST" action="/admin/libros/reactivar">
        <?= csrf_campo() ?>
        <input type="hidden" name="libro_id" id="reactivarLibroId" value="">
        <input type="hidden" name="retorno" value="/admin/libros">
        
        <div class="modal-header border-0 pb-0">
          <h2 class="h5 fw-bold modal-title text-success" id="modalReactivarLibroIndexLabel">
            <i class="bi bi-arrow-counterclockwise me-2"></i>Reactivar Libro en el Catálogo
          </h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">
            El libro <strong id="reactivarLibroTitulo" class="text-body"></strong> volverá a estar en estado <code>activo</code> en el catálogo. Podrás incorporar nuevos ejemplares o reactivar los existentes cuando lo desees.
          </p>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success fw-bold">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Reactivar Libro
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var modalDel = document.getElementById('modalEliminarLibroIndex');
  if (modalDel) {
    modalDel.addEventListener('show.bs.modal', function(event) {
      var btn = event.relatedTarget;
      if (btn) {
        document.getElementById('eliminarLibroId').value = btn.getAttribute('data-libro-id') || '';
        document.getElementById('eliminarLibroTitulo').textContent = btn.getAttribute('data-libro-titulo') || '';
      }
    });
  }

  var modalBaja = document.getElementById('modalBajaLibroIndex');
  if (modalBaja) {
    modalBaja.addEventListener('show.bs.modal', function(event) {
      var btn = event.relatedTarget;
      if (btn) {
        document.getElementById('bajaLibroId').value = btn.getAttribute('data-libro-id') || '';
        document.getElementById('bajaLibroTitulo').textContent = btn.getAttribute('data-libro-titulo') || '';
      }
    });
  }

  var modalReac = document.getElementById('modalReactivarLibroIndex');
  if (modalReac) {
    modalReac.addEventListener('show.bs.modal', function(event) {
      var btn = event.relatedTarget;
      if (btn) {
        document.getElementById('reactivarLibroId').value = btn.getAttribute('data-libro-id') || '';
        document.getElementById('reactivarLibroTitulo').textContent = btn.getAttribute('data-libro-titulo') || '';
      }
    });
  }
});
</script>
