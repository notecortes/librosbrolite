<?php
/**
 * BookSwap · Layout base (CANÓNICO — el agente lo reutiliza en TODAS las páginas).
 *
 * CONTRATO DE VARIABLES (el router las prepara antes de require):
 *   $titulo      string  Título de la página (se concatena al nombre del centro)
 *   $contenido   string  HTML del body, renderizado por la vista hija con ob_start()
 *   $usuario     ?array  Sesión: ['id','nombre','rol_nombre','foto_url'] o null
 *   $config      array   Valores de configuracion (claves centro_*, etc.)
 *   $notif_count int     Notificaciones no leídas (campana)
 *   $csrf_token  string  Token CSRF para formularios/meta
 */
declare(strict_types=1);
require_once __DIR__ . '/../../helpers/funciones.php';

 $titulo      = $titulo      ?? 'Inicio';
 $usuario     = $usuario     ?? null;
 $config      = $config      ?? [];
 $notif_count = (int) ($notif_count ?? 0);
 $csrf_token  = $csrf_token  ?? '';
 $flash       = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
 $tiposFlash  = ['exito' => 'success', 'error' => 'danger', 'aviso' => 'warning', 'info' => 'info'];
 $nombreCentro = $config['centro_nombre'] ?: 'LibrosBro';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?> · <?= e($nombreCentro) ?></title>
  <meta name="description" content="LibrosBro — Intercambia libros, gana tokens y llénate de historias.">
  <?php if ($csrf_token !== ''): ?>
  <meta name="csrf-token" content="<?= e($csrf_token) ?>">
  <?php endif; ?>
  <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/assets/css/theme.css?v=3" rel="stylesheet">
  <script>
  /* Evita el "flash" de tema incorrecto: se aplica ANTES del primer pintado. */
  (function () {
    var t = null;
    try { t = localStorage.getItem('bs-theme'); } catch (e) {}
    if (t !== 'dark' && t !== 'light') {
      t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      try { localStorage.setItem('bs-theme', t); } catch (e) {}
    }
    document.documentElement.setAttribute('data-theme', t);
    document.documentElement.setAttribute('data-bs-theme', t);
  })();

  function toggleTemaLibrosBro() {
    var actual = document.documentElement.getAttribute('data-bs-theme') || document.documentElement.getAttribute('data-theme') || 'light';
    var nuevo = (actual === 'dark') ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', nuevo);
    document.documentElement.setAttribute('data-bs-theme', nuevo);
    if (document.body) {
      document.body.setAttribute('data-theme', nuevo);
      document.body.setAttribute('data-bs-theme', nuevo);
    }
    try { localStorage.setItem('bs-theme', nuevo); } catch (e) {}
    if (window.BS && typeof window.BS.setTema === 'function') {
      try { window.BS.setTema(nuevo, false); } catch (e) {}
    }
  }
  window.toggleTemaBookSwap = toggleTemaLibrosBro;
  </script>
