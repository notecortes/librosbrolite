<?php
/**
 * BookSwap · Vista de Ficha de Libro (Público).
 * Muestra los detalles bibliográficos de un libro, sus ejemplares y botón de reserva.
 */
declare(strict_types=1);

$libro = $libro ?? [];
$ejemplares = $ejemplares ?? [];
$usuario = $usuario ?? null;
$costeLibro = $config['coste_libro'] ?? '1';

$disponibles = array_filter($ejemplares, fn($e) => ($e['estado'] ?? '') === 'disponible');
$numDisponibles = count($disponibles);

$fallbackSvg = '/portada-svg?titulo=' . urlencode($libro['titulo']) . '&autor=' . urlencode($libro['autor']) . '&genero=' . urlencode((string)($libro['genero'] ?? ''));

// Comprobación de si la portada ya está guardada físicamente en el servidor local
$idLibro = (int) ($libro['id'] ?? 0);
$rutaCacheFisica = dirname(__DIR__, 2) . '/uploads/portadas/libro_' . $idLibro . '.jpg';
$tienePortadaLocal = ($idLibro > 0 && is_file($rutaCacheFisica) && filesize($rutaCacheFisica) > 300);

$portadaUrlBd = trim((string) ($libro['portada_url'] ?? ''));
if (!$tienePortadaLocal && $portadaUrlBd !== '' && (str_starts_with($portadaUrlBd, '/uploads/') || str_starts_with($portadaUrlBd, '/assets/'))) {
    $tienePortadaLocal = true;
    $portadaInicial = $portadaUrlBd;
} elseif ($tienePortadaLocal) {
    $portadaInicial = '/uploads/portadas/libro_' . $idLibro . '.jpg';
} else {
    // Si no está ya cacheada localmente en el servidor, usamos la portada SVG instantánea
    // para que la ficha y todos sus datos se muestren al usuario de inmediato sin demoras.
    $portadaInicial = $fallbackSvg;
}
$debeBuscarPortada = !$tienePortadaLocal;

$condicionesFormat = [
    'nuevo' => 'Nuevo (impecable)',
    'como_nuevo' => 'Como nuevo',
    'bueno' => 'Buen estado',
    'aceptable' => 'Aceptable (con señales de uso)',
    'deteriorado' => 'Deteriorado',
];
?>

