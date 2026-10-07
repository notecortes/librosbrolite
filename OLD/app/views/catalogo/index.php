<?php
/**
 * BookSwap · Vista de Catálogo de Libros (Público).
 * Muestra el grid de libros con búsqueda, filtros, coste uniforme y estado de disponibilidad.
 */
declare(strict_types=1);

$libros = $libros ?? [];
$filtros = $filtros ?? [];
$generos = $generos ?? [];
$pagina = (int) ($pagina ?? 1);
$totalPaginas = (int) ($totalPaginas ?? 1);
$total = (int) ($total ?? 0);
$costeLibro = $config['coste_libro'] ?? '1';
?>

<div class="container-xxl py-4">
  <!-- Encabezado y buscador -->
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
      <h1 class="h2 fw-800 mb-1">Catálogo de Libros</h1>
      <p class="text-muted mb-0">Explora los títulos disponibles en nuestra biblioteca comunitaria.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <?php if (puede('csv.importar')): ?>
      <a href="/admin/csv?tipo=catalogo" class="btn btn-outline-success fw-bold">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Importar CSV
      </a>
      <?php endif; ?>
      <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fs-6">
        <i class="bi bi-coin me-1"></i>Coste uniforme: <strong>🪙 <?= e($costeLibro) ?></strong>
      </span>
    </div>
  </div>

  <!-- Aviso informativo: Catálogo cerrado para intercambios -->
  <div class="card border-0 bg-info-subtle text-info-emphasis rounded-4 p-3 mb-4 shadow-sm">
    <div class="d-flex align-items-center gap-3">
      <div class="p-2 bg-info text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; min-width: 40px;">
        <i class="bi bi-info-circle-fill fs-5"></i>
      </div>
      <div class="small">
        <strong>¿Quieres traer libros para intercambiar?</strong> Recuerda que <strong>únicamente se admiten los títulos incluidos en este catálogo oficial</strong> (no se admite cualquier libro). Consulta aquí si tu ejemplar está registrado antes de traerlo al mostrador.
      </div>
    </div>
  </div>

  <!-- Formulario de Búsqueda y Filtros -->
  <div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3 p-md-4">
      <form method="GET" action="/catalogo" class="row g-3 align-items-end">
        <input type="hidden" name="filtrado" value="1">
        <div class="col-12 col-md-4">
          <label for="campo-q" class="form-label small fw-bold text-muted">Buscar por título, autor o ISBN</label>
          <div class="input-group">
            <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" id="campo-q" name="q" class="form-control border-start-0" 
                   placeholder="Ej: Quijote, Orwell, 97884..." 
                   value="<?= e($filtros['q'] ?? '') ?>" autocomplete="off">
          </div>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
          <label for="filtro-genero" class="form-label small fw-bold text-muted">Grupo / Género literario</label>
          <select id="filtro-genero" name="genero" class="form-select">
            <option value="">Todos los grupos y géneros</option>
            <?php foreach ($generos as $gen): ?>
              <option value="<?= e($gen) ?>" <?= ($filtros['genero'] ?? '') === $gen ? 'selected' : '' ?>>
                <?= e($gen) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
          <label class="form-label small fw-bold text-muted d-none d-md-block">&nbsp;</label>
          <div class="p-2 px-3 rounded-3 border d-flex align-items-center gap-2 bg-body-tertiary shadow-xs"
               style="min-height: 42px; border-width: 2px !important; border-color: var(--bs-border-color) !important; cursor: pointer;"
               onclick="document.getElementById('check-disponibles').click();">
            <input class="form-check-input m-0" type="checkbox" id="check-disponibles" name="solo_disponibles" value="1" 
                   style="width: 1.45rem; height: 1.45rem; min-width: 1.45rem; cursor: pointer; border-width: 2px;"
                   <?= !empty($filtros['solo_disponibles']) ? 'checked' : '' ?> onclick="event.stopPropagation();">
            <label class="form-check-label small fw-bold mb-0 text-body" for="check-disponibles" style="cursor: pointer; user-select: none;">
              <i class="bi bi-check2-circle text-success me-1"></i>Solo disponibles
            </label>
          </div>
        </div>

        <div class="col-12 col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary w-100 fw-bold">
            <i class="bi bi-funnel me-1"></i>Filtrar
          </button>
          <?php if (!empty($filtros['q']) || !empty($filtros['genero']) || !empty($filtros['solo_disponibles'])): ?>
            <a href="/catalogo" class="btn btn-outline-secondary" title="Limpiar filtros">
              <i class="bi bi-x-lg"></i>
            </a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Resultados del catálogo -->
  <?php if (empty($libros)): ?>
    <div class="card border-0 shadow-sm rounded-4 text-center py-5">
      <div class="card-body">
        <i class="bi bi-journal-x text-muted display-3 mb-3 d-inline-block"></i>
        <h2 class="h4 fw-bold">No se encontraron libros</h2>
        <p class="text-muted mb-4">No hay títulos que coincidan con los criterios de búsqueda actuales.</p>
        <a href="/catalogo" class="btn btn-primary px-4 fw-bold">
          <i class="bi bi-arrow-counterclockwise me-1"></i>Ver todo el catálogo
        </a>
      </div>
    </div>
  <?php else: ?>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4 mb-4">
      <?php foreach ($libros as $libro): ?>
        <?php
          $disp = (int) ($libro['disponibles_count'] ?? 0);
          $portada = catalogo_resolver_url_portada($libro);
          $fallbackSvg = '/portada-svg?titulo=' . urlencode($libro['titulo']) . '&autor=' . urlencode($libro['autor']) . '&genero=' . urlencode((string)($libro['genero'] ?? ''));
        ?>
        <div class="col">
          <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden d-flex flex-column transition-hover">
            <!-- Portada del libro con relación de aspecto editorial -->
            <a href="/libro/<?= (int) $libro['id'] ?>" class="ratio ratio-4x3 bg-light overflow-hidden position-relative d-block text-decoration-none" title="Ver <?= e($libro['titulo']) ?>">
              <img src="<?= e($portada) ?>" alt="Portada de <?= e($libro['titulo']) ?>" 
                   width="300" height="225"
                   class="object-fit-cover w-100 h-100"
                   referrerpolicy="no-referrer"
                   data-fallback="<?= e($fallbackSvg) ?>"
                   onerror="this.onerror=null; this.src=this.dataset.fallback;"
                   loading="lazy">
              <div class="position-absolute top-0 end-0 m-2">
                <span class="badge bg-dark bg-opacity-75 backdrop-blur text-white px-2 py-1 rounded-pill small">
                  🪙 <?= e($costeLibro) ?>
                </span>
              </div>
            </a>

            <!-- Datos del libro -->
            <div class="card-body p-3 d-flex flex-column flex-grow-1">
              <?php if (!empty($libro['genero'])): ?>
                <span class="badge bg-light text-secondary border align-self-start mb-2 small fw-normal">
                  <?= e($libro['genero']) ?>
                </span>
              <?php endif; ?>

              <h3 class="h6 fw-bold mb-1 text-truncate" title="<?= e($libro['titulo']) ?>">
                <a href="/libro/<?= (int) $libro['id'] ?>" class="text-decoration-none text-reset">
                  <?= e($libro['titulo']) ?>
                </a>
              </h3>
              <p class="text-muted small mb-2 text-truncate"><?= e($libro['autor']) ?></p>

              <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                <?php if ($disp > 0): ?>
                  <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                    <i class="bi bi-check-circle me-1"></i><?= $disp ?> <?= $disp === 1 ? 'disp.' : 'disp.' ?>
                  </span>
                  <?php if ($usuario && puede('reserva.crear')): ?>
                    <form method="POST" action="/reservar" class="d-inline">
                      <?= csrf_campo() ?>
                      <input type="hidden" name="libro_id" value="<?= (int) $libro['id'] ?>">
                      <input type="hidden" name="retorno" value="/catalogo">
                      <button type="button" class="btn btn-sm btn-primary fw-bold rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalReservaRapida-<?= (int) $libro['id'] ?>">
                        <i class="bi bi-bookmark-plus me-1"></i>Reservar
                      </button>

                      <!-- Modal de confirmación rápida (§3.3) -->
                      <div class="modal fade" id="modalReservaRapida-<?= (int) $libro['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                          <div class="modal-content border-0 shadow rounded-4 text-start">
                            <div class="modal-header border-0 pb-0">
                              <h5 class="modal-title fw-bold fs-6">
                                <i class="bi bi-bookmark-plus text-primary me-2"></i>Confirmar reserva
                              </h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body py-3">
                              <p class="mb-2">¿Deseas reservar <strong><?= e($libro['titulo']) ?></strong>?</p>
                              <div class="alert alert-info py-2 px-3 small rounded-3 mb-0">
                                <i class="bi bi-info-circle me-1"></i>Se descontará temporalmente 1 🪙 hasta que recojas o anules la reserva.
                              </div>
                            </div>
                            <div class="modal-footer border-0 pt-0">
                              <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                              <button type="submit" class="btn btn-sm btn-primary fw-bold rounded-pill">
                                <i class="bi bi-check-lg me-1"></i>Confirmar reserva
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </form>
                  <?php else: ?>
                    <a href="<?= $usuario ? '/libro/' . (int) $libro['id'] : '/login' ?>" class="btn btn-sm btn-primary fw-bold rounded-pill px-3">
                      <i class="bi bi-bookmark-plus me-1"></i>Reservar
                    </a>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">
                    <i class="bi bi-clock me-1"></i>Sin copias
                  </span>
                  <a href="/libro/<?= (int) $libro['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                    Ficha
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Paginación server-side -->
    <?php if ($totalPaginas > 1): ?>
      <nav aria-label="Navegación de páginas" class="d-flex justify-content-center mt-4">
        <ul class="pagination pagination-rounded shadow-sm">
          <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="/catalogo?<?= http_build_query(array_merge($filtros, ['pagina' => $pagina - 1])) ?>">
              <i class="bi bi-chevron-left"></i> Anterior
            </a>
          </li>
          <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
            <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
              <a class="page-link" href="/catalogo?<?= http_build_query(array_merge($filtros, ['pagina' => $p])) ?>">
                <?= $p ?>
              </a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
            <a class="page-link" href="/catalogo?<?= http_build_query(array_merge($filtros, ['pagina' => $pagina + 1])) ?>">
              Siguiente <i class="bi bi-chevron-right"></i>
            </a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
