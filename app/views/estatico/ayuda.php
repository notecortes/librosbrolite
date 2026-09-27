<?php
/**
 * BookSwap · Vista Centro de Ayuda y Guía de Funcionalidades
 *
 * Muestra las características operativas del sistema con capturas de pantalla reales.
 * Filtra el contenido dinámicamente según el rol del usuario autenticado (Visitante, Lector, Personal, Admin)
 * para que cada usuario solo vea las características que puede utilizar.
 */
declare(strict_types=1);

$nombreCentro = $config['centro_nombre'] ?? 'BookSwap';
$rolActual = $usuario['rol_nombre'] ?? 'INVITADO';
?>

<div class="py-2">
  <!-- Cabecera y Bienvenida -->
  <div class="text-center max-w-800 mx-auto mb-4">
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-bold mb-3">
      <i class="bi bi-question-circle-fill me-1"></i>Centro de Ayuda y Documentación
    </span>
    <h1 class="display-6 fw-800 mb-2">Guía de Funcionalidades de <?= e($nombreCentro) ?></h1>
    <p class="lead text-muted mb-3">
      Descubre todo lo que puedes hacer en la plataforma. Tu guía se adapta a tu perfil de acceso.
    </p>

    <!-- Indicador de Rol Actual -->
    <div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill bg-surface border shadow-sm small">
      <span class="text-muted">Tu perfil actual:</span>
      <?php if ($rolActual === 'ADMIN'): ?>
        <span class="badge bg-danger text-white"><i class="bi bi-shield-shaded me-1"></i>Administrador</span>
      <?php elseif ($rolActual === 'PERSONAL'): ?>
        <span class="badge bg-warning text-dark"><i class="bi bi-shop me-1"></i>Personal de Mostrador</span>
      <?php elseif ($rolActual === 'USUARIO'): ?>
        <span class="badge bg-primary text-white"><i class="bi bi-person-badge me-1"></i>Lector / Alumno</span>
      <?php else: ?>
        <span class="badge bg-secondary text-white"><i class="bi bi-eye me-1"></i>Visitante / Invitado</span>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($rolActual === 'INVITADO'): ?>
    <!-- Aviso para visitantes no autenticados -->
    <div class="alert alert-info border-info-subtle rounded-4 max-w-850 mx-auto mb-4 d-flex align-items-center gap-3">
      <i class="bi bi-info-circle-fill fs-3 text-info flex-shrink-0"></i>
      <div class="small">
        <strong>Estás consultando la ayuda para visitantes.</strong> Aquí verás las funciones públicas.
        Para acceder a la reserva de libros, tu código de barras personal, tu saldo de tokens y las funciones del mostrador,
        <a href="/login" class="fw-bold alert-link">inicia sesión</a> o <a href="/registro" class="fw-bold alert-link">regístrate gratis</a>.
      </div>
    </div>
  <?php elseif ($rolActual === 'ADMIN'): ?>
    <!-- Selector interactivo de vista exclusivo para Administrador -->
    <div class="card border-0 bg-surface shadow-sm rounded-4 p-3 max-w-850 mx-auto mb-4 text-center">
      <div class="d-flex flex-wrap justify-content-center align-items-center gap-2">
        <span class="small fw-bold text-muted me-1"><i class="bi bi-sliders me-1"></i>Filtro de visualización (Admin):</span>
        <div class="btn-group btn-group-sm" role="group" id="adminHelpFilter">
          <button type="button" class="btn btn-outline-primary active" onclick="filtrarSeccionAyuda('todos')">Todas las funciones</button>
          <button type="button" class="btn btn-outline-danger" onclick="filtrarSeccionAyuda('seccion-admin')">Solo Administrador</button>
          <button type="button" class="btn btn-outline-warning text-dark" onclick="filtrarSeccionAyuda('seccion-personal')">Solo Mostrador</button>
          <button type="button" class="btn btn-outline-info" onclick="filtrarSeccionAyuda('seccion-usuario')">Solo Lectores</button>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Buscador en tiempo real de la ayuda -->
  <div class="max-w-600 mx-auto mb-5">
    <div class="input-group input-group-lg shadow-sm rounded-pill overflow-hidden border">
      <span class="input-group-text bg-surface border-0 ps-4 text-muted"><i class="bi bi-search"></i></span>
      <input type="text" id="helpSearchInput" class="form-control bg-surface border-0 fs-6 py-3"
             placeholder="¿Qué funcionalidad estás buscando? (ej. escanear, reserva, saldo, csv...)"
             onkeyup="buscarEnAyuda()">
      <button class="btn btn-soft px-4" type="button" onclick="limpiarBusquedaAyuda()" id="btnLimpiarBusqueda" style="display:none;">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
  </div>

  <!-- CONTENIDO DE AYUDA MODULAR POR ROL -->
  <div class="max-w-1000 mx-auto" id="contenedorAyuda">

    <!-- ======================================================== -->
    <!-- 1. MÓDULOS DE ADMINISTRADOR (Visible solo para ADMIN)     -->
    <!-- ======================================================== -->
    <?php if ($rolActual === 'ADMIN'): ?>
      <div class="bloque-rol mb-5" id="seccion-admin">
        <div class="d-flex align-items-center gap-2 pb-2 mb-4 border-bottom border-danger-subtle">
          <span class="badge bg-danger p-2 rounded-3 fs-5"><i class="bi bi-shield-shaded"></i></span>
          <div>
            <h2 class="h4 fw-bold mb-0 text-danger">Herramientas de Administración del Sistema</h2>
            <p class="text-muted small mb-0">Gestión global de usuarios, roles, catálogo masivo y seguridad</p>
          </div>
        </div>

        <div class="row g-4">
          <!-- Tarjeta 1: Panel General y Métricas -->
          <div class="col-lg-6 item-ayuda" data-keywords="panel admin metricas alertas libros usuarios estadisticas">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-surface overflow-hidden">
              <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4 d-flex justify-content-between align-items-center">
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">Panel de Control</span>
                <span class="text-muted small"><i class="bi bi-speedometer2 me-1"></i>/admin</span>
              </div>
              <div class="card-body px-4">
                <h3 class="h5 fw-bold mb-2">Panel Central de Métricas y Alertas</h3>
                <p class="text-muted small mb-3">
                  Supervisa en tiempo real el pulso de la biblioteca: reservas pendientes de entrega, ejemplares activos, lectores registrados y atajos directos a las tareas prioritarias del centro.
                </p>
                <div class="marco-captura mb-3" onclick="abrirModalCaptura('/assets/img/ayuda/admin_dashboard.png', 'Panel Central de Métricas y Alertas')">
                  <img src="/assets/img/ayuda/admin_dashboard.png" alt="Panel de Administración" class="img-fluid rounded-3 shadow-xs">
                  <div class="overlay-zoom"><i class="bi bi-zoom-in me-1"></i>Clic para ampliar</div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2">
                  <span class="small text-muted"><i class="bi bi-check2-circle text-success me-1"></i>Métricas en vivo</span>
                  <a href="/admin" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-semibold">Abrir Panel <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
              </div>
            </div>
          </div>

          <!-- Tarjeta 2: Gestión de Usuarios y Reseteo -->
          <div class="col-lg-6 item-ayuda" data-keywords="usuarios roles clave password reset desactivar activar tokens alumnos profesores">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-surface overflow-hidden">
              <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4 d-flex justify-content-between align-items-center">
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">Usuarios y Accesos</span>
                <span class="text-muted small"><i class="bi bi-people me-1"></i>/admin/usuarios</span>
              </div>
              <div class="card-body px-4">
                <h3 class="h5 fw-bold mb-2">Gestión de Usuarios y Recuperación Segura</h3>
                <p class="text-muted small mb-3">
                  Administra las cuentas de lectores y personal: modifica roles (Lector, Personal, Admin), desactiva usuarios inactivos y genera enlaces temporales de reseteo de contraseña de un solo uso con un clic.
                </p>
                <div class="marco-captura mb-3" onclick="abrirModalCaptura('/assets/img/ayuda/admin_usuarios.png', 'Gestión de Usuarios y Enlaces de Contraseña')">
                  <img src="/assets/img/ayuda/admin_usuarios.png" alt="Gestión de Usuarios" class="img-fluid rounded-3 shadow-xs">
                  <div class="overlay-zoom"><i class="bi bi-zoom-in me-1"></i>Clic para ampliar</div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2">
                  <span class="small text-muted"><i class="bi bi-key text-warning me-1"></i>Enlace seguro 1h</span>
                  <a href="/admin/usuarios" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-semibold">Gestionar Usuarios <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
              </div>
            </div>
          </div>

          <!-- Tarjeta 3: Matriz de Permisos RBAC -->
          <div class="col-lg-6 item-ayuda" data-keywords="roles permisos rbac matriz privilegios seguridad personal admin usuario">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-surface overflow-hidden">
              <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4 d-flex justify-content-between align-items-center">
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">Seguridad RBAC</span>
                <span class="text-muted small"><i class="bi bi-shield-lock me-1"></i>/admin/roles</span>
              </div>
              <div class="card-body px-4">
                <h3 class="h5 fw-bold mb-2">Matriz de Permisos por Rol</h3>
                <p class="text-muted small mb-3">
                  Configura de forma visual y granular qué permisos tiene cada rol: asigna o revoca acceso a importaciones CSV, entregas de mostrador o auditoría, con salvaguarda de permisos base esenciales.
                </p>
                <div class="marco-captura mb-3" onclick="abrirModalCaptura('/assets/img/ayuda/admin_roles.png', 'Matriz de Permisos RBAC')">
                  <img src="/assets/img/ayuda/admin_roles.png" alt="Matriz de Permisos" class="img-fluid rounded-3 shadow-xs">
                  <div class="overlay-zoom"><i class="bi bi-zoom-in me-1"></i>Clic para ampliar</div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2">
                  <span class="small text-muted"><i class="bi bi-lock-fill text-danger me-1"></i>Protección base</span>
                  <a href="/admin/roles" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-semibold">Ver Matriz <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
              </div>
            </div>
          </div>

          <!-- Tarjeta 4: Importación Masiva CSV -->
          <div class="col-lg-6 item-ayuda" data-keywords="csv importar catalogo libros lotes excel carga masiva isbn">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-surface overflow-hidden">
              <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4 d-flex justify-content-between align-items-center">
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">Carga de Datos</span>
                <span class="text-muted small"><i class="bi bi-file-earmark-spreadsheet me-1"></i>/admin/csv</span>
              </div>
              <div class="card-body px-4">
                <h3 class="h5 fw-bold mb-2">Importación Masiva de Catálogo (CSV)</h3>
                <p class="text-muted small mb-3">
                  Sube cientos de títulos simultáneamente mediante archivos CSV normalizados. El sistema detecta títulos existentes, actualiza metadatos y crea los ejemplares físicos solicitados de forma atómica.
                </p>
                <div class="marco-captura mb-3" onclick="abrirModalCaptura('/assets/img/ayuda/admin_csv.png', 'Importador Masivo CSV')">
                  <img src="/assets/img/ayuda/admin_csv.png" alt="Importación CSV" class="img-fluid rounded-3 shadow-xs">
                  <div class="overlay-zoom"><i class="bi bi-zoom-in me-1"></i>Clic para ampliar</div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2">
                  <span class="small text-muted"><i class="bi bi-file-check text-success me-1"></i>Plantilla descargable</span>
                  <a href="/admin/csv" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-semibold">Importar CSV <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
              </div>
            </div>
          </div>

          <!-- Tarjeta 5: Copias de Seguridad y Auditoría -->
          <div class="col-lg-6 item-ayuda" data-keywords="backups copias seguridad base de datos descarga sql restauracion auditoria">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-surface overflow-hidden">
              <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4 d-flex justify-content-between align-items-center">
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">Respaldo y Seguridad</span>
                <span class="text-muted small"><i class="bi bi-database-down me-1"></i>/admin/backups</span>
              </div>
              <div class="card-body px-4">
                <h3 class="h5 fw-bold mb-2">Copias de Seguridad de Base de Datos</h3>
                <p class="text-muted small mb-3">
                  Genera copias de seguridad de la base de datos SQL con un clic, mantén un historial ordenado y descárgalas de forma protegida contra accesos no autorizados mediante sanitización de rutas.
                </p>
                <div class="marco-captura mb-3" onclick="abrirModalCaptura('/assets/img/ayuda/admin_backups.png', 'Copias de Seguridad y Respaldos')">
                  <img src="/assets/img/ayuda/admin_backups.png" alt="Backups" class="img-fluid rounded-3 shadow-xs">
                  <div class="overlay-zoom"><i class="bi bi-zoom-in me-1"></i>Clic para ampliar</div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2">
                  <span class="small text-muted"><i class="bi bi-shield-check text-info me-1"></i>Descarga segura</span>
                  <a href="/admin/backups" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-semibold">Ver Backups <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- ======================================================== -->
    <!-- 2. MÓDULOS DE MOSTRADOR / PERSONAL                       -->
    <!-- (Visible para PERSONAL y ADMIN)                          -->
    <!-- ======================================================== -->
    <?php if ($rolActual === 'PERSONAL' || $rolActual === 'ADMIN'): ?>
      <div class="bloque-rol mb-5" id="seccion-personal">
        <div class="d-flex align-items-center gap-2 pb-2 mb-4 border-bottom border-warning-subtle">
          <span class="badge bg-warning text-dark p-2 rounded-3 fs-5"><i class="bi bi-shop"></i></span>
          <div>
            <h2 class="h4 fw-bold mb-0 text-warning-emphasis">Operativa de Mostrador y Biblioteca Física</h2>
            <p class="text-muted small mb-0">Escaneo de reservas, entregas directas, recepción de depósitos y trazabilidad</p>
          </div>
        </div>

        <div class="row g-4">
          <!-- Tarjeta 1: Escáner Universal de Mostrador -->
          <div class="col-lg-6 item-ayuda" data-keywords="mostrador escaner optico codigo barras pistola lector 1d reserva res isbn email">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-surface overflow-hidden">
              <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4 d-flex justify-content-between align-items-center">
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill">Mostrador Rápido</span>
                <span class="text-muted small"><i class="bi bi-upc-scan me-1"></i>/mostrador</span>
              </div>
              <div class="card-body px-4">
                <h3 class="h5 fw-bold mb-2">Escáner Universal Óptico 1D</h3>
                <p class="text-muted small mb-3">
                  Punto de atención con teclado virtual y soporte para pistola de código de barras. Detecta al instante si el código escaneado es una <strong>reserva (RES-*)</strong>, un <strong>ISBN de libro</strong> o el <strong>email de un lector</strong>.
                </p>
                <div class="marco-captura mb-3" onclick="abrirModalCaptura('/assets/img/ayuda/personal_mostrador.png', 'Terminal de Mostrador y Escáner Universal')">
                  <img src="/assets/img/ayuda/personal_mostrador.png" alt="Mostrador Universal" class="img-fluid rounded-3 shadow-xs">
                  <div class="overlay-zoom"><i class="bi bi-zoom-in me-1"></i>Clic para ampliar</div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2">
                  <span class="small text-muted"><i class="bi bi-lightning-charge text-warning me-1"></i>Búsqueda instantánea</span>
                  <a href="/mostrador" class="btn btn-warning text-dark btn-sm rounded-pill px-3 fw-bold">Abrir Mostrador <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
              </div>
            </div>
          </div>

          <!-- Tarjeta 2: Entrega Directa Rápida (Sin Reserva) -->
          <div class="col-lg-6 item-ayuda" data-keywords="entrega directa mostrador rapido sin reserva libro aportado trueque tokens">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-surface overflow-hidden">
              <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4 d-flex justify-content-between align-items-center">
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill">Salida Rápida</span>
                <span class="text-muted small"><i class="bi bi-box-arrow-up-right me-1"></i>/mostrador/entrega-directa</span>
              </div>
              <div class="card-body px-4">
                <h3 class="h5 fw-bold mb-2">Entrega Directa sin Reserva Previa</h3>
                <p class="text-muted small mb-3">
                  Permite a un alumno retirar un ejemplar directamente en ventanilla sin reserva web. Puede pagar con sus tokens acumulados o mediante <em>intercambio físico directo 1x1</em> aportando otro libro en el acto.
                </p>
                <div class="marco-captura mb-3" onclick="abrirModalCaptura('/assets/img/ayuda/personal_entrega_directa.png', 'Entrega Directa en Mostrador')">
                  <img src="/assets/img/ayuda/personal_entrega_directa.png" alt="Entrega Directa" class="img-fluid rounded-3 shadow-xs">
                  <div class="overlay-zoom"><i class="bi bi-zoom-in me-1"></i>Clic para ampliar</div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2">
                  <span class="small text-muted"><i class="bi bi-arrow-left-right text-primary me-1"></i>Cobro con tokens o libro</span>
                  <a href="/mostrador/entrega-directa" class="btn btn-warning text-dark btn-sm rounded-pill px-3 fw-bold">Entrega Directa <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
              </div>
            </div>
          </div>

          <!-- Tarjeta 3: Entrada Unificada de Libros y Depósitos -->
          <div class="col-lg-6 item-ayuda" data-keywords="entrada libros deposito donacion aportacion ejemplares lote acreditacion tokens lector">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-surface overflow-hidden">
              <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4 d-flex justify-content-between align-items-center">
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill">Entrada de Libros</span>
                <span class="text-muted small"><i class="bi bi-journal-plus me-1"></i>/libros/entrada</span>
              </div>
              <div class="card-body px-4">
                <h3 class="h5 fw-bold mb-2">Entrada Unificada de Libros y Acreditación</h3>
                <p class="text-muted small mb-3">
                  Recepción ágil de libros: escanea el ISBN, indica el número de copias y el depositante. El sistema crea las copias físicas, acredita automáticamente los tokens ganados al lector y notifica a quienes tenían el título en su lista de deseos.
                </p>
                <div class="marco-captura mb-3" onclick="abrirModalCaptura('/assets/img/ayuda/personal_entrada.png', 'Entrada Unificada de Libros')">
                  <img src="/assets/img/ayuda/personal_entrada.png" alt="Entrada de Libros" class="img-fluid rounded-3 shadow-xs">
                  <div class="overlay-zoom"><i class="bi bi-zoom-in me-1"></i>Clic para ampliar</div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2">
                  <span class="small text-muted"><i class="bi bi-coin text-warning me-1"></i>Acredita tokens al instante</span>
                  <a href="/libros/entrada" class="btn btn-warning text-dark btn-sm rounded-pill px-3 fw-bold">Registrar Entrada <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
              </div>
            </div>
          </div>

          <!-- Tarjeta 4: Trazabilidad Integral por Ejemplar -->
          <div class="col-lg-6 item-ayuda" data-keywords="trazabilidad ejemplar copia historia libro ciclo vida estado ubicacion condicion">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-surface overflow-hidden">
              <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4 d-flex justify-content-between align-items-center">
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill">Trazabilidad</span>
                <span class="text-muted small"><i class="bi bi-clock-history me-1"></i>/ejemplar/{id}</span>
              </div>
              <div class="card-body px-4">
                <h3 class="h5 fw-bold mb-2">Trazabilidad y Línea de Vida de la Copia</h3>
                <p class="text-muted small mb-3">
                  Conoce la historia completa de cada copia física: quién la donó, qué lectores la han retirado, incidencias registradas y cambios de ubicación o condición (bueno, regular, desgastado).
                </p>
                <div class="marco-captura mb-3" onclick="abrirModalCaptura('/assets/img/ayuda/personal_trazabilidad.png', 'Línea de Vida y Trazabilidad del Ejemplar')">
                  <img src="/assets/img/ayuda/personal_trazabilidad.png" alt="Trazabilidad del Ejemplar" class="img-fluid rounded-3 shadow-xs">
                  <div class="overlay-zoom"><i class="bi bi-zoom-in me-1"></i>Clic para ampliar</div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2">
                  <span class="small text-muted"><i class="bi bi-shield-check text-success me-1"></i>Historial inalterable</span>
                  <a href="/catalogo" class="btn btn-outline-warning text-dark btn-sm rounded-pill px-3 fw-semibold">Explorar Ejemplares <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- ======================================================== -->
    <!-- 3. MÓDULOS DE USUARIO / LECTOR                           -->
    <!-- (Visible para USUARIO, y también para PERSONAL y ADMIN)   -->
    <!-- ======================================================== -->
    <?php if ($rolActual === 'USUARIO' || $rolActual === 'PERSONAL' || $rolActual === 'ADMIN'): ?>
      <div class="bloque-rol mb-5" id="seccion-usuario">
        <div class="d-flex align-items-center gap-2 pb-2 mb-4 border-bottom border-primary-subtle">
          <span class="badge bg-primary text-white p-2 rounded-3 fs-5"><i class="bi bi-person-badge"></i></span>
          <div>
            <h2 class="h4 fw-bold mb-0 text-primary">Funcionalidades para Lectores y Estudiantes</h2>
            <p class="text-muted small mb-0">Gestión de saldo, catálogo de libros, reservas con código de barras y avisos de stock</p>
          </div>
        </div>

        <div class="row g-4">
          <!-- Tarjeta 1: Dashboard y Saldo de Tokens -->
          <div class="col-lg-6 item-ayuda" data-keywords="dashboard saldo tokens cuenta movimientos resumen lecturas">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-surface overflow-hidden">
              <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4 d-flex justify-content-between align-items-center">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">Mi Espacio</span>
                <span class="text-muted small"><i class="bi bi-person me-1"></i>/dashboard</span>
              </div>
              <div class="card-body px-4">
                <h3 class="h5 fw-bold mb-2">Panel Personal y Saldo de Tokens</h3>
                <p class="text-muted small mb-3">
                  Tu centro de control lector: consulta tu saldo de tokens disponible, cuántos tokens tienes comprometidos en reservas activas y accede rápidamente a tus últimas lecturas y movimientos.
                </p>
                <div class="marco-captura mb-3" onclick="abrirModalCaptura('/assets/img/ayuda/usuario_dashboard.png', 'Panel Personal de Usuario y Saldo')">
                  <img src="/assets/img/ayuda/usuario_dashboard.png" alt="Dashboard de Usuario" class="img-fluid rounded-3 shadow-xs">
                  <div class="overlay-zoom"><i class="bi bi-zoom-in me-1"></i>Clic para ampliar</div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2">
                  <span class="small text-muted"><i class="bi bi-coin text-warning me-1"></i>Saldo en tiempo real</span>
                  <a href="/dashboard" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold">Ir a mi Panel <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
              </div>
            </div>
          </div>

          <!-- Tarjeta 2: Catálogo y Reserva en 1 Clic -->
          <div class="col-lg-6 item-ayuda" data-keywords="catalogo buscar libros reservar un clic disponibilidad genero autor">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-surface overflow-hidden">
              <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4 d-flex justify-content-between align-items-center">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">Catálogo</span>
                <span class="text-muted small"><i class="bi bi-journal-bookmark me-1"></i>/catalogo</span>
              </div>
              <div class="card-body px-4">
                <h3 class="h5 fw-bold mb-2">Exploración de Catálogo y Reserva Inmediata</h3>
                <p class="text-muted small mb-3">
                  Encuentra tu próxima lectura filtrando por título, autor o género. Si hay ejemplares disponibles, pulsa en <em>«Reservar ahora»</em> para apartarlo al instante bloqueando de forma segura 1 token de tu saldo durante 72 horas.
                </p>
                <div class="marco-captura mb-3" onclick="abrirModalCaptura('/assets/img/ayuda/usuario_catalogo.png', 'Catálogo de Libros y Reserva en 1 Clic')">
                  <img src="/assets/img/ayuda/usuario_catalogo.png" alt="Catálogo de Libros" class="img-fluid rounded-3 shadow-xs">
                  <div class="overlay-zoom"><i class="bi bi-zoom-in me-1"></i>Clic para ampliar</div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2">
                  <span class="small text-muted"><i class="bi bi-clock-history text-primary me-1"></i>72h garantizadas</span>
                  <a href="/catalogo" class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold">Ver Catálogo <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
              </div>
            </div>
          </div>

          <!-- Tarjeta 3: Mis Reservas y Código de Barras -->
          <div class="col-lg-6 item-ayuda" data-keywords="mis reservas codigo barras recogida mostrador comprobante cancelar">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-surface overflow-hidden">
              <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4 d-flex justify-content-between align-items-center">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">Recogida</span>
                <span class="text-muted small"><i class="bi bi-bookmark-check me-1"></i>/mis-reservas</span>
              </div>
              <div class="card-body px-4">
                <h3 class="h5 fw-bold mb-2">Mis Reservas y Código de Barras 1D</h3>
                <p class="text-muted small mb-3">
                  Presenta tu pantalla en el mostrador para retirar el libro sin esperas. Cada reserva incluye un <strong>código de barras óptico 1D</strong> de alta compatibilidad y la opción de imprimir tu resguardo o cancelar la reserva para liberar tu token.
                </p>
                <div class="marco-captura mb-3" onclick="abrirModalCaptura('/assets/img/ayuda/usuario_reservas.png', 'Mis Reservas con Código de Barras')">
                  <img src="/assets/img/ayuda/usuario_reservas.png" alt="Mis Reservas" class="img-fluid rounded-3 shadow-xs">
                  <div class="overlay-zoom"><i class="bi bi-zoom-in me-1"></i>Clic para ampliar</div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2">
                  <span class="small text-muted"><i class="bi bi-upc text-dark me-1"></i>Lectura rápida 1D</span>
                  <a href="/mis-reservas" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold">Ver mis Reservas <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
              </div>
            </div>
          </div>

          <!-- Tarjeta 4: Lista de Deseos (Avisos de Stock) -->
          <div class="col-lg-6 item-ayuda" data-keywords="wishlist lista deseos avisos notificaciones disponibilidad agotados">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-surface overflow-hidden">
              <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4 d-flex justify-content-between align-items-center">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">Lista de Deseos</span>
                <span class="text-muted small"><i class="bi bi-bell me-1"></i>/wishlist</span>
              </div>
              <div class="card-body px-4">
                <h3 class="h5 fw-bold mb-2">Lista de Deseos y Notificación de Stock</h3>
                <p class="text-muted small mb-3">
                  ¿El libro que buscas está agotado? Pulsa en <em>«Avisarme cuando esté disponible»</em>. En cuanto otro lector o la biblioteca añadan una nueva copia, recibirás una notificación directa en tu campana para reservarlo antes de que se agote.
                </p>
                <div class="marco-captura mb-3" onclick="abrirModalCaptura('/assets/img/ayuda/usuario_wishlist.png', 'Lista de Deseos y Alertas de Disponibilidad')">
                  <img src="/assets/img/ayuda/usuario_wishlist.png" alt="Lista de Deseos" class="img-fluid rounded-3 shadow-xs">
                  <div class="overlay-zoom"><i class="bi bi-zoom-in me-1"></i>Clic para ampliar</div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2">
                  <span class="small text-muted"><i class="bi bi-bell-fill text-warning me-1"></i>Aviso automático</span>
                  <a href="/wishlist" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold">Mi Lista de Deseos <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
              </div>
            </div>
          </div>

          <!-- Tarjeta 5: Historial y Exportación CSV -->
          <div class="col-lg-6 item-ayuda" data-keywords="historial lecturas libros aportados depositos transacciones exportar csv saldo">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-surface overflow-hidden">
              <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4 d-flex justify-content-between align-items-center">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">Mi Historial</span>
                <span class="text-muted small"><i class="bi bi-clock-history me-1"></i>/mi-historial</span>
              </div>
              <div class="card-body px-4">
                <h3 class="h5 fw-bold mb-2">Historial de Lecturas y Movimientos</h3>
                <p class="text-muted small mb-3">
                  Comprueba cada libro retirado, devuelto o donado, junto a la variación exacta de tus tokens. Puedes filtrar por tipo de movimiento, rango de fechas y descargar tu informe personal en formato CSV.
                </p>
                <div class="marco-captura mb-3" onclick="abrirModalCaptura('/assets/img/ayuda/usuario_historial.png', 'Historial de Libros y Movimientos')">
                  <img src="/assets/img/ayuda/usuario_historial.png" alt="Historial de Usuario" class="img-fluid rounded-3 shadow-xs">
                  <div class="overlay-zoom"><i class="bi bi-zoom-in me-1"></i>Clic para ampliar</div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2">
                  <span class="small text-muted"><i class="bi bi-file-earmark-excel text-success me-1"></i>Exportable a CSV</span>
                  <a href="/mi-historial" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-semibold">Ver Historial <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- ======================================================== -->
    <!-- 4. MÓDULOS DE VISITANTE / INVITADO (Públicos)             -->
    <!-- (Visible para INVITADO y como referencia para todos)     -->
    <!-- ======================================================== -->
    <?php if ($rolActual === 'INVITADO' || $rolActual === 'ADMIN'): ?>
      <div class="bloque-rol mb-5" id="seccion-invitado">
        <div class="d-flex align-items-center gap-2 pb-2 mb-4 border-bottom border-secondary-subtle">
          <span class="badge bg-secondary text-white p-2 rounded-3 fs-5"><i class="bi bi-compass"></i></span>
          <div>
            <h2 class="h4 fw-bold mb-0 text-secondary">Acceso Público y Primeros Pasos</h2>
            <p class="text-muted small mb-0">Exploración pública de libros, horarios de atención presencial y registro</p>
          </div>
        </div>

        <div class="row g-4">
          <!-- Tarjeta 1: Bienvenida e Intercambio Circular -->
          <div class="col-lg-6 item-ayuda" data-keywords="inicio bienvenida intercambio circular libros como funciona visitante">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-surface overflow-hidden">
              <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4 d-flex justify-content-between align-items-center">
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">Bienvenida</span>
                <span class="text-muted small"><i class="bi bi-house me-1"></i>/</span>
              </div>
              <div class="card-body px-4">
                <h3 class="h5 fw-bold mb-2">Economía Circular y Donación de Libros</h3>
                <p class="text-muted small mb-3">
                  BookSwap transforma libros leídos que ya no se usan en créditos de lectura. Trae tus ejemplares al mostrador, recibe tokens y llévate nuevas historias para seguir leyendo.
                </p>
                <div class="marco-captura mb-3" onclick="abrirModalCaptura('/assets/img/ayuda/guest_home.png', 'Página Principal de BookSwap')">
                  <img src="/assets/img/ayuda/guest_home.png" alt="Página de Inicio" class="img-fluid rounded-3 shadow-xs">
                  <div class="overlay-zoom"><i class="bi bi-zoom-in me-1"></i>Clic para ampliar</div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2">
                  <span class="small text-muted"><i class="bi bi-arrow-repeat text-primary me-1"></i>100% Gratuito</span>
                  <a href="/como-funciona" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-semibold">Cómo Funciona <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
              </div>
            </div>
          </div>

          <!-- Tarjeta 2: Consulta Pública de Fondos -->
          <div class="col-lg-6 item-ayuda" data-keywords="catalogo publico consulta busqueda titulos biblioteca visitante">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-surface overflow-hidden">
              <div class="card-header bg-transparent border-0 pt-3 pb-0 px-4 d-flex justify-content-between align-items-center">
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">Consulta Abierta</span>
                <span class="text-muted small"><i class="bi bi-journal-bookmark me-1"></i>/catalogo</span>
              </div>
              <div class="card-body px-4">
                <h3 class="h5 fw-bold mb-2">Consulta de Fondos Disponibles</h3>
                <p class="text-muted small mb-3">
                  Cualquier visitante puede consultar en directo las portadas, sinopsis y número de ejemplares físicos disponibles en el centro antes de acercarse al mostrador o registrarse.
                </p>
                <div class="marco-captura mb-3" onclick="abrirModalCaptura('/assets/img/ayuda/guest_catalogo.png', 'Consulta Pública del Catálogo')">
                  <img src="/assets/img/ayuda/guest_catalogo.png" alt="Catálogo Público" class="img-fluid rounded-3 shadow-xs">
                  <div class="overlay-zoom"><i class="bi bi-zoom-in me-1"></i>Clic para ampliar</div>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2">
                  <span class="small text-muted"><i class="bi bi-check2 text-success me-1"></i>Sin registro para mirar</span>
                  <a href="/catalogo" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-semibold">Ver Catálogo <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div>

  <!-- Estado vacío si la búsqueda no arroja resultados -->
  <div id="sinResultadosAyuda" class="text-center py-5 d-none">
    <div class="p-3 bg-secondary-subtle text-secondary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:70px;height:70px;">
      <i class="bi bi-search fs-2"></i>
    </div>
    <h3 class="h5 fw-bold">No se encontraron funcionalidades</h3>
    <p class="text-muted small mb-3">Intenta con otro término de búsqueda (ejemplo: reserva, saldo, mostrador, csv...)</p>
    <button class="btn btn-outline-primary btn-sm rounded-pill px-4" onclick="limpiarBusquedaAyuda()">Mostrar todo</button>
  </div>
