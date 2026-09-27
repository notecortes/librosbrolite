<?php
/**
 * BookSwap · Sidebar de Administración (Fase 18 §3.5)
 *
 * Agrupada en 3 bloques:
 * - OPERACIÓN: Reservas · Catálogo · Movimientos
 * - PERSONAS: Usuarios · Roles y permisos
 * - SISTEMA: Configuración · Backups · Auditoría · Métricas
 */
declare(strict_types=1);

$uriActual = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$esActivo = fn(string $prefijo) => ($uriActual === $prefijo || str_starts_with($uriActual, $prefijo . '/')) ? 'active fw-bold bg-primary text-white' : 'text-body hover-bg-subtle';
?>

<aside class="col-12 col-lg-3 col-xl-2 mb-4 mb-lg-0" id="sidebar-admin">
  <div class="card border-0 shadow-sm rounded-4 p-3 bg-surface sticky-lg-top" style="top: 85px; z-index: 10;">
    <div class="d-flex align-items-center justify-content-between mb-3 px-2">
      <span class="small fw-bold text-uppercase text-muted">
        <i class="bi bi-layout-sidebar-inset text-primary me-1"></i>Navegación
      </span>
      <a href="/admin" class="badge bg-secondary-subtle text-secondary text-decoration-none">
        Panel
      </a>
    </div>

    <nav class="nav nav-pills flex-column gap-1" id="nav-admin-sidebar">
      <!-- 1. OPERACIÓN -->
      <div class="small fw-bold text-uppercase text-muted px-2 pt-2 pb-1 border-top mt-1">
        Operación
      </div>
      <a href="/admin/reservas" class="nav-link rounded-3 py-2 px-3 <?= $esActivo('/admin/reservas') ?>">
        <i class="bi bi-bookmark-check me-2"></i>Reservas
      </a>
      <a href="/admin/libros" class="nav-link rounded-3 py-2 px-3 <?= ($uriActual === '/admin/libros' || str_starts_with($uriActual, '/admin/libros') || str_starts_with($uriActual, '/admin/ejemplares')) ? 'active fw-bold bg-primary text-white' : 'text-body hover-bg-subtle' ?>">
        <i class="bi bi-journal-bookmark me-2"></i>Catálogo
      </a>
      <a href="/admin/movimientos" class="nav-link rounded-3 py-2 px-3 <?= $esActivo('/admin/movimientos') ?>">
        <i class="bi bi-cash-stack me-2"></i>Movimientos
      </a>
      <?php if (puede('csv.importar')): ?>
      <a href="/admin/csv" class="nav-link rounded-3 py-2 px-3 <?= $esActivo('/admin/csv') ?>">
        <i class="bi bi-file-earmark-spreadsheet me-2"></i>Importación CSV
      </a>
      <?php endif; ?>

      <!-- 2. PERSONAS -->
      <div class="small fw-bold text-uppercase text-muted px-2 pt-2 pb-1 border-top mt-2">
        Personas
      </div>
      <a href="/admin/usuarios" class="nav-link rounded-3 py-2 px-3 <?= $esActivo('/admin/usuarios') ?>">
        <i class="bi bi-people me-2"></i>Usuarios
      </a>

      <?php if (puede('roles.gestionar')): ?>
      <a href="/admin/roles" class="nav-link rounded-3 py-2 px-3 <?= $esActivo('/admin/roles') ?>">
        <i class="bi bi-shield-lock me-2"></i>Roles y permisos
      </a>
      <?php endif; ?>

      <!-- 3. SISTEMA -->
      <div class="small fw-bold text-uppercase text-muted px-2 pt-2 pb-1 border-top mt-2">
        Sistema
      </div>
      <?php if (puede('config.editar')): ?>
      <a href="/admin/configuracion" class="nav-link rounded-3 py-2 px-3 <?= $esActivo('/admin/configuracion') ?>">
        <i class="bi bi-sliders me-2"></i>Configuración
      </a>
      <?php endif; ?>
      <?php if (puede('backup.gestionar')): ?>
      <a href="/admin/backups" class="nav-link rounded-3 py-2 px-3 <?= $esActivo('/admin/backups') ?>">
        <i class="bi bi-database-down me-2"></i>Backups
      </a>
      <?php endif; ?>
      <?php if (puede('auditoria.ver')): ?>
      <a href="/admin/auditoria" class="nav-link rounded-3 py-2 px-3 <?= $esActivo('/admin/auditoria') ?>">
        <i class="bi bi-clock-history me-2"></i>Auditoría
      </a>
      <?php endif; ?>
      <?php if (puede('metricas.ver')): ?>
      <a href="/admin/metricas" class="nav-link rounded-3 py-2 px-3 <?= $esActivo('/admin/metricas') ?>">
        <i class="bi bi-graph-up-arrow me-2"></i>Métricas
      </a>
      <?php endif; ?>
    </nav>
  </div>
</aside>
