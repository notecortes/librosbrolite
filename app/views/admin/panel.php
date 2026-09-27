<?php
/**
 * BookSwap · admin/panel.php — Panel de administración general (v4.2).
 *
 * Presenta el catálogo completo de las 9 secciones operativas del centro:
 * 1. Resumen y Métricas
 * 2. Gestión de Usuarios
 * 4. Catálogo y Libros
 * 5. Gestión de Reservas
 * 6. Movimientos Globales (Ledger)
 * 7. Configuración del Sistema
 * 8. Copias de Seguridad
 * 9. Registro de Auditoría
 */
declare(strict_types=1);
?>

<div class="row g-4">
  <?php require __DIR__ . '/partials/sidebar.php'; ?>

  <div class="col-12 col-lg-9 col-xl-10">
    <!-- Breadcrumbs de navegación -->
    <nav aria-label="breadcrumb" class="mb-2">
      <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="/dashboard">BookSwap</a></li>
        <li class="breadcrumb-item active" aria-current="page">Panel de Administración</li>
      </ol>
    </nav>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
      <div>
        <h1 class="h2 fw-800 mb-1">Panel de Administración</h1>
        <p class="text-muted mb-0">Catálogo completo de administración con las 10 áreas operativas del centro.</p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a href="/admin/libros/nuevo" class="btn btn-primary fw-bold shadow-sm">
          <i class="bi bi-upc-scan me-1"></i>Añadir Libro (ISBN)
        </a>
        <a href="/mostrador" class="btn btn-outline-secondary fw-semibold">
          <i class="bi bi-shop me-1"></i>Mostrador
        </a>
      </div>
    </div>

    <!-- Fila de Acciones Rápidas (v4.5 §3.5) -->
    <div class="card border border-primary shadow-sm rounded-4 p-3 mb-4 bg-surface">
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <span class="small fw-bold text-muted text-uppercase me-2">
          <i class="bi bi-lightning-charge-fill text-warning me-1"></i>Acciones Rápidas:
        </span>
        <a href="/mostrador" class="btn btn-primary fw-bold shadow-sm">
          🚀 Entrega directa
        </a>
        <a href="/libros/entrada" class="btn btn-success fw-bold shadow-sm">
          📥 Entrada de libros
        </a>
        <a href="/admin/libros/nuevo" class="btn btn-outline-primary fw-semibold">
          ➕ Dar de alta libro
        </a>
        <?php if (puede('csv.importar')): ?>
        <a href="/admin/csv" class="btn btn-outline-success fw-semibold">
          📥 Importar CSV
        </a>
        <?php endif; ?>
        <?php if (puede('roles.gestionar')): ?>
        <a href="/admin/roles" class="btn btn-outline-dark fw-semibold">
          🔐 Permisos del personal
        </a>
        <?php endif; ?>

      </div>
    </div>

    <!-- Sección: Pendientes de Atención (v4.5 §3.5) -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-surface" id="seccion-pendientes-atencion">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="h5 fw-bold mb-0">
          <i class="bi bi-exclamation-diamond-fill text-warning me-2"></i>Pendientes de atención
        </h2>
        <span class="badge bg-warning-subtle text-warning-emphasis fw-bold">Atención prioritaria</span>
      </div>
      <div class="row g-3">
        <!-- 1. Reservas que expiran hoy -->
        <div class="col-md-6">
          <a href="/admin/reservas" class="card h-100 border p-3 rounded-4 text-decoration-none bg-surface-2 transition-hover">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="badge <?= ($reservasExpiranHoy ?? 0) > 0 ? 'bg-danger text-white' : 'bg-secondary-subtle text-secondary' ?> rounded-pill">
                <?= (int) ($reservasExpiranHoy ?? 0) ?>
              </span>
              <i class="bi bi-clock-history fs-4 text-danger"></i>
            </div>
            <div class="fw-bold text-dark">Reservas que expiran hoy</div>
            <div class="small text-muted">Revisar expiraciones y liberar copias</div>
          </a>
        </div>

        
    </div>