</div>

<!-- Modal / Lightbox para ver capturas en alta resolución -->
<div class="modal fade" id="modalCapturaAyuda" tabindex="-1" aria-labelledby="tituloModalCaptura" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden bg-surface">
      <div class="modal-header border-bottom py-3 px-4">
        <h5 class="modal-title fw-bold" id="tituloModalCaptura">Captura de pantalla</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body p-2 text-center bg-dark-subtle">
        <img id="imgModalCaptura" src="" alt="Captura ampliada" class="img-fluid rounded-3 shadow-sm" style="max-height: 80vh; object-fit: contain;">
      </div>
      <div class="modal-footer border-top py-2 px-4 justify-content-between">
        <span class="small text-muted"><i class="bi bi-info-circle me-1"></i>Captura real del entorno de BookSwap</span>
        <button type="button" class="btn btn-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<style>
.marco-captura {
  position: relative;
  border-radius: 0.75rem;
  overflow: hidden;
  border: 1px solid rgba(0, 0, 0, 0.08);
  cursor: pointer;
  background-color: var(--bs-tertiary-bg, #f8f9fa);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.marco-captura:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}
.marco-captura img {
  display: block;
  width: 100%;
  height: 220px;
  object-fit: cover;
  object-position: top;
  transition: filter 0.2s ease;
}
.marco-captura:hover img {
  filter: brightness(0.92);
}
.overlay-zoom {
  position: absolute;
  bottom: 10px;
  right: 10px;
  background: rgba(15, 23, 42, 0.85);
  color: #fff;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  backdrop-filter: blur(4px);
  pointer-events: none;
}
</style>

<script>
function abrirModalCaptura(src, titulo) {
  const modalEl = document.getElementById('modalCapturaAyuda');
  document.getElementById('imgModalCaptura').src = src;
  document.getElementById('tituloModalCaptura').innerText = titulo;
  const modal = new bootstrap.Modal(modalEl);
  modal.show();
}

function filtrarSeccionAyuda(seccionId) {
  const bloques = document.querySelectorAll('.bloque-rol');
  const botones = document.querySelectorAll('#adminHelpFilter button');

  botones.forEach(btn => btn.classList.remove('active'));
  if (event && event.target) {
    event.target.classList.add('active');
  }

  if (seccionId === 'todos') {
    bloques.forEach(b => b.classList.remove('d-none'));
  } else {
    bloques.forEach(b => {
      if (b.id === seccionId) {
        b.classList.remove('d-none');
      } else {
        b.classList.add('d-none');
      }
    });
  }
}

function buscarEnAyuda() {
  const q = document.getElementById('helpSearchInput').value.trim().toLowerCase();
  const btnLimpiar = document.getElementById('btnLimpiarBusqueda');
  const items = document.querySelectorAll('.item-ayuda');
  const bloques = document.querySelectorAll('.bloque-rol');
  const sinResultados = document.getElementById('sinResultadosAyuda');

  if (q.length > 0) {
    btnLimpiar.style.display = 'block';
  } else {
    btnLimpiar.style.display = 'none';
  }

  let totalVisibles = 0;

  items.forEach(item => {
    const texto = (item.innerText + ' ' + (item.getAttribute('data-keywords') || '')).toLowerCase();
    if (texto.includes(q)) {
      item.style.display = '';
      totalVisibles++;
    } else {
      item.style.display = 'none';
    }
  });

  // Si un bloque no tiene ninguna tarjeta visible, ocultar el encabezado del bloque
  bloques.forEach(bloque => {
    const hijosVisibles = bloque.querySelectorAll('.item-ayuda:not([style*="display: none"])');
    if (hijosVisibles.length === 0) {
      bloque.style.display = 'none';
    } else {
      bloque.style.display = '';
    }
  });

  if (totalVisibles === 0) {
    sinResultados.classList.remove('d-none');
  } else {
    sinResultados.classList.add('d-none');
  }
}

function limpiarBusquedaAyuda() {
  document.getElementById('helpSearchInput').value = '';
  document.getElementById('btnLimpiarBusqueda').style.display = 'none';
  buscarEnAyuda();
}
</script>
