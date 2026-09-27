<?php
/**
 * BookSwap · historial.php — Historial de Libros y Tokens (v4.2).
 *
 * Muestra el balance y la justificación completa de los tokens y ejemplares de un usuario,
 * incluyendo resumen superior, filtros por tipo/fecha, exportación CSV y paginación server-side.
 */
declare(strict_types=1);

$resumen = $resumen ?? [
    'libros_depositados' => 0,
    'libros_retirados'   => 0,
    'saldo_actual'       => 0,
    'tokens_ganados'     => 0,
    'tokens_gastados'    => 0,
];

$movimientos = $movimientos ?? [];
$total = (int) ($total ?? 0);
$pagina = (int) ($pagina ?? 1);
$totalPaginas = (int) ($totalPaginas ?? 1);
$filtros = $filtros ?? ['tipo' => '', 'desde' => '', 'hasta' => ''];
$esAdmin = !empty($esVistaAdmin);
$usuarioObjetivo = $usuarioObjetivo ?? $usuario;

$baseUrlHistorial = $esAdmin
    ? '/admin/usuarios/' . (int) $usuarioObjetivo['id'] . '/historial'
    : '/mi-historial';

$urlExportCsv = $baseUrlHistorial . '?' . http_build_query(array_merge($filtros, ['exportar' => 'csv']));
?>

