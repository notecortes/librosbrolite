<?php
/**
 * BookSwap · Entrada Unificada de Copias al Catálogo (v4.6).
 * Unifica el alta de stock y el registro de depósitos en un solo flujo (§1.1).
 */
declare(strict_types=1);

$libro = $libro ?? null;
$stock = $stock ?? ['disponible' => 0, 'reservado' => 0, 'retirado' => 0, 'baja' => 0];
$criterio = $criterio ?? '';
$noEncontrado = $noEncontrado ?? false;
$usuarios = $usuarios ?? [];
$bonoDeposito = (int) ($config['bono_deposito'] ?? 1);
$librosRecientes = $librosRecientes ?? [];
?>

<div class="container-xxl py-4">
  <div class="d-none"><?= csrf_campo() ?></div>
  <!-- Migas de pan -->
  <nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/dashboard" class="text-decoration-none">Inicio</a></li>
      <li class="breadcrumb-item"><a href="/catalogo" class="text-decoration-none">Catálogo</a></li>
      <li class="breadcrumb-item active" aria-current="page">Entrada de Copias</li>
    </ol>
  </nav>

  <!-- Encabezado -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 border-bottom pb-3">
    <div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-success px-3 py-2 fs-6 rounded-pill">
          <i class="bi bi-box-arrow-in-down me-1"></i> Entrada de Copias
        </span>
        <h1 class="h3 fw-bold mb-0">Recepción e Incorporación de Libros</h1>
      </div>
      <p class="text-muted small mb-0 mt-1">
        Flujo unificado para incorporación de stock de biblioteca y registro de depósitos de usuarios en ≤3 interacciones.
      </p>
    </div>
    <div class="d-flex gap-2">
      <?php if (puede('csv.importar')): ?>
      <a href="/admin/csv?tipo=catalogo" class="btn btn-outline-success fw-semibold">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Importar CSV
      </a>
      <?php endif; ?>
      <a href="/mostrador" class="btn btn-outline-secondary fw-semibold">
        <i class="bi bi-shop me-1"></i>Mostrador
      </a>
      <a href="/admin/libros" class="btn btn-outline-primary fw-semibold">
        <i class="bi bi-bookshelf me-1"></i>Catálogo Admin
      </a>
    </div>
  </div>

  <!-- Paso 1: Buscador / Escáner de ISBN o Título -->
  <div class="card border border-success shadow-sm rounded-4 p-3 p-md-4 mb-4 bg-surface">
    <form method="GET" action="/libros/entrada" class="row g-3 align-items-center" id="form-buscar-entrada">
      <div class="col-12 col-md-9">
        <label for="input-isbn-entrada" class="form-label fw-bold d-flex justify-content-between align-items-center mb-2">
          <span><i class="bi bi-upc-scan text-success me-2"></i>Escanear código de barras (ISBN) o buscar libro:</span>
          <span class="small text-muted fw-normal d-none d-sm-inline">Pistola láser o teclado + Enter</span>
        </label>
        <div class="input-group input-group-lg shadow-xs rounded-3 overflow-hidden border">
          <span class="input-group-text bg-white border-0 text-success ps-3">
            <i class="bi bi-barcode fs-4"></i>
          </span>
          <input type="text"
                 name="q"
                 id="input-isbn-entrada"
                 class="form-control border-0 font-monospace fs-5 fw-bold"
                 placeholder="Escanea el ISBN del libro o escribe título/autor..."
                 value="<?= e($criterio) ?>"
                 autocomplete="off"
                 <?= empty($libro) ? 'autofocus' : '' ?>>
          <button class="btn btn-success fw-bold px-4" type="submit" id="btn-buscar-entrada">
            <i class="bi bi-search me-1"></i>Buscar
          </button>
        </div>
      </div>
      <div class="col-12 col-md-3">
        <div class="p-2 px-3 bg-light rounded-3 border small text-muted">
          <div class="fw-bold text-dark mb-1"><i class="bi bi-info-circle text-primary me-1"></i>Regla de oro:</div>
          <div>Un solo flujo físico: con lector asignado = depósito (+tokens); sin lector = stock de centro.</div>
        </div>
      </div>
    </form>
  </div>

  <?php if ($noEncontrado): ?>
    <!-- CASO B: El libro NO existe en catálogo (§1.1 Caso B) -->
    <div class="card border border-warning shadow-sm rounded-4 p-4 mb-4 bg-surface text-center py-5">
      <div class="mb-3">
        <span class="badge bg-warning-subtle text-warning-emphasis p-3 rounded-circle fs-3">
          <i class="bi bi-journal-plus"></i>
        </span>
      </div>
      <h2 class="h4 fw-bold mb-2">El libro no está registrado en el catálogo</h2>
      <p class="text-muted mb-4 max-w-lg mx-auto" style="max-width: 520px;">
        No existe ningún libro con el ISBN o título «<strong><?= e($criterio) ?></strong>». Puedes darlo de alta ahora mismo mediante búsqueda asistida y se cargará automáticamente aquí para registrar sus copias.
      </p>
      <div>
        <a href="/admin/libros/nuevo?isbn=<?= urlencode($criterio) ?>" class="btn btn-primary btn-lg fw-bold px-4 shadow-sm" id="btn-alta-asistida">
          <i class="bi bi-plus-circle me-2"></i>Dar de alta este libro
        </a>
        <a href="/libros/entrada" class="btn btn-outline-secondary btn-lg ms-2">
          Buscar otro libro
        </a>
      </div>
    </div>
  <?php elseif ($libro): ?>
    <!-- CASO A: El libro EXISTE en catálogo (§1.1 Caso A) -->
    <?php
      $portada = catalogo_resolver_url_portada($libro);
      $fallbackSvg = '/portada-svg?titulo=' . urlencode($libro['titulo']) . '&autor=' . urlencode($libro['autor']);
    ?>
    <div class="row g-4 mb-5">
      <!-- Columna Izquierda: Información del libro y stock actual -->
      <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 bg-surface">
          <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 fw-semibold">
              Libro localizado
            </span>
          </div>
          <div class="card-body p-4 pt-2">
            <div class="d-flex gap-3 align-items-start mb-4">
              <div class="ratio ratio-3x4 rounded-3 overflow-hidden shadow-xs border flex-shrink-0" style="width: 90px;">
                <img src="<?= e($portada) ?>" alt="Portada" class="object-fit-cover w-100 h-100"
                     data-fallback="<?= e($fallbackSvg) ?>"
                     onerror="this.onerror=null; this.src=this.dataset.fallback;">
              </div>
              <div>
                <h2 class="h5 fw-bold mb-1 text-body"><?= e($libro['titulo']) ?></h2>
                <div class="text-muted small mb-2"><?= e($libro['autor']) ?></div>
                <?php if (!empty($libro['isbn13'])): ?>
                  <div class="small"><span class="text-muted">ISBN-13:</span> <code class="fw-bold"><?= e($libro['isbn13']) ?></code></div>
                <?php endif; ?>
                <?php if (!empty($libro['editorial'])): ?>
                  <div class="small text-muted">Editorial: <?= e($libro['editorial']) ?></div>
                <?php endif; ?>
              </div>
            </div>

            <hr class="my-3">

            <div class="mb-3">
              <label class="form-label small fw-bold text-muted text-uppercase">Stock actual por estado:</label>
              <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fs-6">
                  <i class="bi bi-check-circle me-1"></i>Disponibles: <strong><?= (int) ($stock['disponible'] ?? 0) ?></strong>
                </span>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 rounded-pill fs-6">
                  <i class="bi bi-bookmark me-1"></i>Reservadas: <strong><?= (int) ($stock['reservado'] ?? 0) ?></strong>
                </span>
                <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-2 rounded-pill fs-6">
                  <i class="bi bi-send me-1"></i>En préstamo: <strong><?= (int) ($stock['retirado'] ?? 0) ?></strong>
                </span>
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 rounded-pill fs-6">
                  <i class="bi bi-slash-circle me-1"></i>Baja: <strong><?= (int) ($stock['baja'] ?? 0) ?></strong>
                </span>
              </div>
            </div>

            <div class="pt-2">
              <a href="/libro/<?= (int) $libro['id'] ?>" class="btn btn-outline-secondary btn-sm rounded-pill w-100" target="_blank">
                <i class="bi bi-eye me-1"></i>Ver ficha completa del libro
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Columna Derecha: Formulario de Entrada de Copias -->
      <div class="col-12 col-lg-7">
        <div class="card border border-success shadow-sm rounded-4 p-4 bg-surface">
          <h2 class="h5 fw-bold mb-3">
            <i class="bi bi-plus-circle text-success me-2"></i>Formulario de Entrada de Copias
          </h2>

          <form method="POST" action="/libros/entrada" id="form-confirmar-entrada">
            <?= csrf_campo() ?>
            <input type="hidden" name="libro_id" value="<?= (int) $libro['id'] ?>">

            <div class="row g-3">
              <!-- Número de copias -->
              <div class="col-12 col-sm-6">
                <label for="input-copias" class="form-label fw-bold">Número de copias a incorporar:</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-stack"></i></span>
                  <input type="number"
                         name="copias"
                         id="input-copias"
                         class="form-control font-monospace fs-5 fw-bold"
                         value="1"
                         min="1"
                         max="20"
                         required
                         autofocus>
                </div>
                <div class="form-text small">Rango admitido: de 1 a 20 ejemplares en una sola operación.</div>
              </div>

              <!-- Condición física -->
              <div class="col-12 col-sm-6">
                <label for="select-condicion" class="form-label fw-bold">Condición física:</label>
                <select name="condicion" id="select-condicion" class="form-select">
                  <option value="nuevo">Nuevo (impecable)</option>
                  <option value="como_nuevo">Como nuevo</option>
                  <option value="bueno" selected>Buen estado (predeterminado)</option>
                  <option value="aceptable">Aceptable (con señales de uso)</option>
                  <option value="deteriorado">Deteriorado</option>
                </select>
                <div class="form-text small">Estado general del lote de copias.</div>
              </div>

              <!-- Ubicación física -->
              <div class="col-12 col-sm-6">
                <label for="input-ubicacion" class="form-label fw-bold">Ubicación en biblioteca (opcional):</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                  <input type="text"
                         name="ubicacion"
                         id="input-ubicacion"
                         class="form-control"
                         placeholder="Ej: MOSTRADOR, Estante 2-A..."
                         value="MOSTRADOR">
                </div>
              </div>

              <!-- Traído por (Depositante) Opcional -->
              <div class="col-12 col-sm-6">
                <label for="select-depositante" class="form-label fw-bold d-flex justify-content-between align-items-center">
                  <span>Traído por (Lector / Depositante):</span>
                  <span class="badge bg-secondary-subtle text-secondary fw-normal">Opcional</span>
                </label>
                <div class="input-group">
                  <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                  <input type="text"
                         name="depositante"
                         id="select-depositante"
                         class="form-control"
                         list="lista-usuarios-depositantes"
                         placeholder="Email o ID del lector (en blanco para stock)...">
                </div>
                <datalist id="lista-usuarios-depositantes">
                  <?php foreach ($usuarios as $u): ?>
                    <option value="<?= (int)$u['id'] ?>">
                      <?= e($u['nombre']) ?> (<?= e($u['email']) ?>)
                    </option>
                  <?php endforeach; ?>
                </datalist>
                <div class="form-text small">
                  Si se indica un lector, se le acreditarán <strong>+<?= $bonoDeposito ?> token(s) por copia</strong>.
                </div>
              </div>
            </div>

            <!-- Resumen y botón de confirmación -->
            <div class="card bg-body-tertiary border-0 rounded-3 p-3 mt-4">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-muted">Operación atómica:</span>
                <span class="badge bg-success-subtle text-success border border-success-subtle">
                  Estado resultante: Disponible
                </span>
              </div>
              <div class="small text-secondary mb-0">
                Al confirmar, se crearán las copias en el almacén físico y se registrará la transacción en el libro mayor y en la auditoría inmutable del centro.
              </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
              <a href="/libros/entrada" class="btn btn-outline-secondary rounded-pill px-4">
                Cancelar
              </a>
              <button type="submit" class="btn btn-success btn-lg fw-bold rounded-pill px-5 shadow-sm" id="btn-submit-entrada">
                <i class="bi bi-box-arrow-in-down me-2"></i>Confirmar Entrada de Copias
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php else: ?>
    <!-- Sin libro seleccionado: Guía rápida y accesos rápidos -->
    <div class="row g-4 mb-4">
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-surface">
          <div class="fs-2 mb-2">⚡</div>
          <h2 class="h5 fw-bold mb-2">Escaneo en 1 Clic</h2>
          <p class="text-muted small mb-0">
            Escanea el código de barras 1D de la cubierta del libro con la pistola láser. El campo superior lo detectará y cargará automáticamente la ficha para confirmar las copias en 2 interacciones.
          </p>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-surface">
          <div class="fs-2 mb-2">🪙</div>
          <h2 class="h5 fw-bold mb-2">Depósito Ciudadano</h2>
          <p class="text-muted small mb-0">
            Si el libro ha sido traído por un alumno o lector, añade su email o nombre en el campo "Traído por". El sistema acreditará automáticamente los tokens correspondientes en su cuenta.
          </p>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-surface">
          <div class="fs-2 mb-2">📦</div>
          <h2 class="h5 fw-bold mb-2">Alta Directa de Fondo</h2>
          <p class="text-muted small mb-0">
            Para incorporar donaciones anónimas o libros adquiridos por la biblioteca, deja el campo de lector vacío. Se creará el stock físico con trazabilidad de entrada sin emitir tokens.
          </p>
        </div>
      </div>
    </div>

    <!-- Títulos del catálogo sugeridos para entrada rápida -->
    <?php if (!empty($librosRecientes)): ?>
      <div class="card border-0 shadow-sm rounded-4 p-4 bg-surface">
        <h2 class="h6 fw-bold text-muted text-uppercase mb-3">Títulos recientes en catálogo (Selección rápida):</h2>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th scope="col">Título</th>
                <th scope="col">Autor</th>
                <th scope="col">ISBN</th>
                <th scope="col" class="text-end">Acción</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($librosRecientes as $lr): ?>
                <tr>
                  <td class="fw-bold"><?= e($lr['titulo']) ?></td>
                  <td class="text-muted small"><?= e($lr['autor']) ?></td>
                  <td><code><?= e($lr['isbn13'] ?: '—') ?></code></td>
                  <td class="text-end">
                    <a href="/libros/entrada?libro_id=<?= (int) $lr['id'] ?>" class="btn btn-sm btn-outline-success rounded-pill fw-semibold">
                      <i class="bi bi-plus-lg me-1"></i>Añadir copias
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
