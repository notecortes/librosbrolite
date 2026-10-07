<?php
/**
 * BookSwap · admin/auditoria.php — Registro de Auditoría del Sistema (v4.2).
 *
 * Funcionalidades:
 * - Listado cronológico inmutable de todos los eventos auditados.
 * - Filtros por acción, entidad, usuario y rango de fechas.
 * - Visualización detallada de payloads JSON.
 */
declare(strict_types=1);
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="/admin">Administración</a></li>
        <li class="breadcrumb-item active" aria-current="page">Auditoría</li>
      </ol>
    </nav>
    <h1 class="h2 fw-800 mb-1">Registro de Auditoría</h1>
    <p class="text-muted mb-0">Trazabilidad inmutable de eventos administrativos, de seguridad y de mostrador.</p>
  </div>
</div>

<!-- Filtros de búsqueda -->
<div class="card border-0 shadow-sm rounded-4 bg-surface p-3 mb-4">
  <form method="GET" action="/admin/auditoria" class="row g-2 align-items-center">
    <div class="col-md-3">
      <div class="input-group">
        <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control border-start-0" placeholder="Buscar en detalle o usuario..." value="<?= htmlspecialchars($filtros['q'] ?? '') ?>">
      </div>
    </div>
    <div class="col-6 col-md-3">
      <select name="accion" class="form-select">
        <option value="">Todas las acciones</option>
        <?php foreach ($acciones as $acc): ?>
          <option value="<?= htmlspecialchars($acc) ?>" <?= ($filtros['accion'] ?? '') === $acc ? 'selected' : '' ?>>
            <?= htmlspecialchars($acc) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <select name="entidad" class="form-select">
        <option value="">Todas las entidades</option>
        <?php foreach ($entidades as $ent): ?>
          <option value="<?= htmlspecialchars($ent) ?>" <?= ($filtros['entidad'] ?? '') === $ent ? 'selected' : '' ?>>
            <?= htmlspecialchars($ent) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <input type="date" name="desde" class="form-control" title="Desde" value="<?= htmlspecialchars($filtros['desde'] ?? '') ?>" placeholder="Desde">
    </div>
    <div class="col-6 col-md-2 d-flex gap-2">
      <button type="submit" class="btn btn-primary fw-semibold flex-grow-1">Filtrar</button>
      <a href="/admin/auditoria" class="btn btn-outline-secondary" title="Limpiar"><i class="bi bi-x-lg"></i></a>
    </div>
  </form>
</div>

<!-- Tabla de auditoría -->
<div class="card border-0 shadow-sm rounded-4 bg-surface overflow-hidden mb-4">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 font-sans">
      <thead class="table-light">
        <tr>
          <th class="ps-4">Fecha / IP</th>
          <th>Acción</th>
          <th>Usuario</th>
          <th>Entidad / ID</th>
          <th class="pe-4">Detalle</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($registros)): ?>
          <tr>
            <td colspan="5" class="text-center py-5 text-muted">
              <i class="bi bi-shield-check fs-1 d-block mb-2"></i>
              No se encontraron registros de auditoría con los filtros indicados.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($registros as $reg): ?>
            <tr>
              <td class="ps-4" style="min-width: 140px;">
                <div class="small fw-semibold"><?= date('d/m/Y H:i:s', strtotime($reg['fecha'])) ?></div>
                <div class="small text-muted font-monospace"><?= htmlspecialchars($reg['ip'] ?? '—') ?></div>
              </td>
              <td>
                <span class="badge bg-dark-subtle text-dark border font-monospace">
                  <?= htmlspecialchars($reg['accion']) ?>
                </span>
              </td>
              <td>
                <?php if (!empty($reg['usuario_id'])): ?>
                  <div class="fw-semibold text-body"><?= htmlspecialchars($reg['usuario_nombre'] ?? 'ID #' . $reg['usuario_id']) ?></div>
                  <div class="small text-muted"><?= htmlspecialchars($reg['usuario_email'] ?? '') ?></div>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary">Sistema</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($reg['entidad'])): ?>
                  <span class="text-muted small"><?= htmlspecialchars($reg['entidad']) ?></span>
                  <?php if (!empty($reg['entidad_id'])): ?>
                    <span class="font-monospace small fw-bold">#<?= $reg['entidad_id'] ?></span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
              <td class="pe-4">
                <?php if (!empty($reg['detalle'])): ?>
                  <?php $json = json_decode($reg['detalle'], true); ?>
                  <?php if (is_array($json)): ?>
                    <div class="small bg-light p-2 rounded border font-monospace" style="max-width: 450px; overflow-x: auto;">
                      <?php foreach ($json as $k => $v): ?>
                        <div><strong class="text-secondary"><?= htmlspecialchars((string) $k) ?>:</strong> <?= htmlspecialchars(is_array($v) ? json_encode($v) : (string) $v) ?></div>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <span class="small font-monospace"><?= htmlspecialchars($reg['detalle']) ?></span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Paginación -->
  <?php if ($total_paginas > 1): ?>
    <div class="d-flex justify-content-between align-items-center p-3 border-top bg-light">
      <div class="small text-muted">
        Mostrando página <?= $pagina ?> de <?= $total_paginas ?> (total: <?= $total ?> registros)
      </div>
      <ul class="pagination pagination-sm mb-0">
        <?php for ($p = 1; $p <= $total_paginas; $p++): ?>
          <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
            <a class="page-link" href="/admin/auditoria?pagina=<?= $p ?>&q=<?= urlencode($filtros['q'] ?? '') ?>&accion=<?= urlencode($filtros['accion'] ?? '') ?>&entidad=<?= urlencode($filtros['entidad'] ?? '') ?>&desde=<?= urlencode($filtros['desde'] ?? '') ?>&hasta=<?= urlencode($filtros['hasta'] ?? '') ?>">
              <?= $p ?>
            </a>
          </li>
        <?php endfor; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>