<div class="container-xxl py-4">
  <!-- Cabecera -->
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <?php if ($esAdmin): ?>
          <a href="/admin/usuarios" class="btn btn-outline-secondary btn-sm rounded-pill">
            <i class="bi bi-arrow-left me-1"></i>Volver a Usuarios
          </a>
          <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill fw-bold">
            <i class="bi bi-shield-check me-1"></i>Vista Administrador
          </span>
        <?php else: ?>
          <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill fw-bold">
            <i class="bi bi-clock-history me-1"></i>Libro Mayor
          </span>
        <?php endif; ?>
      </div>

      <h1 class="h2 fw-800 mb-1">
        <?= $esAdmin ? 'Historial de ' . e($usuarioObjetivo['nombre']) : 'Mi Historial de Libros y Tokens' ?>
      </h1>
      <p class="text-muted mb-0">
        <?php if ($esAdmin): ?>
          <?= e($usuarioObjetivo['email']) ?> · Justificación completa de movimientos.
        <?php else: ?>
          Registro detallado y transparente de todos tus depósitos, lecturas, retiros y saldo de tokens.
        <?php endif; ?>
      </p>
    </div>

    <div class="d-flex gap-2">
      <a href="<?= e($urlExportCsv) ?>" class="btn btn-outline-success fw-bold px-3 shadow-xs">
        <i class="bi bi-download me-2"></i>Exportar CSV
      </a>
      <?php if (!$esAdmin): ?>
        <a href="/dashboard" class="btn btn-outline-secondary fw-semibold">
          <i class="bi bi-person me-1"></i>Mi Panel
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Resumen superior con tarjetas de métricas -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
      <div class="card p-3 p-md-4 border border-primary shadow-sm rounded-4 bg-surface h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-bold text-uppercase">Libros Depositados</span>
          <span class="badge bg-primary-subtle text-primary p-2 rounded-3"><i class="bi bi-box-arrow-in-down fs-6"></i></span>
        </div>
        <div class="fs-2 fw-800 text-body" id="historial-depositados"><?= (int) $resumen['libros_depositados'] ?></div>
        <div class="small text-muted">Ejemplares aportados a la red</div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="card p-3 p-md-4 border border-info shadow-sm rounded-4 bg-surface h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-bold text-uppercase">Libros Retirados</span>
          <span class="badge bg-info-subtle text-info p-2 rounded-3"><i class="bi bi-book-half fs-6"></i></span>
        </div>
        <div class="fs-2 fw-800 text-body" id="historial-retirados"><?= (int) $resumen['libros_retirados'] ?></div>
        <div class="small text-muted">Lecturas retiradas del centro</div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="card p-3 p-md-4 border border-warning shadow-sm rounded-4 bg-surface h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-bold text-uppercase">Saldo Actual</span>
          <span class="badge bg-warning-subtle text-warning-emphasis p-2 rounded-3"><i class="bi bi-coin fs-6"></i></span>
        </div>
        <div class="d-flex align-items-baseline gap-1">
          <span class="fs-2 fw-800 text-warning-emphasis" id="historial-saldo"><?= (int) $resumen['saldo_actual'] ?></span>
          <span class="fw-bold text-warning-emphasis">🪙</span>
        </div>
        <div class="small text-muted">Tokens listos para intercambiar</div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="card p-3 p-md-4 border border-success shadow-sm rounded-4 bg-surface h-100">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="text-muted small fw-bold text-uppercase">Flujo Total Tokens</span>
          <span class="badge bg-success-subtle text-success p-2 rounded-3"><i class="bi bi-arrow-left-right fs-6"></i></span>
        </div>
        <div class="d-flex align-items-baseline gap-2">
          <span class="text-success fw-bold">+<?= (int) $resumen['tokens_ganados'] ?></span>
          <span class="text-muted">/</span>
          <span class="text-danger fw-bold">-<?= (int) $resumen['tokens_gastados'] ?></span>
        </div>
        <div class="small text-muted">Ganados / Gastados históricos</div>
      </div>
    </div>
  </div>

  <!-- Formulario de filtros -->
  <div class="card border-0 shadow-sm rounded-4 bg-surface p-3 mb-4">
    <form method="GET" action="<?= e($baseUrlHistorial) ?>" class="row g-2 align-items-end">
      <div class="col-12 col-md-3">
        <label for="filtro-tipo" class="form-label small fw-bold text-muted mb-1">Tipo de movimiento</label>
        <select class="form-select form-select-sm" id="filtro-tipo" name="tipo">
          <option value="">-- Todos los tipos --</option>
          <option value="deposito" <?= ($filtros['tipo'] ?? '') === 'deposito' ? 'selected' : '' ?>>Depósito (aporte de libro)</option>
          <option value="retiro" <?= ($filtros['tipo'] ?? '') === 'retiro' ? 'selected' : '' ?>>Retiro (recogida de libro)</option>
          <option value="bono" <?= ($filtros['tipo'] ?? '') === 'bono' ? 'selected' : '' ?>>Bono promocional</option>
          <option value="bono_bienvenida" <?= ($filtros['tipo'] ?? '') === 'bono_bienvenida' ? 'selected' : '' ?>>Bono de bienvenida</option>
          <option value="ajuste" <?= ($filtros['tipo'] ?? '') === 'ajuste' ? 'selected' : '' ?>>Ajuste administrativo</option>
        </select>
      </div>

      <div class="col-6 col-md-3">
        <label for="filtro-desde" class="form-label small fw-bold text-muted mb-1">Desde fecha</label>
        <input type="date" class="form-control form-control-sm" id="filtro-desde" name="desde" value="<?= e($filtros['desde'] ?? '') ?>">
      </div>

      <div class="col-6 col-md-3">
        <label for="filtro-hasta" class="form-label small fw-bold text-muted mb-1">Hasta fecha</label>
        <input type="date" class="form-control form-control-sm" id="filtro-hasta" name="hasta" value="<?= e($filtros['hasta'] ?? '') ?>">
      </div>

      <div class="col-12 col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm fw-bold flex-grow-1">
          <i class="bi bi-funnel me-1"></i>Filtrar
        </button>
        <?php if (!empty($filtros['tipo']) || !empty($filtros['desde']) || !empty($filtros['hasta'])): ?>
          <a href="<?= e($baseUrlHistorial) ?>" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
            <i class="bi bi-x-circle"></i>
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Tabla del historial -->
  <div class="card border-0 shadow-sm rounded-4 bg-surface overflow-hidden mb-4">
    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
      <h2 class="h5 fw-bold mb-0">
        <i class="bi bi-journal-text me-2 text-primary"></i>Movimientos del Libro Mayor
      </h2>
      <span class="badge bg-secondary-subtle text-secondary px-3 py-2 rounded-pill fw-semibold">
        <?= $total ?> movimiento<?= $total !== 1 ? 's' : '' ?> encontrado<?= $total !== 1 ? 's' : '' ?>
      </span>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 150px;">Fecha y Hora</th>
              <th>Tipo</th>
              <th>Libro Involucrado</th>
              <th>Método de Pago</th>
              <th class="text-end">Tokens</th>
              <th class="text-end">Saldo Resultante</th>
              <th>Concepto / Operación</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($movimientos)): ?>
              <tr>
                <td colspan="7" class="text-center py-5 text-muted">
                  <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                  No se han registrado movimientos que coincidan con los criterios seleccionados.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($movimientos as $m): ?>
                <?php
                $esPositivo = (int) $m['cantidad'] > 0;
                $colorBadgeTipo = match ($m['tipo']) {
                    'deposito' => 'bg-success-subtle text-success border-success-subtle',
                    'retiro'   => 'bg-info-subtle text-info-emphasis border-info-subtle',
                    'bono', 'bono_bienvenida' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                    default    => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                };
                ?>
                <tr>
                  <!-- Fecha -->
                  <td class="font-monospace small text-muted">
                    <?= date('d/m/Y H:i', strtotime($m['fecha'])) ?>
                  </td>

                  <!-- Tipo -->
                  <td>
                    <span class="badge <?= $colorBadgeTipo ?> border px-2 py-1 rounded-pill fw-bold text-uppercase small">
                      <?= e($m['tipo']) ?>
                    </span>
                  </td>

                  <!-- Libro (Título + Autor) -->
                  <td>
                    <?php if (!empty($m['libro_titulo'])): ?>
                      <div class="fw-bold text-body">
                        <?php if (!empty($m['libro_id'])): ?>
                          <a href="/libro/<?= (int) $m['libro_id'] ?>" class="text-decoration-none text-body hover-primary">
                            <?= e($m['libro_titulo']) ?>
                          </a>
                        <?php else: ?>
                          <?= e($m['libro_titulo']) ?>
                        <?php endif; ?>
                      </div>
                      <?php if (!empty($m['libro_autor'])): ?>
                        <div class="small text-muted">por <?= e($m['libro_autor']) ?></div>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="text-muted fst-italic">—</span>
                    <?php endif; ?>
                  </td>

                  <!-- Método de Pago -->
                  <td>
                    <span class="badge bg-light text-dark border px-2 py-1 rounded-3 small">
                      <i class="bi <?= $m['metodo_pago'] === 'libro' ? 'bi-journal-bookmark' : 'bi-coin' ?> me-1"></i>
                      <?= e(ucfirst($m['metodo_pago'])) ?>
                    </span>
                  </td>

                  <!-- Cantidad -->
                  <td class="text-end fw-800 font-monospace">
                    <?php if ($esPositivo): ?>
                      <span class="text-success">+<?= (int) $m['cantidad'] ?></span>
                    <?php elseif ((int) $m['cantidad'] < 0): ?>
                      <span class="text-danger"><?= (int) $m['cantidad'] ?></span>
                    <?php else: ?>
                      <span class="text-muted">0</span>
                    <?php endif; ?>
                  </td>

                  <!-- Saldo Resultante -->
                  <td class="text-end fw-800 font-monospace text-body">
                    <?= (int) $m['saldo_resultante'] ?> <span class="small text-warning-emphasis">🪙</span>
                  </td>

                  <!-- Concepto / Reserva -->
                  <td class="small">
                    <div><?= e($m['concepto']) ?></div>
                    <?php if (!empty($m['codigo_reserva'])): ?>
                      <span class="badge bg-dark text-white font-monospace mt-1">
                        <i class="bi bi-qr-code me-1"></i><?= e($m['codigo_reserva']) ?>
                      </span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Paginación server-side -->
    <?php if ($totalPaginas > 1): ?>
      <div class="card-footer bg-transparent border-0 py-3 px-4 d-flex justify-content-between align-items-center">
        <span class="small text-muted">
          Página <?= $pagina ?> de <?= $totalPaginas ?> (<?= $total ?> registros)
        </span>
        <ul class="pagination pagination-sm mb-0">
          <?php if ($pagina > 1): ?>
            <li class="page-item">
              <a class="page-link" href="<?= e($baseUrlHistorial . '?' . http_build_query(array_merge($filtros, ['pagina' => $pagina - 1]))) ?>">
                &laquo; Anterior
              </a>
            </li>
          <?php endif; ?>

          <?php for ($p = max(1, $pagina - 2); $p <= min($totalPaginas, $pagina + 2); $p++): ?>
            <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
              <a class="page-link" href="<?= e($baseUrlHistorial . '?' . http_build_query(array_merge($filtros, ['pagina' => $p]))) ?>">
                <?= $p ?>
              </a>
            </li>
          <?php endfor; ?>

          <?php if ($pagina < $totalPaginas): ?>
            <li class="page-item">
              <a class="page-link" href="<?= e($baseUrlHistorial . '?' . http_build_query(array_merge($filtros, ['pagina' => $pagina + 1]))) ?>">
                Siguiente &raquo;
              </a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>
</div>