<!-- Tarjetas de métricas rápidas superiores -->
<div class="row g-3 g-md-4 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card p-3 border-0 shadow-sm rounded-4 bg-surface h-100">
      <div class="d-flex align-items-center gap-3">
        <div class="p-3 bg-primary-subtle text-primary rounded-3"><i class="bi bi-book fs-4"></i></div>
        <div>
          <div class="text-muted small">Libros en catálogo</div>
          <div class="h4 fw-800 mb-0"><?= (int) ($stats['libros'] ?? 0) ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card p-3 border-0 shadow-sm rounded-4 bg-surface h-100">
      <div class="d-flex align-items-center gap-3">
        <div class="p-3 bg-success-subtle text-success rounded-3"><i class="bi bi-journal-check fs-4"></i></div>
        <div>
          <div class="text-muted small">Copias disponibles</div>
          <div class="h4 fw-800 mb-0"><?= (int) ($stats['disponibles'] ?? 0) ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card p-3 border-0 shadow-sm rounded-4 bg-surface h-100">
      <div class="d-flex align-items-center gap-3">
        <div class="p-3 bg-warning-subtle text-warning rounded-3"><i class="bi bi-people fs-4"></i></div>
        <div>
          <div class="text-muted small">Usuarios activos</div>
          <div class="h4 fw-800 mb-0"><?= (int) ($stats['usuarios'] ?? 0) ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card p-3 border-0 shadow-sm rounded-4 bg-surface h-100">
      <div class="d-flex align-items-center gap-3">
        <div class="p-3 bg-info-subtle text-info rounded-3"><i class="bi bi-coin fs-4"></i></div>
        <div>
          <div class="text-muted small">Tokens emitidos</div>
          <div class="h4 fw-800 mb-0"><?= (int) ($stats['tokens'] ?? 0) ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 9 Secciones de Administración Operativa -->