</head>
<body>
  <a class="visually-hidden-focusable" href="#contenido">Saltar al contenido</a>

  <nav class="navbar navbar-expand-lg navbar-bookswap sticky-top">
    <div class="container-xxl">
      <?php $logoVer = @filemtime(dirname(__DIR__, 2) . '/public/assets/img/logo.svg') ?: 3; ?>
      <a class="navbar-brand d-flex align-items-center gap-2 fw-800" href="<?= $usuario ? '/dashboard' : '/' ?>">
        <img src="/assets/img/logo.svg?v=<?= $logoVer ?>" alt="LibrosBro" width="68" height="68" class="brand-logo">
        <span>LibrosBro</span>
      </a>
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navPrincipal"
              aria-controls="navPrincipal" aria-expanded="false" aria-label="Abrir menú">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navPrincipal">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-lg-1">
          <?php if (!$usuario): ?>
            <!-- INVITADO: Catálogo · Visítanos · Cómo funciona -->
            <li class="nav-item"><a class="nav-link" href="/catalogo"><i class="bi bi-journal-bookmark me-1"></i>Catálogo</a></li>
            <li class="nav-item"><a class="nav-link" href="/visitanos"><i class="bi bi-geo-alt me-1"></i>Visítanos</a></li>
            <li class="nav-item"><a class="nav-link" href="/como-funciona"><i class="bi bi-question-circle me-1"></i>Cómo funciona</a></li>
            <li class="nav-item"><a class="nav-link" href="/ayuda"><i class="bi bi-info-circle me-1"></i>Ayuda</a></li>
          <?php elseif ($usuario['rol_nombre'] === 'USUARIO'): ?>
            <!-- USUARIO: Catálogo · Mis reservas · Mi historial -->
            <li class="nav-item"><a class="nav-link" href="/catalogo"><i class="bi bi-journal-bookmark me-1"></i>Catálogo</a></li>
            <li class="nav-item"><a class="nav-link" href="/mis-reservas"><i class="bi bi-bookmark me-1"></i>Mis reservas</a></li>
            <li class="nav-item"><a class="nav-link" href="/mi-historial"><i class="bi bi-clock-history me-1"></i>Mi historial</a></li>
          <?php elseif ($usuario['rol_nombre'] === 'PERSONAL'): ?>
            <!-- PERSONAL: [Mostrador] como botón primario · Catálogo -->
            <li class="nav-item">
              <a class="btn btn-primary btn-sm my-auto me-1" href="/mostrador"><i class="bi bi-shop me-1"></i>Mostrador</a>
            </li>
            <li class="nav-item"><a class="nav-link" href="/catalogo"><i class="bi bi-journal-bookmark me-1"></i>Catálogo</a></li>
            <?php if (puede('config.editar') || puede('metricas.ver') || puede('roles.gestionar') || puede('usuarios.gestionar') || puede('csv.importar')): ?>
              <li class="nav-item"><a class="nav-link" href="/admin"><i class="bi bi-speedometer2 me-1"></i>Panel</a></li>
            <?php endif; ?>
          <?php elseif ($usuario['rol_nombre'] === 'ADMIN'): ?>
            <!-- ADMIN: [Mostrador] · Catálogo · Panel -->
            <li class="nav-item">
              <a class="btn btn-primary btn-sm my-auto me-1" href="/mostrador"><i class="bi bi-shop me-1"></i>Mostrador</a>
            </li>
            <li class="nav-item"><a class="nav-link" href="/catalogo"><i class="bi bi-journal-bookmark me-1"></i>Catálogo</a></li>
            <li class="nav-item"><a class="nav-link" href="/admin"><i class="bi bi-speedometer2 me-1"></i>Panel</a></li>
          <?php endif; ?>
        </ul>
        <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
          <li class="nav-item">
            <button class="btn btn-soft btn-sm" type="button" id="btn-toggle-theme" data-toggle-theme="1" onclick="toggleTemaLibrosBro();" aria-label="Cambiar tema claro/oscuro">
              <i class="bi bi-moon-stars icono-luna"></i><i class="bi bi-sun icono-sol"></i>
            </button>
          </li>
          <?php if ($usuario): ?>
            <?php if ($usuario['rol_nombre'] === 'USUARIO'): ?>
              <li class="nav-item">
                <a class="saldo-pill" href="/mi-cuenta" title="<?= (int) ($tokensComprometidos ?? 0) ?> 🪙 comprometidos en <?= (int) ($numReservasActivas ?? 0) ?> reserva(s) activa(s)" data-bs-toggle="tooltip" data-bs-placement="bottom">
                  <i class="bi bi-coin"></i><span id="saldo-nav"><?= (int) ($saldo ?? 0) ?></span>
                </a>
              </li>
            <?php endif; ?>
            <li class="nav-item">
              <a class="campana d-inline-block p-1" href="/notificaciones" aria-label="Notificaciones (<?= $notif_count ?> sin leer)">
                <i class="bi bi-bell"></i>
                <?php if ($notif_count > 0): ?><span class="campana-badge"><?= $notif_count > 99 ? '99+' : $notif_count ?></span><?php endif; ?>
              </a>
            </li>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                <?php if (!empty($usuario['foto_url'])): ?>
                  <img class="avatar" src="<?= e($usuario['foto_url']) ?>" alt="" width="32" height="32" loading="lazy">
                <?php else: ?>
                  <span class="avatar-iniciales"><?= e(iniciales($usuario['nombre'])) ?></span>
                <?php endif; ?>
                <span class="d-none d-lg-inline"><?= e($usuario['nombre']) ?></span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end shadow-sm rounded-3">
                <?php if ($usuario['rol_nombre'] === 'ADMIN'): ?>
                  <li><h6 class="dropdown-header text-uppercase small">Administración</h6></li>
                  <li><a class="dropdown-item fw-semibold" href="/admin"><i class="bi bi-speedometer2 me-2 text-primary"></i>Panel de Administración</a></li>
                  <li><a class="dropdown-item fw-semibold" href="/mostrador"><i class="bi bi-shop me-2 text-warning"></i>Modo Mostrador</a></li>
                  <li><a class="dropdown-item" href="/admin/libros/nuevo"><i class="bi bi-upc-scan me-2"></i>Añadir Libro por ISBN</a></li>
                  <?php if (puede('csv.importar')): ?>
                  <li><a class="dropdown-item" href="/admin/csv"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Importación CSV</a></li>
                  <?php endif; ?>
                  <?php if (puede('roles.gestionar')): ?>
                  <li><a class="dropdown-item" href="/admin/roles"><i class="bi bi-shield-lock me-2 text-danger"></i>Matriz de Permisos</a></li>
                  <?php endif; ?>
                  <li><hr class="dropdown-divider"></li>
                <?php elseif ($usuario['rol_nombre'] === 'PERSONAL'): ?>
                  <li><h6 class="dropdown-header text-uppercase small">Personal</h6></li>
                  <li><a class="dropdown-item fw-semibold" href="/mostrador"><i class="bi bi-shop me-2 text-warning"></i>Modo Mostrador</a></li>
                  <li><a class="dropdown-item" href="/admin/libros/nuevo"><i class="bi bi-upc-scan me-2"></i>Añadir Libro por ISBN</a></li>
                  <?php if (puede('csv.importar')): ?>
                  <li><a class="dropdown-item" href="/admin/csv"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Importación CSV</a></li>
                  <?php endif; ?>
                  <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
                <li><h6 class="dropdown-header text-uppercase small">Mi Cuenta</h6></li>
                <li><a class="dropdown-item" href="/dashboard"><i class="bi bi-person me-2"></i>Mi panel y tokens</a></li>
                <li><a class="dropdown-item" href="/mi-historial"><i class="bi bi-clock-history me-2"></i>Mi historial y movimientos</a></li>
                <li><a class="dropdown-item" href="/mis-reservas"><i class="bi bi-bookmark me-2"></i>Mis reservas</a></li>
                <li><a class="dropdown-item" href="/wishlist"><i class="bi bi-bell me-2"></i>Lista de deseos (avisos)</a></li>
                <li><a class="dropdown-item" href="/cambiar-password"><i class="bi bi-key me-2 text-primary"></i>Cambiar contraseña</a></li>
                <li><a class="dropdown-item" href="/ayuda"><i class="bi bi-question-circle me-2 text-info"></i>Centro de Ayuda</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                  <form method="post" action="/logout">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                    <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</button>
                  </form>
                </li>
              </ul>
            </li>
          <?php else: ?>
            <li class="nav-item"><a class="btn btn-soft btn-sm" href="/login">Entrar</a></li>
            <li class="nav-item"><a class="btn btn-primary btn-sm" href="/registro">Crear cuenta</a></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>

  <main id="contenido" class="container-xxl py-4">
    <?php if ($flash): ?>
      <div class="alert alert-<?= e($tiposFlash[$flash['tipo']] ?? 'info') ?> alert-dismissible fade show" role="alert">
        <?= e($flash['mensaje']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
      </div>
    <?php endif; ?>
    <?= $contenido ?>
  </main>

  <?php require __DIR__ . '/../partials/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/assets/js/app.js?v=2" defer></script>
</body>
</html>