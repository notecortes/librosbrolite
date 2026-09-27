<?php
/**
 * BookSwap · admin/movimientos/index.php — Libro Mayor Global / Ledger (v4.2).
 *
 * Funcionalidades:
 * - Listado inmutable y de solo lectura de todos los movimientos de tokens de todos los usuarios.
 * - Filtros por tipo (depósito, retiro, bono, ajuste), usuario y rango de fechas.
 * - Auditoría visual de saldos resultantes y trazabilidad con transacciones y libros.
 */
declare(strict_types=1);
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="/admin">Administración</a></li>
        <li class="breadcrumb-item active" aria-current="page">Movimientos</li>
      </ol>
    </nav>
    <h1 class="h2 fw-800 mb-1">Libro Mayor Global (Ledger)</h1>
    <p class="text-muted mb-0">Registro histórico inmutable de todos los movimientos y emisiones de tokens del centro.</p>
  </div>
  <div>
    <a href="/admin/usuarios" class="btn btn-outline-primary fw-semibold">
      <i class="bi bi-people me-1"></i>Ir a Usuarios (Ajustes de Saldo)
    </a>
  </div>
</div>

<!-- Filtros de búsqueda -->
<div class="card border-0 shadow-sm rounded-4 bg-surface p-3 mb-4">
  <form method="GET" action="/admin/movimientos" class="row g-2 align-items-center">
    <div class="col-md-4">
      <div class="input-group">
        <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control border-start-0" placeholder="Usuario, concepto o libro..." value="<?= htmlspecialchars($filtros['q'] ?? '') ?>">
      </div>
    </div>
    <div class="col-6 col-md-2">
      <select name="tipo" class="form-select">
        <option value="">Todos los tipos</option>
        <option value="deposito" <?= ($filtros['tipo'] ?? '') === 'deposito' ? 'selected' : '' ?>>Depósitos</option>
        <option value="retiro" <?= ($filtros['tipo'] ?? '') === 'retiro' ? 'selected' : '' ?>>Retiros</option>
        <option value="bono" <?= ($filtros['tipo'] ?? '') === 'bono' ? 'selected' : '' ?>>Bonos</option>
        <option value="ajuste" <?= ($filtros['tipo'] ?? '') === 'ajuste' ? 'selected' : '' ?>>Ajustes</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <input type="date" name="desde" class="form-control" title="Desde" value="<?= htmlspecialchars($filtros['desde'] ?? '') ?>" placeholder="Desde">
    </div>
    <div class="col-6 col-md-2">
      <input type="date" name="hasta" class="form-control" title="Hasta" value="<?= htmlspecialchars($filtros['hasta'] ?? '') ?>" placeholder="Hasta">
    </div>
    <div class="col-6 col-md-2 d-flex gap-2">
      <button type="submit" class="btn btn-primary fw-semibold flex-grow-1">Filtrar</button>
      <a href="/admin/movimientos" class="btn btn-outline-secondary" title="Limpiar"><i class="bi bi-x-lg"></i></a>
    </div>
  </form>
</div>

<!-- Tabla de movimientos -->
<div class="card border-0 shadow-sm rounded-4 bg-surface overflow-hidden mb-4">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="ps-4">ID / Fecha</th>
          <th>Usuario</th>
          <th>Tipo</th>
          <th>Libro / Concepto</th>
          <th class="text-end">Cantidad</th>
          <th class="text-end pe-4">Saldo Resultante</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($movimientos)): ?>
          <tr>
            <td colspan="6" class="text-center py-5 text-muted">
              <i class="bi bi-cash-stack fs-1 d-block mb-2"></i>
              No se encontraron movimientos registrados con los filtros especificados.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($movimientos as $m): ?>
            <tr>
              <td class="ps-4">
                <div class="font-monospace small text-muted">#<?= $m['id'] ?></div>
                <div class="small fw-semibold"><?= date('d/m/Y H:i', strtotime($m['fecha'])) ?></div>
              </td>
              <td>
                <div class="fw-bold text-body">
                  <a href="/admin/usuarios/<?= $m['usuario_id'] ?>/historial" class="text-decoration-none text-body">
                    <?= htmlspecialchars($m['usuario_nombre'] ?? '') ?>
                  </a>
                </div>
                <div class="small text-muted"><?= htmlspecialchars($m['usuario_email'] ?? '') ?></div>
              </td>
              <td>
                <?php if ($m['tipo'] === 'deposito'): ?>
                  <span class="badge bg-success-subtle text-success border border-success-subtle">
                    <i class="bi bi-journal-arrow-up me-1"></i>Depósito
                  </span>
                <?php elseif ($m['tipo'] === 'retiro'): ?>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                    <i class="bi bi-journal-arrow-down me-1"></i>Retiro
                  </span>
                <?php elseif ($m['tipo'] === 'bono'): ?>
                  <span class="badge bg-info-subtle text-info border border-info-subtle">
                    <i class="bi bi-gift me-1"></i>Bono
                  </span>
                <?php else: ?>
                  <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                    <i class="bi bi-sliders me-1"></i>Ajuste
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($m['libro_titulo'])): ?>
                  <div class="fw-semibold text-body"><?= htmlspecialchars($m['libro_titulo']) ?></div>
                <?php endif; ?>
                <div class="small text-muted"><?= htmlspecialchars($m['concepto'] ?? '—') ?></div>
                <?php if (!empty($m['codigo_reserva'])): ?>
                  <div class="small font-monospace text-primary">Reserva: <?= htmlspecialchars($m['codigo_reserva']) ?></div>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <span class="fw-bold fs-6 <?= (int) $m['cantidad'] > 0 ? 'text-success' : 'text-danger' ?>">
                  <?= (int) $m['cantidad'] > 0 ? '+' . $m['cantidad'] : $m['cantidad'] ?>
                </span>
              </td>
              <td class="text-end pe-4">
                <span class="fw-bold fs-6 font-monospace text-body">
                  <?= (int) $m['saldo_resultante'] ?> <i class="bi bi-coin text-warning"></i>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Paginación -->
  <?php if ($totalPaginas > 1): ?>
    <div class="d-flex justify-content-between align-items-center p-3 border-top bg-light">
      <div class="small text-muted">
        Mostrando página <?= $pagina ?> de <?= $totalPaginas ?> (total: <?= $total ?> movimientos)
      </div>
      <ul class="pagination pagination-sm mb-0">
        <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
          <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
            <a class="page-link" href="/admin/movimientos?pagina=<?= $p ?>&q=<?= urlencode($filtros['q'] ?? '') ?>&tipo=<?= urlencode($filtros['tipo'] ?? '') ?>&desde=<?= urlencode($filtros['desde'] ?? '') ?>&hasta=<?= urlencode($filtros['hasta'] ?? '') ?>">
              <?= $p ?>
            </a>
          </li>
        <?php endfor; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>