<div class="container-xxl py-4">
  <!-- Navegación de migas de pan -->
  <nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Inicio</a></li>
      <li class="breadcrumb-item"><a href="/catalogo" class="text-decoration-none">Catálogo</a></li>
      <li class="breadcrumb-item active" aria-current="page"><?= e($libro['titulo']) ?></li>
    </ol>
  </nav>

  <?php if (puede('catalogo.editar') || puede('entrega.confirmar')): ?>
    <!-- Barra de acciones contextuales para personal de mostrador / administración (§2.1) -->
    <div id="barra-gestion-libro" class="card border border-primary shadow-sm rounded-4 bg-body-tertiary mb-4 p-3">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-primary px-2 py-1"><i class="bi bi-gear-fill me-1"></i>Gestión de Mostrador</span>
          <span class="text-body fw-bold small">Herramientas de ejemplares y catálogo</span>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
          <?php if (puede('catalogo.editar')): ?>
            <a href="/libros/entrada?libro_id=<?= (int) $libro['id'] ?>" class="btn btn-sm btn-primary rounded-pill px-3 shadow-xs">
              <i class="bi bi-box-arrow-in-down me-1"></i>Añadir copias
            </a>
            <a href="/admin/libros/editar?id=<?= (int) $libro['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
              <i class="bi bi-pencil me-1"></i>Editar libro
            </a>
            <?php if (($libro['estado'] ?? 'activo') === 'baja'): ?>
              <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalReactivarLibroFicha">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Reactivar libro
              </button>
            <?php else: ?>
              <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalBajaLibroFicha">
                <i class="bi bi-slash-circle me-1"></i>Dar de baja libro
              </button>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalEliminarLibroFicha">
              <i class="bi bi-trash3 me-1"></i>Eliminar libro
            </button>
          <?php endif; ?>

          <?php if (puede('entrega.confirmar')): ?>
            <?php 
              $primeraDisponible = null;
              foreach ($ejemplares as $ejCheck) {
                  if (($ejCheck['estado'] ?? '') === 'disponible') {
                      $primeraDisponible = $ejCheck;
                      break;
                  }
              }
            ?>
            <?php if ($primeraDisponible): ?>
              <button type="button" class="btn btn-sm btn-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalEntregaDirectaEj-<?= (int) $primeraDisponible['id'] ?>">
                <i class="bi bi-box-arrow-up-right me-1"></i>Entregar copia
              </button>
            <?php else: ?>
              <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" disabled title="Sin copias disponibles para entregar">
                <i class="bi bi-box-arrow-up-right me-1"></i>Entregar copia
              </button>
            <?php endif; ?>
          <?php endif; ?>

          <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-3 text-dark" data-bs-toggle="modal" data-bs-target="#modalMovimientosLibro">
            <i class="bi bi-clock-history me-1"></i>Ver movimientos del libro
          </button>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (($libro['estado'] ?? 'activo') === 'baja'): ?>
    <div class="alert alert-danger rounded-4 d-flex align-items-center mb-4 shadow-xs">
      <i class="bi bi-slash-circle-fill fs-3 me-3 text-danger"></i>
      <div>
        <strong class="d-block">Este libro ha sido dado de baja del catálogo</strong>
        <?php if (!empty($libro['motivo_baja'])): ?>
          <span class="small d-block"><strong>Motivo:</strong> <?= e($libro['motivo_baja']) ?></span>
        <?php endif; ?>
        <?php if (!empty($libro['fecha_baja'])): ?>
          <span class="small text-muted d-block">Fecha de baja: <?= e(date('d/m/Y H:i', strtotime($libro['fecha_baja']))) ?></span>
        <?php endif; ?>
        <span class="small text-muted">No admite nuevas reservas ni entregas ordinarias.</span>
      </div>
    </div>
  <?php endif; ?>

  <div class="row g-4 mb-5">
    <!-- Columna izquierda: Portada del libro con presentación editorial -->
    <div class="col-12 col-md-5 col-lg-4">
      <div class="card border-0 shadow rounded-4 overflow-hidden sticky-top" style="top: 90px;">
        <div class="position-relative bg-body-tertiary text-center p-3">
          <div class="ratio ratio-3x4 rounded-3 overflow-hidden shadow-sm mx-auto position-relative border" style="--bs-aspect-ratio: calc(4 / 3 * 100%); max-width: 280px;">
            <img id="portada-libro-img"
                 src="<?= e($portadaInicial) ?>" alt="Portada de <?= e($libro['titulo']) ?>"
                 width="280" height="373"
                 loading="lazy"
                 class="object-fit-cover w-100 h-100"
                 referrerpolicy="no-referrer"
                 data-fallback="<?= e($fallbackSvg) ?>"
                 onerror="this.onerror=null; this.src=this.dataset.fallback;"
                 style="transition: opacity 0.3s ease;">
            <!-- Efecto sutil de lomo de libro en el lateral izquierdo -->
            <div class="position-absolute top-0 start-0 bottom-0 pointer-events-none" 
                 style="width: 14px; background: linear-gradient(to right, rgba(0,0,0,0.25), rgba(0,0,0,0.05) 70%, transparent); opacity: 0.7;"></div>

            <?php if ($debeBuscarPortada): ?>
              <!-- Indicador discreto de búsqueda de portada en segundo plano -->
              <div id="portada-loader-badge" 
                   class="position-absolute bottom-0 start-50 translate-middle-x mb-3 badge bg-dark bg-opacity-75 text-white small px-3 py-1 rounded-pill d-flex align-items-center gap-2 shadow-sm"
                   style="transition: opacity 0.3s ease; z-index: 5;">
                <span class="spinner-border spinner-border-sm text-light" style="width: 0.75rem; height: 0.75rem;" role="status" aria-hidden="true"></span>
                <span style="font-size: 0.75rem;">Buscando portada...</span>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card-body p-3 bg-surface border-top">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fs-6">
              Coste: <strong>🪙 <?= e($costeLibro) ?> token</strong>
            </span>
            <?php if ($numDisponibles > 0): ?>
              <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill small">
                <i class="bi bi-check-circle me-1"></i><?= $numDisponibles ?> <?= $numDisponibles === 1 ? 'disponible' : 'disponibles' ?>
              </span>
            <?php else: ?>
              <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill small">
                <i class="bi bi-clock me-1"></i>Sin stock ahora
              </span>
            <?php endif; ?>
          </div>

          <div class="small text-muted mb-2">
            <i class="bi bi-info-circle me-1"></i>Intercambio directo en mostrador (1 libro o 1 token).
          </div>

          <?php if (puede('catalogo.editar')): ?>
            <a href="/admin/libros/editar?id=<?= (int) $libro['id'] ?>" class="btn btn-outline-secondary btn-sm w-100 mt-2">
              <i class="bi bi-pencil me-1"></i>Editar datos / portada
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Columna derecha: Detalles bibliográficos y ejemplares -->
    <div class="col-12 col-md-7 col-lg-8">
      <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
          <!-- Género -->
          <?php if (!empty($libro['genero'])): ?>
            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-1 rounded-pill mb-2">
              <?= e($libro['genero']) ?>
            </span>
          <?php endif; ?>

          <h1 class="h2 fw-800 text-body mb-2"><?= e($libro['titulo']) ?></h1>
          <p class="fs-5 text-muted mb-4"><?= e($libro['autor']) ?></p>

          <hr class="my-4">

          <!-- Metadatos estructurados -->
          <div class="row g-3 mb-4">
            <?php if (!empty($libro['isbn13'])): ?>
              <div class="col-6 col-sm-4">
                <span class="text-muted small d-block">ISBN-13</span>
                <strong class="font-monospace"><?= e($libro['isbn13']) ?></strong>
              </div>
            <?php endif; ?>

            <?php if (!empty($libro['editorial'])): ?>
              <div class="col-6 col-sm-4">
                <span class="text-muted small d-block">Editorial</span>
                <strong><?= e($libro['editorial']) ?></strong>
              </div>
            <?php endif; ?>

            <?php if (!empty($libro['anio'])): ?>
              <div class="col-6 col-sm-4">
                <span class="text-muted small d-block">Año de publicación</span>
                <strong><?= e((string)$libro['anio']) ?></strong>
              </div>
            <?php endif; ?>

            <div class="col-6 col-sm-4">
              <span class="text-muted small d-block">Idioma</span>
              <?php
                $mapIdiomasFicha = [
                    'es'  => 'Español (es)',
                    'val' => 'Valencià (val)',
                    'ca'  => 'Catalán (ca)',
                    'en'  => 'Inglés (en)',
                    'fr'  => 'Francés (fr)',
                    'de'  => 'Alemán (de)',
                    'it'  => 'Italiano (it)',
                    'gl'  => 'Gallego (gl)',
                    'eu'  => 'Euskera (eu)',
                ];
                $codIdiomaFicha = strtolower(trim((string)($libro['idioma'] ?? 'es')));
                $textoIdiomaFicha = $mapIdiomasFicha[$codIdiomaFicha] ?? strtoupper($codIdiomaFicha);
              ?>
              <strong><?= e($textoIdiomaFicha) ?></strong>
            </div>

            <div class="col-6 col-sm-4">
              <span class="text-muted small d-block">Disponibilidad</span>
              <?php if ($numDisponibles > 0): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                  <?= $numDisponibles ?> <?= $numDisponibles === 1 ? 'copia disponible' : 'copias disponibles' ?>
                </span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">
                  Agotado temporalmente
                </span>
              <?php endif; ?>
            </div>
          </div>

          <?php if (!empty($libro['observaciones'])): ?>
            <div class="mb-4">
              <h2 class="h6 fw-bold text-muted text-uppercase mb-2">Sinopsis u observaciones</h2>
              <p class="text-secondary leading-relaxed mb-0"><?= nl2br(e($libro['observaciones'])) ?></p>
            </div>
          <?php endif; ?>

          <!-- Botón de acción de reserva para usuarios -->
          <div class="p-3 bg-light rounded-3 mt-4 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
            <div>
              <div class="fw-bold">¿Quieres leer este libro?</div>
              <div class="small text-muted">
                <?php if ($numDisponibles > 0): ?>
                  Hay <?= $numDisponibles ?> ejemplar(es) listo(s) en nuestro punto físico.
                <?php else: ?>
                  Actualmente todas las copias se encuentran prestadas o reservadas.
                <?php endif; ?>
              </div>
            </div>

            <div>
              <?php if ($numDisponibles > 0): ?>
                <?php if ($usuario !== null): ?>
                  <form method="POST" action="/reservar" class="d-inline">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="libro_id" value="<?= (int) $libro['id'] ?>">
                    <button type="submit" class="btn btn-primary btn-lg fw-bold px-4 shadow-sm">
                      <i class="bi bi-bookmark-plus me-2"></i>Reservar ahora
                    </button>
                  </form>
                <?php else: ?>
                  <a href="/login?redireccion=/libro/<?= (int) $libro['id'] ?>" class="btn btn-primary btn-lg fw-bold px-4 shadow-sm">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Inicia sesión para reservar
                  </a>
                <?php endif; ?>
              <?php else: ?>
                <?php if ($usuario !== null): ?>
                  <?php $enWishlist = function_exists('wishlist_existe') && wishlist_existe(db(), (int) $usuario['id'], (int) $libro['id']); ?>
                  <?php if ($enWishlist): ?>
                    <form method="POST" action="/wishlist/eliminar" class="d-inline">
                      <?= csrf_campo() ?>
                      <input type="hidden" name="libro_id" value="<?= (int) $libro['id'] ?>">
                      <input type="hidden" name="redirect" value="/libro/<?= (int) $libro['id'] ?>">
                      <button type="submit" class="btn btn-outline-success btn-lg fw-bold px-4" title="Haz clic para cancelar el aviso de disponibilidad">
                        <i class="bi bi-bell-fill text-warning me-2"></i>Aviso activado (Cancelar aviso)
                      </button>
                    </form>
                  <?php else: ?>
                    <form method="POST" action="/wishlist/agregar" class="d-inline">
                      <?= csrf_campo() ?>
                      <input type="hidden" name="libro_id" value="<?= (int) $libro['id'] ?>">
                      <input type="hidden" name="redirect" value="/libro/<?= (int) $libro['id'] ?>">
                      <button type="submit" class="btn btn-outline-primary btn-lg fw-bold px-4">
                        <i class="bi bi-bell me-2"></i>Avisarme cuando esté disponible
                      </button>
                    </form>
                  <?php endif; ?>
                <?php else: ?>
                  <button class="btn btn-secondary btn-lg fw-bold px-4" disabled>
                    <i class="bi bi-x-circle me-2"></i>Sin ejemplares
                  </button>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>

          <?php if (puede('reserva.crear') && (puede('catalogo.editar') || puede('entrega.confirmar')) && $numDisponibles > 0): ?>
            <!-- Bloque para que el personal reserve a un lector específico -->
            <div class="card bg-primary-subtle border-primary-subtle border rounded-4 p-3 mt-3 shadow-xs">
              <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-primary text-white"><i class="bi bi-person-badge me-1"></i>Gestión de Mostrador / Administración</span>
                <strong class="text-primary-emphasis small">Reservar este libro para un lector</strong>
              </div>
              <form method="POST" action="/reservar" class="row g-2 align-items-center">
                <?= csrf_campo() ?>
                <input type="hidden" name="libro_id" value="<?= (int) $libro['id'] ?>">
                <input type="hidden" name="retorno" value="/libro/<?= (int) $libro['id'] ?>">
                <div class="col-12 col-md-7">
                  <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-person-vcard text-muted"></i></span>
                    <input type="text" name="lector" class="form-control" placeholder="Introduce el email o ID del lector..." required>
                  </div>
                </div>
                <div class="col-12 col-md-5 d-flex gap-2">
                  <select name="ejemplar_id" class="form-select form-select-sm" style="max-width: 140px;">
                    <option value="">Copia auto</option>
                    <?php foreach ($ejemplares as $e): ?>
                      <?php if ($e['estado'] === 'disponible'): ?>
                        <option value="<?= (int) $e['id'] ?>">Copia #<?= (int) $e['id'] ?></option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn-sm btn-primary fw-bold flex-grow-1 text-nowrap">
                    <i class="bi bi-bookmark-plus me-1"></i>Reservar a Lector
                  </button>
                </div>
              </form>
            </div>
          <?php endif; ?>

        </div>
      </div>

      <!-- Ejemplares físicos en estantería (§2.2) -->
      <?php
        $conteos = [
            'disponible' => 0,
            'reservado' => 0,
            'retirado' => 0,
            'baja' => 0,
        ];
        foreach ($ejemplares as $e) {
            $st = $e['estado'] ?? 'disponible';
            if (isset($conteos[$st])) {
                $conteos[$st]++;
            } else {
                $conteos[$st] = 1;
            }
        }
        $esPersonal = (puede('catalogo.editar') || puede('entrega.confirmar'));
      ?>
      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h2 class="h5 fw-bold mb-0">Ejemplares Físicos Registrados</h2>
            <div class="d-flex flex-wrap align-items-center gap-2">
              <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1">Disponibles (<?= $conteos['disponible'] ?>)</span>
              <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-1">Reservadas (<?= $conteos['reservado'] ?>)</span>
              <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill px-2 py-1">Retiradas (<?= $conteos['retirado'] ?>)</span>
              <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1">Baja (<?= $conteos['baja'] ?>)</span>
            </div>
          </div>
        </div>
        <div class="card-body p-4 pt-2">
          <?php if (empty($ejemplares)): ?>
            <p class="text-muted mb-0">No hay ejemplares registrados para este título en este momento.</p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th scope="col">Copia #</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Condición</th>
                    <th scope="col">Ubicación física</th>
                    <th scope="col" class="text-end">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($ejemplares as $ej): ?>
                    <tr>
                      <td class="fw-bold">
                        <?php if ($esPersonal): ?>
                          <a href="/ejemplar/<?= (int) $ej['id'] ?>" class="text-decoration-none fw-bold" title="Ver trazabilidad de la copia">
                            #<?= (int) $ej['id'] ?> <i class="bi bi-box-arrow-up-right small text-muted"></i>
                          </a>
                        <?php else: ?>
                          #<?= (int) $ej['id'] ?>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($ej['estado'] === 'disponible'): ?>
                          <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Disponible</span>
                        <?php elseif ($ej['estado'] === 'reservado'): ?>
                          <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill">Reservado</span>
                        <?php elseif ($ej['estado'] === 'retirado'): ?>
                          <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill">En préstamo</span>
                        <?php else: ?>
                          <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">Baja</span>
                        <?php endif; ?>
                      </td>
                      <td><?= e($condicionesFormat[$ej['condicion']] ?? $ej['condicion']) ?></td>
                      <td><code><?= e($ej['ubicacion'] ?? '—') ?></code></td>
                      <td class="text-end">
                        <?php if ($esPersonal): ?>
                          <!-- Acciones en línea para personal (§2.2) -->
                          <div class="d-flex align-items-center justify-content-end gap-1 flex-wrap">
                            <a href="/ejemplar/<?= (int) $ej['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2 py-0" style="font-size: 0.78rem;" title="Ver trazabilidad completa">
                              <i class="bi bi-clock-history me-1"></i>Ver copia
                            </a>

                            <?php if (puede('catalogo.editar')): ?>
                              <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-0" style="font-size: 0.78rem;" data-bs-toggle="modal" data-bs-target="#modalEditarEjemplar-<?= (int) $ej['id'] ?>" title="Editar condición y ubicación">
                                <i class="bi bi-pencil me-1"></i>Editar
                              </button>
                            <?php endif; ?>

                            <?php if ($ej['estado'] === 'disponible' && puede('entrega.confirmar')): ?>
                              <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-2 py-0 fw-semibold" style="font-size: 0.78rem;" data-bs-toggle="modal" data-bs-target="#modalEntregaDirectaEj-<?= (int) $ej['id'] ?>" title="Entregar directamente en mano">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Entregar
                              </button>
                            <?php endif; ?>

                            <?php if ($ej['estado'] === 'reservado' && !empty($ej['reserva_id'])): ?>
                              <form method="POST" action="/admin/reservas/cancelar" onsubmit="return confirm('¿Anular la reserva del ejemplar #<?= (int) $ej['id'] ?> (Lector: <?= e($ej['lector_nombre'] ?? '') ?>)?');" class="d-inline">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="transaccion_id" value="<?= (int) $ej['reserva_id'] ?>">
                                <input type="hidden" name="retorno" value="/libro/<?= (int) $libro['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-0" style="font-size: 0.78rem;" title="Anular reserva">
                                  <i class="bi bi-x-circle me-1"></i>Anular
                                </button>
                              </form>
                            <?php endif; ?>

                            <?php if ($ej['estado'] !== 'baja' && puede('catalogo.editar')): ?>
                              <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-0" style="font-size: 0.78rem;" data-bs-toggle="modal" data-bs-target="#modalBajaEjemplar-<?= (int) $ej['id'] ?>" title="Dar de baja este ejemplar">
                                <i class="bi bi-trash3 me-1"></i>Baja
                              </button>
                            <?php endif; ?>
                          </div>

                          <!-- Modal para reservar ejemplar concreto para lector -->
                          <?php if ($ej['estado'] === 'disponible'): ?>
                            <div class="modal fade text-start" id="modalReservaEj-<?= (int) $ej['id'] ?>" tabindex="-1" aria-hidden="true">
                              <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content rounded-4 border-0 shadow">
                                  <form method="POST" action="/reservar">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="libro_id" value="<?= (int) $libro['id'] ?>">
                                    <input type="hidden" name="ejemplar_id" value="<?= (int) $ej['id'] ?>">
                                    <input type="hidden" name="retorno" value="/libro/<?= (int) $libro['id'] ?>">
                                    <div class="modal-header border-0 pb-0">
                                      <h5 class="modal-title fw-bold">
                                        <i class="bi bi-bookmark-plus text-primary me-2"></i>Reservar Copia #<?= (int) $ej['id'] ?>
                                      </h5>
                                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <div class="modal-body py-3">
                                      <p class="text-muted small mb-3">
                                        Vas a reservar <strong><?= e($libro['titulo']) ?></strong> (Copia #<?= (int) $ej['id'] ?>, <?= e($ej['ubicacion'] ?? 'Mostrador') ?>) para un lector.
                                      </p>
                                      <div class="mb-3">
                                        <label class="form-label small fw-bold">Email o ID del lector:</label>
                                        <input type="text" name="lector" class="form-control font-monospace" placeholder="Introduce el email o ID del lector..." required>
                                      </div>
                                    </div>
                                    <div class="modal-footer border-0 pt-0">
                                      <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                                      <button type="submit" class="btn btn-primary rounded-pill fw-bold px-4">Confirmar Reserva</button>
                                    </div>
                                  </form>
                                </div>
                              </div>
                            </div>

                            <!-- Modal para entregar ejemplar concreto a lector directamente -->
                            <div class="modal fade text-start" id="modalEntregaDirectaEj-<?= (int) $ej['id'] ?>" tabindex="-1" aria-hidden="true">
                              <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content rounded-4 border-0 shadow">
                                  <form method="POST" action="/mostrador/entrega-directa">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="ejemplar_id" value="<?= (int) $ej['id'] ?>">
                                    <input type="hidden" name="retorno" value="/libro/<?= (int) $libro['id'] ?>">
                                    <div class="modal-header border-0 pb-0">
                                      <h5 class="modal-title fw-bold">
                                        <i class="bi bi-box-arrow-up-right text-success me-2"></i>Entrega Directa en Mano · Copia #<?= (int) $ej['id'] ?>
                                      </h5>
                                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <div class="modal-body py-3">
                                      <p class="text-muted small mb-3">
                                        Entregarás <strong><?= e($libro['titulo']) ?></strong> (Copia #<?= (int) $ej['id'] ?>) a un lector presencial, descontando el token correspondiente y retirando el ejemplar inmediatamente del catálogo.
                                      </p>
                                      <div class="mb-3">
                                        <label class="form-label small fw-bold">Email o ID del lector receptor:</label>
                                        <input type="text" name="lector" class="form-control font-monospace" placeholder="Introduce el email o ID del receptor..." required>
                                        <div class="form-text small">Se comprobará que el lector tenga saldo suficiente para retirar.</div>
                                      </div>
                                      <div class="mb-2">
                                        <label class="form-label small fw-bold">Forma de cobro:</label>
                                        <select name="metodo_pago" class="form-select form-select-sm">
                                          <option value="tokens" selected>Cobrar 1 Token (Saldo del lector)</option>
                                        </select>
                                      </div>
                                    </div>
                                    <div class="modal-footer border-0 pt-0">
                                      <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                                      <button type="submit" class="btn btn-success rounded-pill fw-bold px-4">
                                        <i class="bi bi-check2-circle me-1"></i>Confirmar Entrega y Baja
                                      </button>
                                    </div>
                                  </form>
                                </div>
                              </div>
                            </div>
                          <?php endif; ?>

                          <?php if (puede('catalogo.editar')): ?>
                            <!-- Modal Editar Copia -->
                            <div class="modal fade text-start" id="modalEditarEjemplar-<?= (int) $ej['id'] ?>" tabindex="-1" aria-hidden="true">
                              <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content rounded-4 border-0 shadow">
                                  <form method="POST" action="/ejemplar/<?= (int) $ej['id'] ?>/editar">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="retorno" value="/libro/<?= (int) $libro['id'] ?>">
                                    <div class="modal-header border-0 pb-0">
                                      <h5 class="modal-title fw-bold">
                                        <i class="bi bi-pencil-square text-primary me-2"></i>Editar Copia #<?= (int) $ej['id'] ?>
                                      </h5>
                                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <div class="modal-body py-3">
                                      <div class="mb-3">
                                        <label class="form-label small fw-bold">Condición / Conservación:</label>
                                        <select name="condicion" class="form-select">
                                          <?php foreach ($condicionesFormat as $k => $label): ?>
                                            <option value="<?= e($k) ?>" <?= ($ej['condicion'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                                          <?php endforeach; ?>
                                        </select>
                                      </div>
                                      <div class="mb-3">
                                        <label class="form-label small fw-bold">Ubicación física en estantería:</label>
                                        <input type="text" name="ubicacion" class="form-control font-monospace" value="<?= e($ej['ubicacion'] ?? '') ?>" placeholder="Ej: EST-01-A">
                                      </div>
                                    </div>
                                    <div class="modal-footer border-0 pt-0">
                                      <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                                      <button type="submit" class="btn btn-primary rounded-pill fw-bold px-4">Guardar cambios</button>
                                    </div>
                                  </form>
                                </div>
                              </div>
                            </div>

                            <!-- Modal Dar de Baja Copia -->
                            <?php if ($ej['estado'] !== 'baja'): ?>
                              <div class="modal fade text-start" id="modalBajaEjemplar-<?= (int) $ej['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                  <div class="modal-content rounded-4 border-0 shadow">
                                    <form method="POST" action="/ejemplar/<?= (int) $ej['id'] ?>/baja">
                                      <?= csrf_campo() ?>
                                      <input type="hidden" name="retorno" value="/libro/<?= (int) $libro['id'] ?>">
                                      <div class="modal-header border-0 pb-0">
                                        <h5 class="modal-title fw-bold text-danger">
                                          <i class="bi bi-trash3 text-danger me-2"></i>Dar de baja Copia #<?= (int) $ej['id'] ?>
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                      </div>
                                      <div class="modal-body py-3">
                                        <?php if ($ej['estado'] === 'reservado'): ?>
                                          <div class="alert alert-warning small mb-3">
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i>Esta copia está <strong>reservada</strong>. Debes anular la reserva activa antes de darla de baja.
                                          </div>
                                        <?php else: ?>
                                          <p class="text-muted small mb-3">
                                            Esta copia pasará al estado <code>baja</code> y dejará de estar disponible para reservas e intercambios.
                                          </p>
                                        <?php endif; ?>
                                        <div class="mb-3">
                                          <label class="form-label small fw-bold">Motivo de la baja (obligatorio):</label>
                                          <textarea name="motivo" class="form-control" rows="3" placeholder="Ej: Libro deteriorado, extraviado, páginas sueltas..." required></textarea>
                                        </div>
                                      </div>
                                      <div class="modal-footer border-0 pt-0">
                                        <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-danger rounded-pill fw-bold px-4" <?= $ej['estado'] === 'reservado' ? 'disabled' : '' ?>>
                                          Confirmar baja
                                        </button>
                                      </div>
                                    </form>
                                  </div>
                                </div>
                              </div>
                            <?php endif; ?>
                          <?php endif; ?>

                        <?php else: ?>
                          <!-- Acciones para lectores estándar -->
                          <?php if ($ej['estado'] === 'disponible'): ?>
                            <?php if ($usuario !== null): ?>
                              <form method="POST" action="/reservar" class="d-inline">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="libro_id" value="<?= (int) $libro['id'] ?>">
                                <input type="hidden" name="ejemplar_id" value="<?= (int) $ej['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
                                  <i class="bi bi-bookmark-plus me-1"></i>Reservar
                                </button>
                              </form>
                            <?php else: ?>
                              <a href="/login?redireccion=/libro/<?= (int) $libro['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Reservar
                              </a>
                            <?php endif; ?>
                          <?php else: ?>
                            <span class="text-muted small">—</span>
                          <?php endif; ?>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Enlace para volver -->
      <div class="mt-4">
        <a href="/catalogo" class="btn btn-outline-secondary fw-semibold">
          <i class="bi bi-arrow-left me-1"></i>Volver al Catálogo
        </a>
      </div>
    </div>
  </div>
</div>

<?php if (puede('catalogo.editar') || puede('entrega.confirmar')): ?>
  <!-- Modal Historial de Movimientos del Libro (§2.1) -->
  <div class="modal fade" id="modalMovimientosLibro" tabindex="-1" aria-labelledby="modalMovimientosLibroLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content rounded-4 border-0 shadow">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold" id="modalMovimientosLibroLabel">
            <i class="bi bi-clock-history text-primary me-2"></i>Últimos movimientos · <?= e($libro['titulo']) ?>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body py-3">
          <?php if (empty($historialLibro)): ?>
            <p class="text-muted text-center py-4 mb-0">No se han registrado movimientos recientes para las copias de este libro.</p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light small">
                  <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Copia #</th>
                    <th>Lector</th>
                    <th>Estado</th>
                  </tr>
                </thead>
                <tbody class="small">
                  <?php foreach ($historialLibro as $mov): ?>
                    <tr>
                      <td class="text-nowrap text-muted"><?= e(date('d/m/Y H:i', strtotime($mov['created_at']))) ?></td>
                      <td>
                        <span class="badge bg-secondary-subtle text-secondary border"><?= e(ucfirst((string)$mov['tipo'])) ?></span>
                      </td>
                      <td class="fw-bold">
                        <a href="/ejemplar/<?= (int) $mov['ejemplar_id'] ?>" class="text-decoration-none">#<?= (int) $mov['ejemplar_id'] ?></a>
                      </td>
                      <td>
                        <?= e($mov['lector_nombre'] ?? '—') ?>

                      </td>
                      <td>
                        <span class="badge bg-light text-dark border"><?= e($mov['estado']) ?></span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($debeBuscarPortada): ?>
<script>
/**
 * Carga asíncrona de la portada de un libro en segundo plano (v4.2).
 * Permite que la ficha del libro se dibuje de forma instantánea con el marcador SVG local,
 * y posteriormente busca, descarga y proyecta la portada real en alta resolución.
 */
document.addEventListener('DOMContentLoaded', function () {
  const libroId = <?= (int) $libro['id'] ?>;
  const imgPortada = document.getElementById('portada-libro-img');
  const badge = document.getElementById('portada-loader-badge');

  if (!imgPortada || !libroId) {
    return;
  }

  // Realizar la consulta a la API de portada de forma asíncrona
  fetch('/api/libro-portada?id=' + encodeURIComponent(libroId))
    .then(function (respuesta) {
      if (!respuesta.ok) {
        throw new Error('Error HTTP ' + respuesta.status);
      }
      return respuesta.json();
    })
    .then(function (datos) {
      if (datos && datos.ok && datos.portada_url) {
        // Pre-cargar la imagen en memoria antes de sustituir el src para evitar parpadeos
        const precarga = new Image();
        precarga.onload = function () {
          imgPortada.style.opacity = '0';
          setTimeout(function () {
            imgPortada.src = datos.portada_url;
            imgPortada.style.opacity = '1';
            if (badge) {
              badge.style.opacity = '0';
              setTimeout(function () {
                badge.remove();
              }, 300);
            }
          }, 200);
        };
        precarga.onerror = function () {
          // Si la imagen descargada fallase al renderizar, ocultamos el badge suavemente
          if (badge) {
            badge.style.opacity = '0';
            setTimeout(function () {
              badge.remove();
            }, 300);
          }
        };
        precarga.src = datos.portada_url;
      } else {
        // No se encontró portada en APIs externas; se conserva la portada SVG de diseño
        if (badge) {
          badge.style.opacity = '0';
          setTimeout(function () {
            badge.remove();
          }, 300);
        }
      }
    })
    .catch(function () {
      // Manejo de error de red o timeout
      if (badge) {
        badge.style.opacity = '0';
        setTimeout(function () {
          badge.remove();
        }, 300);
      }
    });
});
</script>
<?php endif; ?>

<?php if (puede('catalogo.editar')): ?>
<!-- Modal: Confirmar Eliminación de Libro (Ficha) -->
<div class="modal fade" id="modalEliminarLibroFicha" tabindex="-1" aria-labelledby="modalEliminarLibroFichaLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <form method="POST" action="/admin/libros/eliminar">
        <?= csrf_campo() ?>
        <input type="hidden" name="libro_id" value="<?= (int) $libro['id'] ?>">
        <input type="hidden" name="retorno" value="/catalogo">
        
        <div class="modal-header border-0 pb-0">
          <h2 class="h5 fw-bold modal-title text-danger" id="modalEliminarLibroFichaLabel">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>Eliminar Libro del Catálogo
          </h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p class="mb-3">
            ¿Estás seguro de que deseas eliminar permanentemente el libro <strong class="text-body"><?= e($libro['titulo']) ?></strong> del catálogo?
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

<!-- Modal: Confirmar Baja de Libro (Ficha) -->
<div class="modal fade" id="modalBajaLibroFicha" tabindex="-1" aria-labelledby="modalBajaLibroFichaLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <form method="POST" action="/admin/libros/baja">
        <?= csrf_campo() ?>
        <input type="hidden" name="libro_id" value="<?= (int) $libro['id'] ?>">
        <input type="hidden" name="retorno" value="/libro/<?= (int) $libro['id'] ?>">
        
        <div class="modal-header border-0 pb-0">
          <h2 class="h5 fw-bold modal-title text-warning-emphasis" id="modalBajaLibroFichaLabel">
            <i class="bi bi-slash-circle me-2 text-warning"></i>Dar de Baja Libro del Catálogo
          </h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p class="mb-3">
            ¿Confirmas la baja del libro <strong class="text-body"><?= e($libro['titulo']) ?></strong> del catálogo?
          </p>
          <div class="alert alert-warning small border-0 rounded-3 mb-3">
            <ul class="mb-0 ps-3">
              <li>El libro pasará al estado <code>baja</code> y dejará de ofrecerse en búsquedas públicas.</li>
              <li>Todas sus copias físicas asociadas se marcarán en estado <code>baja</code>.</li>
              <li>Si existen <strong>reservas activas pendientes</strong>, se cancelarán de inmediato y se devolverán los tokens bloqueados a los lectores con notificación.</li>
            </ul>
          </div>
          <div class="mb-2">
            <label for="motivo-baja-libro-ficha" class="form-label small fw-bold">Motivo de la baja (obligatorio):</label>
            <textarea id="motivo-baja-libro-ficha" name="motivo" class="form-control" rows="3" required
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

<!-- Modal: Reactivar Libro (Ficha) -->
<div class="modal fade" id="modalReactivarLibroFicha" tabindex="-1" aria-labelledby="modalReactivarLibroFichaLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <form method="POST" action="/admin/libros/reactivar">
        <?= csrf_campo() ?>
        <input type="hidden" name="libro_id" value="<?= (int) $libro['id'] ?>">
        <input type="hidden" name="retorno" value="/libro/<?= (int) $libro['id'] ?>">
        
        <div class="modal-header border-0 pb-0">
          <h2 class="h5 fw-bold modal-title text-success" id="modalReactivarLibroFichaLabel">
            <i class="bi bi-arrow-counterclockwise me-2"></i>Reactivar Libro en el Catálogo
          </h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">
            El libro <strong class="text-body"><?= e($libro['titulo']) ?></strong> volverá a estar en estado <code>activo</code> en el catálogo. Podrás incorporar nuevos ejemplares o reactivar los existentes cuando lo desees.
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
<?php endif; ?>