<div class="row g-4">
  <!-- Sección 1: RESUMEN / MÉTRICAS -->
  <div class="col-md-6 col-lg-4">
    <div class="card border-0 shadow-sm rounded-4 bg-surface h-100 p-4 hover-card transition-all">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="p-3 bg-primary-subtle text-primary rounded-4">
          <i class="bi bi-graph-up-arrow fs-3"></i>
        </div>
        <span class="badge bg-primary-subtle text-primary fw-bold">Sección 1</span>
      </div>
      <h2 class="h5 fw-bold mb-2">Resumen y Métricas</h2>
      <p class="text-muted small mb-4">Gráficas interactivas con Chart.js, distribución de ejemplares, rotación y circulación global de tokens.</p>
      <div class="mt-auto">
        <a href="/admin/metricas" class="btn btn-outline-primary btn-sm rounded-pill fw-semibold w-100">
          Ver Métricas <i class="bi bi-arrow-right ms-1"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- Sección 2: USUARIOS -->
  <div class="col-md-6 col-lg-4">
    <div class="card border-0 shadow-sm rounded-4 bg-surface h-100 p-4 hover-card transition-all">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="p-3 bg-info-subtle text-info rounded-4">
          <i class="bi bi-people fs-3"></i>
        </div>
        <span class="badge bg-info-subtle text-info fw-bold">Sección 2</span>
      </div>
      <h2 class="h5 fw-bold mb-2">Usuarios</h2>
      <p class="text-muted small mb-4">Alta de usuarios, cambio de roles, activación/bloqueo, restablecimiento de contraseña temporal y ajuste manual de tokens.</p>
      <div class="mt-auto">
        <a href="/admin/usuarios" class="btn btn-outline-info btn-sm rounded-pill fw-semibold w-100">
          Gestionar Usuarios <i class="bi bi-arrow-right ms-1"></i>
        </a>
      </div>
    </div>
  </div>

  

  <!-- Sección 4: CATÁLOGO -->
  <div class="col-md-6 col-lg-4">
    <div class="card border-0 shadow-sm rounded-4 bg-surface h-100 p-4 hover-card transition-all">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="p-3 bg-warning-subtle text-warning rounded-4">
          <i class="bi bi-journal-album fs-3"></i>
        </div>
        <span class="badge bg-warning-subtle text-warning fw-bold">Sección 4</span>
      </div>
      <h2 class="h5 fw-bold mb-2">Catálogo y Libros</h2>
      <p class="text-muted small mb-4">CRUD bibliográfico, alta automática por ISBN vía APIs externas, inventario de copias físicas y carga masiva CSV.</p>
      <div class="mt-auto d-flex gap-2">
        <a href="/admin/libros" class="btn btn-outline-warning btn-sm rounded-pill fw-semibold flex-grow-1">
          Catálogo
        </a>
        <a href="/admin/libros/nuevo" class="btn btn-warning btn-sm rounded-pill fw-semibold text-white">
          + ISBN
        </a>
        <?php if (puede('csv.importar')): ?>
        <a href="/admin/csv" class="btn btn-outline-secondary btn-sm rounded-pill fw-semibold">
          CSV
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Sección 5: RESERVAS -->
  <div class="col-md-6 col-lg-4">
    <div class="card border-0 shadow-sm rounded-4 bg-surface h-100 p-4 hover-card transition-all">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="p-3 bg-danger-subtle text-danger rounded-4">
          <i class="bi bi-bookmark-check fs-3"></i>
        </div>
        <span class="badge bg-danger-subtle text-danger fw-bold">Sección 5</span>
      </div>
      <h2 class="h5 fw-bold mb-2">Gestión de Reservas</h2>
      <p class="text-muted small mb-4">Monitorización de todas las reservas, cancelación administrativa con liberación de copias y ejecución de expiración.</p>
      <div class="mt-auto">
        <a href="/admin/reservas" class="btn btn-outline-danger btn-sm rounded-pill fw-semibold w-100">
          Ver Reservas <i class="bi bi-arrow-right ms-1"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- Sección 6: MOVIMIENTOS -->
  <div class="col-md-6 col-lg-4">
    <div class="card border-0 shadow-sm rounded-4 bg-surface h-100 p-4 hover-card transition-all">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="p-3 bg-secondary-subtle text-secondary rounded-4">
          <i class="bi bi-cash-stack fs-3"></i>
        </div>
        <span class="badge bg-secondary-subtle text-secondary fw-bold">Sección 6</span>
      </div>
      <h2 class="h5 fw-bold mb-2">Movimientos Globales</h2>
      <p class="text-muted small mb-4">Libro mayor (ledger) inmutable con registro completo de depósitos, retiros, bonos y ajustes de saldo de todos los usuarios.</p>
      <div class="mt-auto">
        <a href="/admin/movimientos" class="btn btn-outline-secondary btn-sm rounded-pill fw-semibold w-100">
          Auditar Ledger <i class="bi bi-arrow-right ms-1"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- Sección 7: CONFIGURACIÓN -->
  <div class="col-md-6 col-lg-4">
    <div class="card border-0 shadow-sm rounded-4 bg-surface h-100 p-4 hover-card transition-all">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="p-3 bg-primary-subtle text-primary rounded-4">
          <i class="bi bi-sliders fs-3"></i>
        </div>
        <span class="badge bg-primary-subtle text-primary fw-bold">Sección 7</span>
      </div>
      <h2 class="h5 fw-bold mb-2">Configuración del Sistema</h2>
      <p class="text-muted small mb-4">Economía (coste libro, bonos), tiempos de reserva, datos del centro educativo, mapa y credenciales Google OAuth.</p>
      <div class="mt-auto">
        <a href="/admin/configuracion" class="btn btn-outline-primary btn-sm rounded-pill fw-semibold w-100">
          Configurar Parámetros <i class="bi bi-arrow-right ms-1"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- Sección 8: BACKUPS -->
  <div class="col-md-6 col-lg-4">
    <div class="card border-0 shadow-sm rounded-4 bg-surface h-100 p-4 hover-card transition-all">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="p-3 bg-danger-subtle text-danger rounded-4">
          <i class="bi bi-database-down fs-3"></i>
        </div>
        <span class="badge bg-danger-subtle text-danger fw-bold">Sección 8</span>
      </div>
      <h2 class="h5 fw-bold mb-2">Copias de Seguridad</h2>
      <p class="text-muted small mb-4">Generación manual o programada de volcados SQL completos (esquema + datos), descarga protegida y restauración.</p>
      <div class="mt-auto">
        <a href="/admin/backups" class="btn btn-outline-danger btn-sm rounded-pill fw-semibold w-100">
          Gestionar Backups <i class="bi bi-arrow-right ms-1"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- Sección 9: AUDITORÍA -->
  <div class="col-md-6 col-lg-4">
    <div class="card border-0 shadow-sm rounded-4 bg-surface h-100 p-4 hover-card transition-all">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="p-3 bg-dark-subtle text-dark rounded-4">
          <i class="bi bi-clock-history fs-3"></i>
        </div>
        <span class="badge bg-dark-subtle text-dark fw-bold">Sección 9</span>
      </div>
      <h2 class="h5 fw-bold mb-2">Registro de Auditoría</h2>
      <p class="text-muted small mb-4">Trazabilidad inmutable de todas las acciones del sistema con filtrado por eventos, usuarios y detalles JSON.</p>
      <div class="mt-auto">
        <a href="/admin/auditoria" class="btn btn-outline-dark btn-sm rounded-pill fw-semibold w-100">
          Consultar Auditoría <i class="bi bi-arrow-right ms-1"></i>
        </a>
      </div>
    </div>
  </div>

  <?php if (puede('roles.gestionar')): ?>
  <!-- Sección 10: ROLES Y PERMISOS -->
  <div class="col-md-6 col-lg-4">
    <div class="card border-0 shadow-sm rounded-4 bg-surface h-100 p-4 hover-card transition-all">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="p-3 bg-danger-subtle text-danger rounded-4">
          <i class="bi bi-shield-lock fs-3"></i>
        </div>
        <span class="badge bg-danger-subtle text-danger fw-bold">Sección 10</span>
      </div>
      <h2 class="h5 fw-bold mb-2">Matriz de Permisos</h2>
      <p class="text-muted small mb-4">Configuración de privilegios del personal, permisos base inmutables y trazabilidad de cambios.</p>
      <div class="mt-auto">
        <a href="/admin/roles" class="btn btn-outline-danger btn-sm rounded-pill fw-semibold w-100">
          Gestionar Permisos <i class="bi bi-arrow-right ms-1"></i>
        </a>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
  </div><!-- /.col-lg-9 -->
</div><!-- /.row -->
