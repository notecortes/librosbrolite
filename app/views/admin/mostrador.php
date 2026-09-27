<?php
/**
 * BookSwap · Modo Mostrador (v4.4).
 *
 * Pantalla unificada de atención presencial en biblioteca (§3.1, §3.2):
 * 1) 🚀 ENTREGA DIRECTA (sin reserva previa, 3 pasos visibles) - Acción principal
 * 2) 📦 ENTREGAR RESERVA (código / QR / Lector)
 * 3) ➕ REGISTRAR DEPÓSITO (admitir o rechazar libros)
 * 4) 📚 ALTA DE CATÁLOGO (alta al vuelo por ISBN/manual o reservar)
 */
declare(strict_types=1);

$usuario = $usuario ?? [];
$config = $config ?? [];
$reservaSeleccionada = $reservaSeleccionada ?? null;
$librosCatalogo = $librosCatalogo ?? [];
$usuariosSistema = $usuariosSistema ?? [];
$ejemplaresDisponibles = $ejemplaresDisponibles ?? [];
$reservasPendientes = $reservasPendientes ?? [];
$costeLibro = (int) ($config['coste_libro'] ?? 1);
$bonoDeposito = (int) ($config['bono_deposito'] ?? 1);

// Determinar pestaña inicial: si viene un código de reserva o tab=reserva, abrir pestaña 2; en caso contrario, directa si tiene permiso o reserva
$puedeDirecta = puede('entrega.confirmar');
$tabActivaInicial = (!empty($_GET['codigo']) || ($_GET['tab'] ?? '') === 'reserva') ? 'reserva' : ($puedeDirecta ? 'directa' : 'reserva');
?>

<div class="container-xxl py-4">
  <!-- Encabezado de Modo Mostrador -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 border-bottom pb-3">
    <div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary px-3 py-2 fs-6 rounded-pill">
          <i class="bi bi-shop me-1"></i> Modo Mostrador
        </span>
        <h1 class="h3 fw-bold mb-0">Atención en Sala y Caja</h1>
      </div>
      <p class="text-muted small mb-0 mt-1">
        Gestión presencial de entregas directas, reservas por código, depósitos y catálogo de biblioteca.
      </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <span class="badge bg-light text-body border p-2 d-flex align-items-center gap-2 rounded-3">
        <i class="bi bi-coin text-warning fs-5"></i>
        <span>Coste libro: <strong><?= $costeLibro ?> token<?= $costeLibro !== 1 ? 's' : '' ?></strong></span>
      </span>
      <span class="badge bg-light text-body border p-2 d-flex align-items-center gap-2 rounded-3">
        <i class="bi bi-gift text-success fs-5"></i>
        <span>Bono depósito: <strong>+<?= $bonoDeposito ?> token<?= $bonoDeposito !== 1 ? 's' : '' ?></strong></span>
      </span>
    </div>
  </div>

  <!-- ================================================================= -->
  <!-- HUB DE ESCÁNER UNIVERSAL 1D (§2.2)                                -->
  <!-- ================================================================= -->
  <div class="card border border-success shadow-sm rounded-4 p-3 p-md-4 mb-4 bg-surface position-relative overflow-hidden" id="hub-escaner-universal">
    <div class="row align-items-center g-3">
      <div class="col-12 col-lg-7">
        <form id="form-escaner-universal" method="POST" action="/mostrador/escanear" onsubmit="return procesarEnvioEscaner(event);">
          <?= csrf_campo() ?>
          <input type="hidden" name="web_submit" value="1">
          <label for="escaner-universal" class="form-label d-flex align-items-center justify-content-between mb-2">
            <span class="fw-bold fs-6 text-uppercase text-body-secondary d-flex align-items-center gap-2">
              <span id="indicador-escaner-listo" class="spinner-grow spinner-grow-sm text-success" role="status" aria-hidden="true"></span>
              <i class="bi bi-upc-scan fs-5 text-success"></i> Escáner Universal de Mostrador 1D
            </span>
            <span class="small text-muted d-none d-sm-inline">
              Atajo: Pulsa <kbd class="bg-secondary text-white">F2</kbd> o <kbd class="bg-secondary text-white">/</kbd> para enfocar
            </span>
          </label>
          <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden border">
            <span class="input-group-text bg-white border-0 text-success ps-3">
              <i class="bi bi-barcode fs-3" id="icono-pulso-escaner"></i>
            </span>
            <input type="text"
                   name="valor"
                   id="escaner-universal"
                   class="form-control border-0 font-monospace fs-5 fw-bold"
                   placeholder="Escanea código de reserva o ISBN del libro..."
                   autocomplete="off"
                   autofocus
                   aria-label="Escáner universal">
            <button class="btn btn-success fw-bold px-4 d-flex align-items-center gap-2" type="submit" id="btn-escaner-disparar">
              <i class="bi bi-arrow-return-left"></i>
              <span class="d-none d-md-inline">Procesar</span>
            </button>
          </div>
        </form>
      </div>
      <div class="col-12 col-lg-5">
        <div class="p-3 bg-light rounded-3 border d-flex flex-column gap-1 small text-muted">
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary-subtle text-primary fw-bold font-monospace">RES-...</span>
            <span>Detecta y prepara la entrega de la reserva</span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-warning-subtle text-warning-emphasis fw-bold font-monospace">ISBN 10/13</span>
            <span>Localiza libro disponible para entrega directa</span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-info-subtle text-info fw-bold font-monospace">Usuario</span>
            <span>Selecciona al lector y carga su saldo disponible</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Contenedor de Feedback Dinámico del Escaneo -->
    <div id="resultado-escaneo" class="mt-3 d-none">
      <div class="alert mb-0 d-flex align-items-center justify-content-between p-3 rounded-3" id="alert-resultado-escaneo">
        <div class="d-flex align-items-center gap-3">
          <div id="icon-resultado-escaneo" class="fs-4"></div>
          <div>
            <div class="fw-bold fs-6" id="titulo-resultado-escaneo"></div>
            <div class="small" id="desc-resultado-escaneo"></div>
          </div>
        </div>
        <div id="accion-resultado-escaneo"></div>
      </div>
    </div>
  </div>

  <!-- NAVEGACIÓN PRINCIPAL: Operaciones agrupadas en SALIDAS y ENTRADAS (§3.1) -->
  <div class="row g-3 mb-4" id="selector-operaciones">
    <!-- Grupo SALIDAS: Atención y entregas a lectores -->
    <div class="col-12 col-xl-6">
      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="badge bg-primary text-white px-2 py-1"><i class="bi bi-box-arrow-up-right me-1"></i>SALIDAS</span>
        <span class="small fw-bold text-muted text-uppercase">Entregas y atención al lector</span>
      </div>
      <div class="row g-2">
        <!-- 1) ENTREGA DIRECTA (Acción Principal Resaltada) -->
        <?php if (puede('entrega.directa') || puede('entrega.confirmar')): ?>
        <div class="col-6" id="card-operacion-directa">
          <button type="button"
                  class="btn w-100 p-3 rounded-4 text-start h-100 transition-all border-2 <?= $tabActivaInicial === 'directa' ? 'btn-primary shadow border-primary text-white' : 'btn-outline-primary bg-surface' ?>"
                  id="btn-tab-directa"
                  onclick="activarOperacionMostrador('directa');">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="fs-4">🚀</span>
              <span class="badge <?= $tabActivaInicial === 'directa' ? 'bg-white text-primary' : 'bg-primary text-white' ?> rounded-pill small">Principal</span>
            </div>
            <div class="fw-bold fs-6 mb-1">1. Entrega Directa</div>
            <div class="small opacity-75">Sin reserva previa (3 pasos)</div>
          </button>
        </div>
        <?php endif; ?>

        <!-- 2) ENTREGAR RESERVA -->
        <?php if (puede('entrega.confirmar')): ?>
        <div class="col-6" id="card-operacion-reserva">
          <button type="button"
                  class="btn w-100 p-3 rounded-4 text-start h-100 transition-all border-2 <?= $tabActivaInicial === 'reserva' ? 'btn-primary shadow border-primary text-white' : 'btn-outline-secondary bg-surface' ?>"
                  id="btn-tab-reserva"
                  onclick="activarOperacionMostrador('reserva');">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="fs-4">📦</span>
              <span class="badge bg-secondary-subtle text-secondary rounded-pill small">Código 128</span>
            </div>
            <div class="fw-bold fs-6 mb-1">2. Entregar Reserva</div>
            <div class="small opacity-75">Canje con código alfanumérico</div>
          </button>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Grupo ENTRADAS: Incorporación de copias y depósitos -->
    <div class="col-12 col-xl-6">
      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="badge bg-success text-white px-2 py-1"><i class="bi bi-box-arrow-in-down me-1"></i>ENTRADAS</span>
        <span class="small fw-bold text-muted text-uppercase">Recepción de copias y depósitos</span>
      </div>
      <div class="row g-2">
        <!-- 3) AÑADIR COPIAS / REGISTRAR DEPÓSITO -->
        <?php if (puede('deposito.registrar')): ?>
        <div class="col-6" id="card-operacion-deposito">
          <a href="/libros/entrada"
             class="btn w-100 p-3 rounded-4 text-start h-100 transition-all border-2 btn-outline-success bg-surface d-block text-decoration-none text-body"
             id="btn-tab-deposito">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="fs-4">📥</span>
              <span class="badge bg-success-subtle text-success rounded-pill small">+<?= $bonoDeposito ?> token / stock</span>
            </div>
            <div class="fw-bold fs-6 mb-1">3. Registrar Depósito</div>
            <div class="small opacity-75">Añadir copias / Registrar Depósito</div>
          </a>
        </div>
        <?php endif; ?>

        <!-- 4) ALTA DE CATÁLOGO -->
        <?php if (puede('catalogo.editar')): ?>
        <div class="col-6" id="card-operacion-catalogo">
          <button type="button"
                  class="btn w-100 p-3 rounded-4 text-start h-100 transition-all border-2 btn-outline-secondary bg-surface"
                  id="btn-tab-catalogo"
                  onclick="activarOperacionMostrador('catalogo');">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="fs-4">📚</span>
              <span class="badge bg-info-subtle text-info-emphasis rounded-pill small">Al vuelo</span>
            </div>
            <div class="fw-bold fs-6 mb-1">4. Alta de Catálogo</div>
            <div class="small opacity-75">ISBN, CSV o nuevo título</div>
          </button>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ================================================================= -->
  <!-- HOY EN EL MOSTRADOR (Fase 18 §3.4)                                -->
  <!-- ================================================================= -->
  <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-surface" id="seccion-hoy-mostrador">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="h5 fw-bold mb-0">
        <i class="bi bi-calendar-event text-primary me-2"></i>Hoy en el mostrador
      </h2>
      <span class="badge bg-primary-subtle text-primary fw-bold">Jornada actual</span>
    </div>
    <div class="row g-3">
      <div class="col-md-6 col-lg-3">
        <div class="p-3 rounded-4 bg-surface-2 border h-100">
          <div class="d-flex align-items-center justify-content-between mb-1">
            <span class="small text-muted fw-bold text-uppercase">Entregas de hoy</span>
            <span class="fs-4">📦</span>
          </div>
          <div class="display-6 fw-800 text-primary mb-0"><?= (int) ($entregasHoy ?? 0) ?></div>
        </div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="p-3 rounded-4 bg-surface-2 border h-100">
          <div class="d-flex align-items-center justify-content-between mb-1">
            <span class="small text-muted fw-bold text-uppercase">Depósitos de hoy</span>
            <span class="fs-4">➕</span>
          </div>
          <div class="display-6 fw-800 text-success mb-0"><?= (int) ($depositosHoy ?? 0) ?></div>
        </div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="p-3 rounded-4 bg-surface-2 border h-100">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="small text-muted fw-bold text-uppercase">Reservas que expiran hoy</span>
            <span class="badge bg-danger-subtle text-danger rounded-pill fw-bold">
              <?= count($reservasExpiranHoyList ?? []) ?> activas
            </span>
          </div>
          <?php if (empty($reservasExpiranHoyList)): ?>
            <p class="small text-muted mb-0">No hay reservas con vencimiento en el día de hoy.</p>
          <?php else: ?>
            <div class="list-group list-group-flush small" style="max-height: 120px; overflow-y: auto;">
              <?php foreach ($reservasExpiranHoyList as $rExp): ?>
                <div class="list-group-item bg-transparent px-0 py-1 d-flex justify-content-between align-items-center">
                  <div>
                    <span class="font-monospace fw-bold me-1 text-primary"><?= e($rExp['codigo']) ?></span>
                    <span class="text-truncate d-inline-block align-bottom" style="max-width: 180px;"><?= e($rExp['titulo']) ?></span>
                    <span class="text-muted">(<?= e($rExp['usuario_nombre']) ?>)</span>
                  </div>
                  <a href="/mostrador?codigo=<?= urlencode((string) $rExp['codigo']) ?>" class="btn btn-xs btn-outline-primary rounded-pill py-0 px-2 fw-semibold">
                    Cargar
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ================================================================= -->
  <!-- SECCIÓN 1: 🚀 ENTREGA DIRECTA (Fase 15 · §3.2)                     -->
  <!-- ================================================================= -->
  <?php if ($puedeDirecta): ?>
  <div id="sec-entrega-directa" class="operacion-panel <?= $tabActivaInicial === 'directa' ? '' : 'd-none' ?>">
    <div class="card border border-primary shadow-sm rounded-4 mb-4 overflow-hidden">
      <div class="card-header bg-primary-subtle py-3 px-4 d-flex justify-content-between align-items-center">
        <div>
          <h2 class="h5 fw-bold mb-0 text-primary-emphasis">
            🚀 1. Entrega Directa Rápida (Sin Reserva Previa)
          </h2>
          <div class="small text-muted">
            Flujo de 3 pasos en una pantalla: selecciona el libro disponible, el usuario y la forma de cobro.
          </div>
        </div>
        <span class="badge bg-primary text-white rounded-pill px-3 py-1">Atómico en 1 paso</span>
      </div>

      <div class="card-body p-4">
        <!-- Formulario Atómico de Entrega Directa -->
        <form method="POST" action="/mostrador/entrega-directa" id="form-entrega-directa">
          <?= csrf_campo() ?>
          <input type="hidden" name="retorno" value="/mostrador">
          <input type="hidden" name="ejemplar_id" id="input-directa-ejemplar-id" value="">
          <input type="hidden" name="usuario_id" id="input-directa-usuario-id" value="">
          <input type="hidden" name="metodo_pago" id="input-directa-metodo-pago" value="tokens">

          <div class="row g-4">
            <!-- PASO 1 · LIBRO (Buscador con autocompletado copias disponibles) -->
            <div class="col-12 col-xl-4">
              <div class="card border rounded-4 h-100 p-3 bg-light shadow-2xs">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <h3 class="h6 fw-bold mb-0 text-uppercase text-primary" style="letter-spacing: 0.5px;">
                    <span class="badge bg-primary rounded-circle me-1">1</span> Elegir Libro Disponible
                  </h3>
                  <span class="badge bg-white border text-muted small" id="badge-contador-copias">
                    <?= count($ejemplaresDisponibles) ?> copias
                  </span>
                </div>
                <p class="small text-muted mb-3">Busca por título, autor o ISBN entre los ejemplares físicos disponibles.</p>

                <!-- Input de búsqueda en vivo -->
                <div class="input-group input-group-sm mb-2">
                  <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                  <input type="text"
                         id="buscar-copia-input"
                         class="form-control"
                         placeholder="Escribe título, autor o ISBN..."
                         autocomplete="off"
                         oninput="filtrarCopiasDisponibles();">
                </div>

                <!-- Tarjeta de libro seleccionado -->
                <div id="libro-seleccionado-card" class="d-none border border-success bg-success-subtle rounded-3 p-3 mb-2">
                  <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill text-success fs-4"></i>
                    <div class="flex-grow-1 overflow-hidden">
                      <div class="fw-bold text-truncate" id="libro-sel-titulo"></div>
                      <div class="small text-muted text-truncate" id="libro-sel-autor"></div>
                      <div class="small mt-1">
                        <span class="badge bg-white border text-body" id="libro-sel-condicion"></span>
                        <span class="badge bg-white border text-muted" id="libro-sel-ubicacion"></span>
                        <span class="badge bg-success text-white" id="libro-sel-id"></span>
                      </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deseleccionarCopia();" title="Cambiar libro">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </div>
                </div>

                <!-- Lista de resultados de ejemplares -->
                <div id="lista-copias-disponibles" class="overflow-auto border rounded-3 bg-white p-1" style="max-height: 280px;">
                  <?php if (empty($ejemplaresDisponibles)): ?>
                    <div class="text-center py-4 text-muted small">
                      <i class="bi bi-inbox fs-3 d-block mb-1"></i> No hay ejemplares en estado 'disponible'.
                    </div>
                  <?php else: ?>
                    <?php foreach ($ejemplaresDisponibles as $ej): ?>
                      <div class="item-copia p-2 border-bottom cursor-pointer hover-bg rounded-2 d-flex align-items-center gap-2"
                           data-id="<?= (int) $ej['id'] ?>"
                           data-libro-id="<?= (int) $ej['libro_id'] ?>"
                           data-titulo="<?= e($ej['titulo']) ?>"
                           data-autor="<?= e($ej['autor']) ?>"
                           data-condicion="<?= e($ej['condicion']) ?>"
                           data-ubicacion="<?= e($ej['ubicacion']) ?>"
                           data-isbn="<?= e($ej['isbn13'] ?? '') ?>"
                           onclick="seleccionarCopia(<?= (int) $ej['id'] ?>, '<?= e(addslashes($ej['titulo'])) ?>', '<?= e(addslashes($ej['autor'])) ?>', '<?= e($ej['condicion']) ?>', '<?= e($ej['ubicacion']) ?>');">
                        <div class="flex-grow-1 min-w-0">
                          <div class="fw-semibold text-truncate small"><?= e($ej['titulo']) ?></div>
                          <div class="text-muted" style="font-size: 0.75rem;"><?= e($ej['autor']) ?></div>
                          <div class="d-flex gap-1 mt-1">
                            <span class="badge bg-light text-dark border py-0 px-1" style="font-size: 0.65rem;"><?= e($ej['condicion']) ?></span>
                            <span class="badge bg-light text-muted border py-0 px-1" style="font-size: 0.65rem;"><?= e($ej['ubicacion']) ?></span>
                            <span class="badge bg-primary-subtle text-primary py-0 px-1" style="font-size: 0.65rem;">#<?= (int) $ej['id'] ?></span>
                          </div>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 small">Elegir</button>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- PASO 2 · USUARIO (Buscador por nombre o email) -->
            <div class="col-12 col-xl-4">
              <div class="card border rounded-4 h-100 p-3 bg-light shadow-2xs">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <h3 class="h6 fw-bold mb-0 text-uppercase text-primary" style="letter-spacing: 0.5px;">
                    <span class="badge bg-primary rounded-circle me-1">2</span> Elegir Lector Receptor
                  </h3>
                  <span class="badge bg-white border text-muted small" id="badge-contador-usuarios">
                    <?= count($usuariosSistema) ?> socios
                  </span>
                </div>
                <p class="small text-muted mb-3">Busca por nombre o email del lector receptor.</p>

                <!-- Input de búsqueda de usuario -->
                <div class="input-group input-group-sm mb-2">
                  <span class="input-group-text bg-white"><i class="bi bi-person-search text-muted"></i></span>
                  <input type="text"
                         id="buscar-usuario-input"
                         class="form-control"
                         placeholder="Buscar por nombre o email..."
                         autocomplete="off"
                         oninput="filtrarUsuariosSistema();">
                </div>

                <!-- Tarjeta de usuario seleccionado -->
                <div id="usuario-seleccionado-card" class="d-none border border-success bg-success-subtle rounded-3 p-3 mb-2">
                  <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-person-check-fill text-success fs-4"></i>
                    <div class="flex-grow-1 overflow-hidden">
                      <div class="fw-bold text-truncate" id="usuario-sel-nombre"></div>
                      <div class="small text-muted text-truncate" id="usuario-sel-email"></div>
                      <div class="d-flex gap-2 align-items-center mt-1">
                        
                        <span class="badge bg-warning-subtle text-warning-emphasis border" id="usuario-sel-saldo"></span>
                      </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deseleccionarUsuario();" title="Cambiar lector">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  </div>
                </div>

                <!-- Alerta de Reserva Activa Existente (§1.7) -->
                <div id="alerta-reserva-activa" class="alert alert-warning d-none p-2 mb-2 rounded-3 small">
                  <div class="d-flex align-items-start gap-2">
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-5 flex-shrink-0"></i>
                    <div class="flex-grow-1">
                      <strong class="d-block mb-1">¡Reserva activa encontrada!</strong>
                      <span id="texto-alerta-reserva">El usuario ya tiene una reserva activa para esta copia.</span>
                      <div class="mt-2">
                        <a href="#" id="btn-ir-a-reserva" class="btn btn-warning btn-sm fw-bold">
                          <i class="bi bi-arrow-right-circle me-1"></i>Ir a entregar su reserva
                        </a>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Lista de resultados de usuarios -->
                <div id="lista-usuarios-sistema" class="overflow-auto border rounded-3 bg-white p-1" style="max-height: 280px;">
                  <?php foreach ($usuariosSistema as $u): ?>
                    <?php
                    $saldoU = (int) ($u['saldo'] ?? 0);
                    ?>
                    <div class="item-usuario p-2 border-bottom rounded-2 d-flex align-items-center gap-2 cursor-pointer hover-bg"
                         data-id="<?= (int) $u['id'] ?>"
                         data-nombre="<?= e($u['nombre']) ?>"
                         data-email="<?= e($u['email']) ?>"
                         data-saldo="<?= $saldoU ?>"
                         data-elegible="1"
                         onclick="seleccionarUsuario(<?= (int) $u['id'] ?>, '<?= e(addslashes($u['nombre'])) ?>', '<?= e(addslashes($u['email'])) ?>', <?= $saldoU ?>);">
                      <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate small"><?= e($u['nombre']) ?></div>
                        <div class="text-muted" style="font-size: 0.75rem;"><?= e($u['email']) ?></div>
                        <div class="d-flex gap-2 align-items-center mt-1">
                          <span class="badge bg-warning-subtle text-warning-emphasis py-0 px-1" style="font-size: 0.65rem;">🪙 <?= $saldoU ?></span>
                        </div>
                      </div>
                      <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 small">Elegir</button>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <!-- PASO 3 · COBRO Y CONFIRMACIÓN -->
            <div class="col-12 col-xl-4">
              <div class="card border rounded-4 h-100 p-3 bg-light shadow-2xs d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <h3 class="h6 fw-bold mb-0 text-uppercase text-primary" style="letter-spacing: 0.5px;">
                    <span class="badge bg-primary rounded-circle me-1">3</span> Forma de Cobro
                  </h3>
                  <span class="badge bg-primary-subtle text-primary small">Liquidación</span>
                </div>
                <p class="small text-muted mb-3">Elige cobrar con saldo de tokens o registrar intercambio con libro aportado.</p>

                <!-- Botones Grandes de Elección de Cobro (§3.2) -->
                <div class="row g-2 mb-3">
                  <div class="col-6">
                    <button type="button"
                            id="btn-metodo-tokens"
                            class="btn btn-warning w-100 p-2 text-start h-100 border border-2 border-warning active shadow-xs"
                            onclick="seleccionarMetodoCobro('tokens');">
                      <div class="fw-bold small mb-1">🪙 Con Tokens</div>
                      <div class="small opacity-75" style="font-size: 0.75rem;">-<?= $costeLibro ?> token del saldo</div>
                    </button>
                  </div>
                  <div class="col-6">
                    <button type="button"
                            id="btn-metodo-libro"
                            class="btn btn-outline-success w-100 p-2 text-start h-100 border border-2"
                            onclick="seleccionarMetodoCobro('libro');">
                      <div class="fw-bold small mb-1">📚 Con Libro</div>
                      <div class="small opacity-75" style="font-size: 0.75rem;">+<?= $bonoDeposito ?> bono / -<?= $costeLibro ?> retiro</div>
                    </button>
                  </div>
                </div>

                <!-- Formulario Inline de Libro Aportado (si método es 'libro') -->
                <div id="panel-libro-aportado" class="d-none border rounded-3 p-2 bg-white mb-3">
                  <label class="form-label small fw-bold mb-1">Libro aportado por el socio:</label>
                  <select name="libro_depositado_id" id="select-libro-depositado" class="form-select form-select-sm mb-2">
                    <option value="">-- Seleccionar libro del catálogo --</option>
                    <?php foreach ($librosCatalogo as $l): ?>
                      <option value="<?= (int) $l['id'] ?>"><?= e($l['titulo']) ?> (<?= e($l['autor']) ?>)</option>
                    <?php endforeach; ?>
                  </select>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small text-muted" style="font-size: 0.75rem;">¿No está catalogado?</span>
                    <a href="/admin/libros/nuevo?retorno=/mostrador" target="_blank" class="small fw-semibold text-decoration-none">
                      Alta al vuelo <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                  </div>

                  <label class="form-label small fw-bold mb-1">Condición física:</label>
                  <select name="condicion_libro_depositado" class="form-select form-select-sm">
                    <option value="bueno" selected>Bueno (estándar)</option>
                    <option value="nuevo">Nuevo / Como nuevo</option>
                    <option value="regular">Regular / Con marcas</option>
                  </select>
                </div>

                <!-- Mensaje de saldo insuficiente para tokens -->
                <div id="alerta-saldo-insuficiente" class="alert alert-danger d-none p-2 mb-3 rounded-3 small">
                  <i class="bi bi-x-circle-fill me-1"></i>
                  <span id="texto-saldo-insuficiente">Saldo insuficiente para cobrar en tokens.</span>
                </div>

                <!-- Resumen final de la operación -->
                <div class="card p-3 rounded-3 bg-white border mb-3 mt-auto">
                  <div class="small fw-bold text-muted text-uppercase mb-2" style="letter-spacing: 0.5px; font-size: 0.7rem;">Resumen de la entrega</div>
                  <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Copia:</span>
                    <span class="fw-semibold text-truncate text-end ms-2" id="resumen-copia">No seleccionada</span>
                  </div>
                  <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Receptor:</span>
                    <span class="fw-semibold text-truncate text-end ms-2" id="resumen-lector">No seleccionado</span>
                  </div>
                  <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Forma de cobro:</span>
                    <span class="fw-semibold" id="resumen-metodo">Tokens</span>
                  </div>
                  <div class="d-flex justify-content-between small border-top pt-2 mt-2">
                    <span class="fw-bold">Saldo resultante:</span>
                    <span class="fw-bold text-primary" id="resumen-saldo-final">--</span>
                  </div>
                </div>

                <!-- Botón de Confirmación Final -->
                <button type="submit"
                        id="btn-confirmar-entrega-directa"
                        class="btn btn-primary w-100 py-2 fw-bold shadow-sm"
                        disabled>
                  <i class="bi bi-check2-circle me-1"></i>Confirmar Entrega Directa
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ================================================================= -->
  <!-- SECCIÓN 2: 📦 ENTREGAR RESERVA (Código / QR)                       -->
  <!-- ================================================================= -->
  <div id="sec-entregar-reserva" class="operacion-panel <?= $tabActivaInicial === 'reserva' ? '' : 'd-none' ?>">
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
        <h2 class="h5 fw-bold mb-1">
          <i class="bi bi-qr-code-scan text-primary me-2"></i>2. Entregar Reserva Existente
        </h2>
        <p class="text-muted small mb-0">Introduce o escanea el código de recogida, o busca por el email del lector.</p>
      </div>
      <div class="card-body px-4 pb-4">
        <!-- Formulario de búsqueda de reserva -->
        <form method="GET" action="/mostrador" class="mb-4">
          <div class="input-group input-group-lg">
            <span class="input-group-text bg-white border-end-0">
              <i class="bi bi-search text-muted"></i>
            </span>
            <input type="text"
                   name="codigo"
                   id="buscar-reserva-input"
                   class="form-control border-start-0"
                   placeholder="Ej: RES-260923-A1B2, escáner QR o email..."
                   value="<?= e($_GET['codigo'] ?? '') ?>"
                   autofocus>
            <button class="btn btn-primary px-4 fw-bold" type="submit">
              Buscar Reserva
            </button>
          </div>
        </form>

        <?php if ($reservaSeleccionada): ?>
          <!-- Ficha de la reserva encontrada -->
          <div class="border rounded-4 p-4 bg-light shadow-xs position-relative overflow-hidden">
            <?php if (!empty($reservaSeleccionada['ha_expirado'])): ?>
              <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                <div>
                  <strong>¡Atención! Esta reserva venció el <?= e($reservaSeleccionada['fecha_limite']) ?>.</strong>
                  <div class="small">El plazo de recogida ha expirado. No es posible entregarla.</div>
                </div>
              </div>
            <?php endif; ?>

            <div class="row g-3 align-items-center">
              <div class="col-auto">
                <div style="width: 80px;" class="ratio ratio-2x3 rounded-2 shadow-xs overflow-hidden bg-white border">
                  <?php if (!empty($reservaSeleccionada['portada_url'])): ?>
                    <img src="<?= e($reservaSeleccionada['portada_url']) ?>" alt="Portada" width="80" height="120" loading="lazy" class="object-fit-cover w-100 h-100">
                  <?php else: ?>
                    <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                      <i class="bi bi-book fs-3"></i>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="col">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="badge bg-primary rounded-pill font-monospace"><?= e($reservaSeleccionada['codigo']) ?></span>
                  <span class="badge bg-<?= $reservaSeleccionada['estado'] === 'activa' ? 'success' : 'secondary' ?>-subtle text-<?= $reservaSeleccionada['estado'] === 'activa' ? 'success' : 'secondary' ?> border rounded-pill">
                    <?= e($reservaSeleccionada['estado']) ?>
                  </span>
                </div>
                <h3 class="h5 fw-bold mb-1"><?= e($reservaSeleccionada['titulo']) ?></h3>
                <div class="text-muted small mb-2"><?= e($reservaSeleccionada['autor']) ?></div>

                <div class="d-flex flex-wrap gap-2 text-muted small">
                  <span><i class="bi bi-person me-1"></i><?= e($reservaSeleccionada['usuario_nombre']) ?></span>
                  <span>•</span>
                  
                  <span>•</span>
                  <span><i class="bi bi-coin text-warning me-1"></i>Saldo: <strong><?= (int) ($reservaSeleccionada['usuario_saldo'] ?? 0) ?> tokens</strong></span>
                </div>
              </div>
            </div>

            <?php if (empty($reservaSeleccionada['ha_expirado']) && $reservaSeleccionada['estado'] === 'activa'): ?>
              <hr class="my-4">

              <!-- Ficha de liquidación simplificada (reserva prepagada) -->
              <div class="card border p-3 rounded-3 bg-white mb-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                  <div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill mb-1">
                      <i class="bi bi-check-circle me-1"></i>Reserva pagada (<?= (int) ($reservaSeleccionada['tokens'] ?? $costeLibro) ?> 🪙)
                    </span>
                    <p class="small text-muted mb-0">
                      El importe ya fue descontado de su saldo al reservar.
                    </p>
                  </div>
                  <form method="POST" action="/mostrador/entregar" class="m-0">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="transaccion_id" value="<?= (int) $reservaSeleccionada['transaccion_id'] ?>">
                    <input type="hidden" name="metodo_pago" value="tokens">
                    <button type="submit" class="btn btn-primary fw-bold" id="btn-confirmar-entrega">
                      <i class="bi bi-check2-circle me-1"></i>Confirmar entrega (pagada: <?= (int) ($reservaSeleccionada['tokens'] ?? $costeLibro) ?> 🪙)
                    </button>
                  </form>
                </div>
              </div>

              <!-- Botón de anulación de reserva activa -->
              <div class="pt-3 mt-3 border-top d-flex justify-content-between align-items-center">
                <span class="small text-muted">¿El lector desiste de la reserva?</span>
                <form method="POST" action="/admin/reservas/cancelar" onsubmit="return confirm('¿Seguro que deseas anular esta reserva? Se liberarán los tokens bloqueados y el ejemplar quedará disponible.');" class="m-0">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="transaccion_id" value="<?= (int) $reservaSeleccionada['transaccion_id'] ?>">
                  <input type="hidden" name="retorno" value="/mostrador">
                  <button type="submit" class="btn btn-outline-danger btn-sm fw-semibold" id="btn-cancelar-reserva">
                    <i class="bi bi-x-circle me-1"></i>Cancelar reserva
                  </button>
                </form>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- ============================================================= -->
        <!-- LISTADO DE LIBROS A ENTREGAR (Ordenados por fecha de reserva) -->
        <!-- ============================================================= -->
        <div class="mt-4 pt-3 border-top" id="seccion-listado-reservas-pendientes">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
              <h3 class="h6 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-clock-history text-primary"></i> Libros pendientes de entrega
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill font-monospace" id="badge-total-reservas">
                  <?= count($reservasPendientes) ?> <?= count($reservasPendientes) === 1 ? 'pendiente' : 'pendientes' ?>
                </span>
              </h3>
              <p class="text-muted small mb-0">Ordenadas por fecha de reserva (las más antiguas primero para entrega ágil en mostrador).</p>
            </div>

            <?php if (!empty($reservasPendientes)): ?>
            <!-- Filtro rápido en tiempo real -->
            <div style="min-width: 260px; max-width: 360px;" class="ms-auto">
              <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-funnel"></i></span>
                <input type="text"
                       id="filtro-reservas-input"
                       class="form-control border-start-0"
                       placeholder="Filtrar por título, autor, lector..."
                       oninput="filtrarTablaReservas(this.value)">
                <button class="btn btn-outline-secondary border-start-0 bg-white" type="button" onclick="document.getElementById('filtro-reservas-input').value=''; filtrarTablaReservas('');" title="Limpiar filtro">
                  <i class="bi bi-x"></i>
                </button>
              </div>
            </div>
            <?php endif; ?>
          </div>

          <?php if (empty($reservasPendientes)): ?>
            <div class="text-center py-4 bg-light rounded-4 border">
              <i class="bi bi-check2-circle text-success fs-2 mb-2 d-block"></i>
              <div class="fw-semibold text-dark">No hay reservas pendientes de entrega</div>
              <div class="small text-muted">Todas las reservas activas han sido entregadas o no hay solicitudes en espera.</div>
            </div>
          <?php else: ?>
            <div class="table-responsive rounded-3 border bg-white shadow-xs">
              <table class="table table-hover align-middle mb-0" id="tabla-reservas-pendientes">
                <thead class="table-light text-muted small text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                  <tr>
                    <th class="ps-3 py-3" style="width: 140px;">Fecha Reserva</th>
                    <th class="py-3">Libro / Ubicación</th>
                    <th class="py-3">Lector</th>
                    <th class="py-3" style="width: 130px;">Código</th>
                    <th class="py-3" style="width: 130px;">Límite Recogida</th>
                    <th class="pe-3 py-3 text-end" style="width: 140px;">Acción Rápida</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($reservasPendientes as $r):
                    $estaSeleccionada = ($reservaSeleccionada && (int)$reservaSeleccionada['transaccion_id'] === (int)$r['transaccion_id']);
                    $saldoOk = ((int)$r['usuario_saldo'] >= $costeLibro);
                    $textoBusqueda = mb_strtolower($r['titulo'] . ' ' . $r['autor'] . ' ' . $r['usuario_nombre'] . ' ' . $r['codigo'] . ' ' . ($r['ubicacion'] ?? ''));
                  ?>
                    <tr class="fila-reserva-item <?= $estaSeleccionada ? 'table-primary border-primary' : '' ?>"
                        data-busqueda="<?= e($textoBusqueda) ?>"
                        id="fila-reserva-<?= (int)$r['transaccion_id'] ?>">
                      
                      <!-- Fecha Reserva -->
                      <td class="ps-3">
                        <div class="fw-semibold text-dark small">
                          <i class="bi bi-calendar-event me-1 text-muted"></i><?= date('d/m/Y', strtotime((string)$r['fecha_reserva'])) ?>
                        </div>
                        <div class="text-muted" style="font-size: 0.75rem;">
                          <i class="bi bi-clock me-1"></i><?= date('H:i', strtotime((string)$r['fecha_reserva'])) ?>
                        </div>
                      </td>

                      <!-- Libro / Ubicación -->
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <div style="width: 38px; height: 54px; flex-shrink: 0;" class="rounded-1 overflow-hidden bg-light border shadow-2xs d-flex align-items-center justify-content-center">
                            <?php if (!empty($r['portada_url'])): ?>
                              <img src="<?= e($r['portada_url']) ?>" alt="Portada" class="w-100 h-100 object-fit-cover" loading="lazy">
                            <?php else: ?>
                              <i class="bi bi-book text-muted"></i>
                            <?php endif; ?>
                          </div>
                          <div class="min-w-0">
                            <div class="fw-bold text-dark text-truncate" style="max-width: 260px;" title="<?= e($r['titulo']) ?>">
                              <?= e($r['titulo']) ?>
                            </div>
                            <div class="text-muted small text-truncate" style="max-width: 260px;">
                              <?= e($r['autor']) ?>
                            </div>
                            <div class="mt-1 d-flex flex-wrap gap-1">
                              <?php if (!empty($r['ubicacion'])): ?>
                                <span class="badge bg-light text-secondary border px-1 py-0" style="font-size: 0.7rem;" title="Ubicación física en estantería">
                                  <i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($r['ubicacion']) ?>
                                </span>
                              <?php endif; ?>
                              <span class="badge bg-light text-muted border px-1 py-0" style="font-size: 0.7rem;">
                                Copia #<?= (int)$r['ejemplar_id'] ?> (<?= e($r['condicion'] ?? 'bueno') ?>)
                              </span>
                            </div>
                          </div>
                        </div>
                      </td>

                      <!-- Lector -->
                      <td>
                        <div class="fw-semibold text-dark small text-truncate" style="max-width: 180px;">
                          <?= e($r['usuario_nombre']) ?>
                        </div>
                        <div class="d-flex align-items-center gap-1 mt-1">
                          <span class="badge bg-secondary-subtle text-secondary font-monospace" style="font-size: 0.72rem;">
                            
                          </span>
                          <span class="badge bg-<?= $saldoOk ? 'success' : 'warning' ?>-subtle text-<?= $saldoOk ? 'success' : 'dark' ?> border" style="font-size: 0.72rem;" title="Saldo actual del socio">
                            <i class="bi bi-coin text-warning me-1"></i><?= (int)$r['usuario_saldo'] ?> tkn
                          </span>
                        </div>
                      </td>

                      <!-- Código -->
                      <td>
                        <span class="badge bg-primary-subtle text-primary font-monospace px-2 py-1 border border-primary-subtle">
                          <?= e($r['codigo']) ?>
                        </span>
                      </td>

                      <!-- Límite Recogida -->
                      <td>
                        <?php if (!empty($r['ha_expirado'])): ?>
                          <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>Vencida
                          </span>
                          <div class="text-danger small" style="font-size: 0.72rem;"><?= date('d/m/Y', strtotime((string)$r['fecha_limite'])) ?></div>
                        <?php else: ?>
                          <span class="small fw-semibold text-dark">
                            <?= date('d/m/Y', strtotime((string)$r['fecha_limite'])) ?>
                          </span>
                          <div class="text-muted" style="font-size: 0.72rem;">
                            <?= date('H:i', strtotime((string)$r['fecha_limite'])) ?>
                          </div>
                        <?php endif; ?>
                      </td>

                      <!-- Acción Rápida -->
                      <td class="pe-3 text-end">
                        <?php if ($estaSeleccionada): ?>
                          <span class="badge bg-success-subtle text-success border border-success-subtle py-2 px-3 fw-bold rounded-pill">
                            <i class="bi bi-check-circle-fill me-1"></i>En Gestión
                          </span>
                        <?php else: ?>
                          <a href="/mostrador?codigo=<?= urlencode((string)$r['codigo']) ?>#sec-entregar-reserva"
                             class="btn btn-sm btn-primary fw-bold text-nowrap rounded-pill px-3 shadow-xs">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Entregar
                          </a>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>

              <!-- Mensaje si el filtro no coincide con nada -->
              <div id="sin-coincidencias-filtro" class="text-center py-4 text-muted small d-none">
                <i class="bi bi-search fs-4 text-secondary mb-1 d-block"></i>
                No se encontraron reservas pendientes que coincidan con la búsqueda.
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ================================================================= -->
  <!-- SECCIÓN 3: ➕ REGISTRAR DEPÓSITO                                  -->
  <!-- ================================================================= -->
  <div id="sec-registrar-deposito" class="operacion-panel d-none">
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
        <h2 class="h5 fw-bold mb-1">
          <i class="bi bi-box-arrow-in-down text-success me-2"></i>3. Recepción de Depósito
        </h2>
        <p class="text-muted small mb-0">Registra un libro aportado por un socio para donación o suma de tokens.</p>
      </div>
      <div class="card-body px-4 pb-4">
        <form method="POST" action="/mostrador/deposito">
          <?= csrf_campo() ?>

          <!-- Socio depositante -->
          <div class="mb-3">
            <label class="form-label small fw-bold">Socio Depositante:</label>
            <select name="usuario_id" class="form-select" required>
              <option value="">-- Seleccionar socio --</option>
              <?php foreach ($usuariosSistema as $u): ?>
                <option value="<?= (int) $u['id'] ?>">
                  <?= e($u['nombre']) ?> (<?= e($u['email']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Libro a depositar -->
          <div class="mb-3">
            <label class="form-label small fw-bold">Libro a Depositar:</label>
            <select name="libro_id" class="form-select" required>
              <option value="">-- Seleccionar libro del catálogo --</option>
              <?php foreach ($librosCatalogo as $l): ?>
                <option value="<?= (int) $l['id'] ?>">
                  <?= e($l['titulo']) ?> (<?= e($l['autor']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text small">
              ¿No está catalogado? <a href="/admin/libros/nuevo?retorno=/mostrador">Alta al vuelo por ISBN/manual</a>.
            </div>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold">Condición:</label>
              <select name="condicion" class="form-select">
                <option value="bueno" selected>Bueno</option>
                <option value="nuevo">Nuevo</option>
                <option value="regular">Regular</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">Ubicación:</label>
              <input type="text" name="ubicacion" class="form-control" value="MOSTRADOR">
            </div>
          </div>

          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-success fw-bold">
              <i class="bi bi-plus-circle me-1"></i>Aceptar Depósito (+<?= $bonoDeposito ?> token)
            </button>

            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#collapseRechazar">
              <i class="bi bi-x-octagon me-1"></i>Rechazar libro no apto...
            </button>
          </div>
        </form>

        <!-- Formulario colapsable para rechazar libro -->
        <div class="collapse mt-3" id="collapseRechazar">
          <div class="card card-body bg-light border rounded-3 p-3">
            <h3 class="h6 fw-bold text-danger mb-2">Rechazar Libro Aportado</h3>
            <form method="POST" action="/mostrador/rechazar-deposito">
              <?= csrf_campo() ?>
              <div class="mb-2">
                <label class="form-label small">Socio:</label>
                <select name="usuario_id" class="form-select form-select-sm" required>
                  <option value="">-- Seleccionar socio --</option>
                  <?php foreach ($usuariosSistema as $u): ?>
                    <option value="<?= (int) $u['id'] ?>">
                      <?= e($u['nombre']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-2">
                <label class="form-label small">Título del libro:</label>
                <input type="text" name="titulo" class="form-control form-select-sm" placeholder="Título o ISBN aportado" required>
              </div>
              <div class="mb-3">
                <label class="form-label small">Motivo de rechazo:</label>
                <select name="motivo" class="form-select form-select-sm" required>
                  <option value="Deteriorado (páginas rotas, mojado, carcoma)">Deteriorado (páginas rotas, mojado, carcoma)</option>
                  <option value="Subrayado exhaustivo o anotaciones">Subrayado exhaustivo o anotaciones</option>
                  <option value="Manual escolar o temario desfasado">Manual escolar o temario desfasado</option>
                  <option value="Contenido no permitido por la política del centro">Contenido no permitido por la política del centro</option>
                </select>
              </div>
              <button type="submit" class="btn btn-danger btn-sm w-100">
                <i class="bi bi-slash-circle me-1"></i>Registrar Rechazo Motivado
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ================================================================= -->
  <!-- SECCIÓN 4: 📚 ALTA DE CATÁLOGO / RESERVA A LECTOR                   -->
  <!-- ================================================================= -->
  <div id="sec-alta-catalogo" class="operacion-panel d-none">
    <div class="row g-4">
      <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-4 bg-surface">
          <div class="d-flex align-items-center gap-3 mb-3">
            <div class="p-3 bg-primary-subtle text-primary rounded-3"><i class="bi bi-upc-scan fs-4"></i></div>
            <div>
              <h2 class="h5 fw-bold mb-1">Alta Rápida de Libro</h2>
              <div class="text-muted small">Añade títulos nuevos al catálogo por ISBN o de forma manual.</div>
            </div>
          </div>
          <p class="small text-muted mb-4">
            Utiliza el escáner de código de barras o consulta de ISBN para recuperar metadatos oficiales y portadas al instante.
          </p>
          <div class="mt-auto d-flex flex-wrap gap-2">
            <a href="/admin/libros/nuevo?retorno=/mostrador" class="btn btn-primary fw-bold">
              <i class="bi bi-plus-lg me-1"></i>Añadir Libro por ISBN
            </a>
            <?php if (puede('csv.importar')): ?>
            <a href="/admin/csv" class="btn btn-outline-success fw-semibold">
              <i class="bi bi-file-earmark-spreadsheet me-1"></i>Importar CSV
            </a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-4 bg-surface">
          <div class="d-flex align-items-center gap-3 mb-3">
            <div class="p-3 bg-info-subtle text-info-emphasis rounded-3"><i class="bi bi-bookmark-plus fs-4"></i></div>
            <div>
              <h2 class="h5 fw-bold mb-1">Reservar a un Lector</h2>
              <div class="text-muted small">Crea una reserva presencial para un lector usando su correo o ID.</div>
            </div>
          </div>
          <form method="POST" action="/admin/reservas/crear">
            <?= csrf_campo() ?>
            <input type="hidden" name="retorno" value="/mostrador">

            <div class="mb-3">
              <label class="form-label small fw-bold">Email o ID del Lector:</label>
              <input type="text" name="lector" class="form-control font-monospace" placeholder="Ej: usuario@bookswap.local o ID" required>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold">Libro a Reservar:</label>
              <select name="libro_id" class="form-select" required>
                <option value="">-- Seleccionar libro del catálogo --</option>
                <?php foreach ($librosCatalogo as $l): ?>
                  <option value="<?= (int) $l['id'] ?>"><?= e($l['titulo']) ?> (<?= e($l['autor']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>

            <button type="submit" class="btn btn-outline-primary w-100 fw-bold">
              <i class="bi bi-calendar-check me-1"></i>Crear Reserva
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
/**
 * Estado global de la pantalla interactiva de Entrega Directa.
 */
var estadoDirecta = {
    costeLibro: <?= $costeLibro ?>,
    bonoDeposito: <?= $bonoDeposito ?>,
    ejemplarSeleccionado: null,
    usuarioSeleccionado: null,
    metodoCobro: 'tokens'
};

/**
 * Conmuta entre las 4 operaciones principales del Modo Mostrador.
 *
 * @param {string} operacion Clave de la operación ('directa', 'reserva', 'deposito', 'catalogo')
 */
function activarOperacionMostrador(operacion) {
    var tabs = ['directa', 'reserva', 'deposito', 'catalogo'];
    tabs.forEach(function (tab) {
        var panel = document.getElementById('sec-' + (tab === 'directa' ? 'entrega-directa' : (tab === 'reserva' ? 'entregar-reserva' : (tab === 'deposito' ? 'registrar-deposito' : 'alta-catalogo'))));
        var btn = document.getElementById('btn-tab-' + tab);
        if (panel) {
            if (tab === operacion) {
                panel.classList.remove('d-none');
            } else {
                panel.classList.add('d-none');
            }
        }
        if (btn) {
            if (tab === operacion) {
                btn.className = 'btn w-100 p-3 rounded-4 text-start h-100 transition-all border-2 btn-primary shadow border-primary text-white';
            } else {
                btn.className = 'btn w-100 p-3 rounded-4 text-start h-100 transition-all border-2 btn-outline-secondary bg-surface';
            }
        }
    });
}

/**
 * Filtra en tiempo real la lista de copias disponibles (Paso 1).
 */
function filtrarCopiasDisponibles() {
    var query = (document.getElementById('buscar-copia-input').value || '').trim().toLowerCase();
    var items = document.querySelectorAll('#lista-copias-disponibles .item-copia');
    var visibles = 0;

    items.forEach(function (el) {
        var titulo = (el.getAttribute('data-titulo') || '').toLowerCase();
        var autor = (el.getAttribute('data-autor') || '').toLowerCase();
        var isbn = (el.getAttribute('data-isbn') || '').toLowerCase();
        var id = (el.getAttribute('data-id') || '').toLowerCase();

        if (query === '' || titulo.includes(query) || autor.includes(query) || isbn.includes(query) || id.includes(query)) {
            el.style.display = 'flex';
            visibles++;
        } else {
            el.style.display = 'none';
        }
    });

    var badge = document.getElementById('badge-contador-copias');
    if (badge) {
        badge.textContent = visibles + ' copias';
    }
}

/**
 * Selecciona una copia disponible en el Paso 1.
 *
 * @param {number} id         ID del ejemplar
 * @param {string} titulo     Título del libro
 * @param {string} autor      Autor del libro
 * @param {string} condicion  Condición física
 * @param {string} ubicacion  Ubicación en biblioteca
 */
function seleccionarCopia(id, titulo, autor, condicion, ubicacion) {
    estadoDirecta.ejemplarSeleccionado = {
        id: id,
        titulo: titulo,
        autor: autor,
        condicion: condicion,
        ubicacion: ubicacion
    };

    document.getElementById('input-directa-ejemplar-id').value = id;
    document.getElementById('libro-sel-titulo').textContent = titulo;
    document.getElementById('libro-sel-autor').textContent = autor;
    document.getElementById('libro-sel-condicion').textContent = condicion;
    document.getElementById('libro-sel-ubicacion').textContent = ubicacion;
    document.getElementById('libro-sel-id').textContent = '#' + id;

    document.getElementById('libro-seleccionado-card').classList.remove('d-none');
    document.getElementById('lista-copias-disponibles').classList.add('d-none');

    verificarReservaActivaUsuario();
    actualizarResumenFinal();
}

/**
 * Deselecciona la copia actual en el Paso 1 para permitir elegir otra.
 */
function deseleccionarCopia() {
    estadoDirecta.ejemplarSeleccionado = null;
    document.getElementById('input-directa-ejemplar-id').value = '';
    document.getElementById('libro-seleccionado-card').classList.add('d-none');
    document.getElementById('lista-copias-disponibles').classList.remove('d-none');

    var alerta = document.getElementById('alerta-reserva-activa');
    if (alerta) alerta.classList.add('d-none');

    actualizarResumenFinal();
}

/**
 * Filtra en tiempo real la lista de usuarios del sistema (Paso 2).
 */
function filtrarUsuariosSistema() {
    var query = (document.getElementById('buscar-usuario-input').value || '').trim().toLowerCase();
    var items = document.querySelectorAll('#lista-usuarios-sistema .item-usuario');
    var visibles = 0;

    items.forEach(function (el) {
        var nombre = (el.getAttribute('data-nombre') || '').toLowerCase();
        var email = (el.getAttribute('data-email') || '').toLowerCase();
        
        if (query === '' || nombre.includes(query) || email.includes(query)) {
            el.style.display = 'flex';
            visibles++;
        } else {
            el.style.display = 'none';
        }
    });

    var badge = document.getElementById('badge-contador-usuarios');
    if (badge) {
        badge.textContent = visibles + ' socios';
    }
}

/**
 * Selecciona el usuario lector en el Paso 2.
 *
 * @param {number} id      ID del usuario
 * @param {string} nombre  Nombre del usuario
 * @param {string} email   Email del usuario
 * @param {number} saldo   Saldo disponible
 */
function seleccionarUsuario(id, nombre, email, saldo) {
    estadoDirecta.usuarioSeleccionado = {
        id: id,
        nombre: nombre,
        email: email,
        saldo: saldo
    };

    document.getElementById('input-directa-usuario-id').value = id;
    document.getElementById('usuario-sel-nombre').textContent = nombre;
    document.getElementById('usuario-sel-email').textContent = email;
    document.getElementById('usuario-sel-saldo').textContent = 'Saldo: ' + saldo + ' 🪙';

    document.getElementById('usuario-seleccionado-card').classList.remove('d-none');
    document.getElementById('lista-usuarios-sistema').classList.add('d-none');

    verificarReservaActivaUsuario();
    actualizarResumenFinal();
}

/**
 * Deselecciona el usuario actual en el Paso 2.
 */
function deseleccionarUsuario() {
    estadoDirecta.usuarioSeleccionado = null;
    document.getElementById('input-directa-usuario-id').value = '';
    document.getElementById('usuario-seleccionado-card').classList.add('d-none');
    document.getElementById('lista-usuarios-sistema').classList.remove('d-none');

    var alerta = document.getElementById('alerta-reserva-activa');
    if (alerta) alerta.classList.add('d-none');

    actualizarResumenFinal();
}

/**
 * Comprueba vía API si el usuario seleccionado ya tiene una reserva activa para la copia elegida (§1.7).
 */
function verificarReservaActivaUsuario() {
    var alerta = document.getElementById('alerta-reserva-activa');
    if (!estadoDirecta.ejemplarSeleccionado || !estadoDirecta.usuarioSeleccionado) {
        if (alerta) alerta.classList.add('d-none');
        return;
    }

    var url = '/api/mostrador/verificar-reserva?ejemplar_id=' + estadoDirecta.ejemplarSeleccionado.id + '&usuario_id=' + estadoDirecta.usuarioSeleccionado.id;
    fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.tiene_reserva && data.reserva) {
                document.getElementById('texto-alerta-reserva').textContent =
                    'El lector ya tiene una reserva activa para este mismo ejemplar (Código: ' + data.reserva.codigo + ').';
                var btnRes = document.getElementById('btn-ir-a-reserva');
                if (btnRes) {
                    btnRes.href = '/mostrador?codigo=' + encodeURIComponent(data.reserva.codigo);
                }
                alerta.classList.remove('d-none');
            } else {
                alerta.classList.add('d-none');
            }
        })
        .catch(function () {
            // Ignorar errores de red en comprobación no bloqueante
        });
}

/**
 * Selecciona el método de cobro en el Paso 3 ('tokens' o 'libro').
 *
 * @param {string} metodo 'tokens' o 'libro'
 */
function seleccionarMetodoCobro(metodo) {
    estadoDirecta.metodoCobro = metodo;
    document.getElementById('input-directa-metodo-pago').value = metodo;

    var btnTokens = document.getElementById('btn-metodo-tokens');
    var btnLibro = document.getElementById('btn-metodo-libro');
    var panelLibro = document.getElementById('panel-libro-aportado');

    if (metodo === 'tokens') {
        btnTokens.className = 'btn btn-warning w-100 p-2 text-start h-100 border border-2 border-warning active shadow-xs';
        btnLibro.className = 'btn btn-outline-success w-100 p-2 text-start h-100 border border-2';
        panelLibro.classList.add('d-none');
    } else {
        btnTokens.className = 'btn btn-outline-warning w-100 p-2 text-start h-100 border border-2';
        btnLibro.className = 'btn btn-success w-100 p-2 text-start h-100 border border-2 border-success active shadow-xs';
        panelLibro.classList.remove('d-none');
    }

    actualizarResumenFinal();
}

/**
 * Actualiza el panel de resumen final y valida si el botón de confirmación puede habilitarse.
 */
function actualizarResumenFinal() {
    var btnConfirmar = document.getElementById('btn-confirmar-entrega-directa');
    var alertaSaldo = document.getElementById('alerta-saldo-insuficiente');

    // Resumen de copia
    if (estadoDirecta.ejemplarSeleccionado) {
        document.getElementById('resumen-copia').textContent = estadoDirecta.ejemplarSeleccionado.titulo + ' (#' + estadoDirecta.ejemplarSeleccionado.id + ')';
    } else {
        document.getElementById('resumen-copia').textContent = 'No seleccionada';
    }

    // Resumen de lector
    if (estadoDirecta.usuarioSeleccionado) {
        document.getElementById('resumen-lector').textContent = estadoDirecta.usuarioSeleccionado.nombre + ' (' + estadoDirecta.usuarioSeleccionado.saldo + ' 🪙)';
    } else {
        document.getElementById('resumen-lector').textContent = 'No seleccionado';
    }

    // Resumen de método y cálculo de saldo
    var saldoFinalStr = '--';
    var esValido = false;

    if (estadoDirecta.usuarioSeleccionado && estadoDirecta.ejemplarSeleccionado) {
        var saldoActual = estadoDirecta.usuarioSeleccionado.saldo;

        if (estadoDirecta.metodoCobro === 'tokens') {
            document.getElementById('resumen-metodo').textContent = 'Tokens (-' + estadoDirecta.costeLibro + ')';
            var saldoRestante = saldoActual - estadoDirecta.costeLibro;

            if (saldoActual < estadoDirecta.costeLibro) {
                document.getElementById('texto-saldo-insuficiente').textContent =
                    'Saldo insuficiente: dispone de ' + saldoActual + ' tokens y se requieren ' + estadoDirecta.costeLibro + '.';
                alertaSaldo.classList.remove('d-none');
                saldoFinalStr = saldoRestante + ' 🪙 (Insuficiente)';
                esValido = false;
            } else {
                alertaSaldo.classList.add('d-none');
                saldoFinalStr = saldoRestante + ' 🪙';
                esValido = true;
            }
        } else {
            var netoIntercambio = estadoDirecta.bonoDeposito - estadoDirecta.costeLibro;
            document.getElementById('resumen-metodo').textContent = 'Libro (+ ' + estadoDirecta.bonoDeposito + ' / -' + estadoDirecta.costeLibro + ')';
            var saldoRestante = saldoActual + netoIntercambio;

            if (saldoRestante < 0) {
                document.getElementById('texto-saldo-insuficiente').textContent =
                    'Saldo insuficiente para compensar la diferencia del intercambio.';
                alertaSaldo.classList.remove('d-none');
                saldoFinalStr = saldoRestante + ' 🪙 (Insuficiente)';
                esValido = false;
            } else {
                alertaSaldo.classList.add('d-none');
                saldoFinalStr = saldoRestante + ' 🪙';
                esValido = true;
            }
        }
    } else {
        alertaSaldo.classList.add('d-none');
    }

    document.getElementById('resumen-saldo-final').textContent = saldoFinalStr;
    btnConfirmar.disabled = !esValido;
}

// Disparar confeti si se redirige con ?exito=1
document.addEventListener('DOMContentLoaded', function () {
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('exito') === '1') {
        if (window.BS && typeof window.BS.confeti === 'function') {
            window.BS.confeti(1800);
        }
    }
});

// =========================================================================
// LÓGICA DE CONTROL DEL ESCÁNER UNIVERSAL 1D (§2.2)
// =========================================================================
var escanerUniversalInput = document.getElementById('escaner-universal');
var hubEscaner = document.getElementById('hub-escaner-universal');
var indicadorListo = document.getElementById('indicador-escaner-listo');

// Atajo global: F2 o / para enfocar el escáner
document.addEventListener('keydown', function(e) {
    if (e.key === 'F2' || (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA')) {
        e.preventDefault();
        if (escanerUniversalInput) {
            escanerUniversalInput.focus();
            escanerUniversalInput.select();
        }
    }
});

if (escanerUniversalInput) {
    // Feedback visual de foco
    escanerUniversalInput.addEventListener('focus', function() {
        if (hubEscaner) hubEscaner.classList.add('border-success', 'shadow');
        if (indicadorListo) indicadorListo.classList.remove('d-none');
    });
    escanerUniversalInput.addEventListener('blur', function() {
        if (hubEscaner) hubEscaner.classList.remove('shadow');
    });

    // Disparo inmediato con pistola óptica de código de barras o teclado (Enter)
    escanerUniversalInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            procesarEnvioEscaner(e);
        }
    });

    var formEscaner = document.getElementById('form-escaner-universal');
    if (formEscaner) {
        formEscaner.addEventListener('submit', function(e) {
            e.preventDefault();
            procesarEnvioEscaner(e);
            return false;
        });
    }

    // Auto-focus continuo para el operador de mostrador
    window.addEventListener('load', function() {
        escanerUniversalInput.focus();
    });
}

/**
 * Conduce de forma atómica y visual al paso de entrega directa cuando se escanea un libro o se pulsa 'Continuar entrega'.
 */
function continuarEntregaLibro(libroId, titulo, autor, isbn, copiasDisponibles, ejemplarId, condicion, ubicacion) {
    if (typeof activarOperacionMostrador === 'function') {
        activarOperacionMostrador('directa');
    }

    if (copiasDisponibles <= 0) {
        alert('Este libro no tiene ejemplares físicos disponibles en este momento para entregar.');
        return;
    }

    // 1. Resolver el ejemplar a seleccionar
    var ejId = ejemplarId;
    var ejCond = condicion || 'bueno';
    var ejUbic = ubicacion || 'MOSTRADOR';

    if (!ejId) {
        var itemCopia = document.querySelector('#lista-copias-disponibles .item-copia[data-libro-id="' + libroId + '"]');
        if (itemCopia) {
            ejId = parseInt(itemCopia.getAttribute('data-id'), 10);
            ejCond = itemCopia.getAttribute('data-condicion') || ejCond;
            ejUbic = itemCopia.getAttribute('data-ubicacion') || ejUbic;
        }
    }

    // 2. Si se resolvió el ID del ejemplar, llamar directamente a seleccionarCopia
    if (ejId && typeof seleccionarCopia === 'function') {
        seleccionarCopia(ejId, titulo, autor, ejCond, ejUbic);
    } else {
        var bInp = document.getElementById('buscar-copia-input');
        if (bInp) {
            bInp.value = isbn || titulo;
            if (typeof filtrarCopiasDisponibles === 'function') {
                filtrarCopiasDisponibles();
            }
            var primerVisible = document.querySelector('#lista-copias-disponibles .item-copia:not([style*="display: none"])');
            if (primerVisible && typeof seleccionarCopia === 'function') {
                var pId = parseInt(primerVisible.getAttribute('data-id'), 10);
                var pTit = primerVisible.getAttribute('data-titulo') || titulo;
                var pAut = primerVisible.getAttribute('data-autor') || autor;
                var pCon = primerVisible.getAttribute('data-condicion') || 'bueno';
                var pUbi = primerVisible.getAttribute('data-ubicacion') || 'MOSTRADOR';
                seleccionarCopia(pId, pTit, pAut, pCon, pUbi);
            }
        }
    }

    // 3. Desplazar suavemente hasta la sección de entrega directa
    var sec = document.getElementById('sec-entrega-directa');
    if (sec) {
        sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // 4. Focalizar el paso siguiente (Paso 2: Elegir lector)
    setTimeout(function() {
        var uInput = document.getElementById('buscar-usuario-input');
        if (uInput && (!estadoDirecta || !estadoDirecta.usuarioSeleccionado)) {
            uInput.focus();
        } else if (estadoDirecta && estadoDirecta.usuarioSeleccionado) {
            var btnConf = document.getElementById('btn-confirmar-entrega-directa');
            if (btnConf && !btnConf.disabled) {
                btnConf.focus();
            }
        }
    }, 350);
}

function procesarEnvioEscaner(e) {
    if (e) e.preventDefault();
    if (!escanerUniversalInput) return false;

    var val = escanerUniversalInput.value.trim();
    if (!val) return false;

    var resCont = document.getElementById('resultado-escaneo');
    var alertBox = document.getElementById('alert-resultado-escaneo');
    var iconEl = document.getElementById('icon-resultado-escaneo');
    var tituloEl = document.getElementById('titulo-resultado-escaneo');
    var descEl = document.getElementById('desc-resultado-escaneo');
    var accionEl = document.getElementById('accion-resultado-escaneo');

    // Deshabilitar temporalmente mientras procesa
    escanerUniversalInput.disabled = true;

    var formData = new FormData();
    formData.append('valor', val);
    var csrfInput = document.querySelector('input[name="csrf_token"]');
    if (csrfInput) formData.append('csrf_token', csrfInput.value);

    fetch('/mostrador/escanear', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        escanerUniversalInput.disabled = false;
        escanerUniversalInput.value = '';
        escanerUniversalInput.focus();

        if (resCont) resCont.classList.remove('d-none');

        if (data.ok) {
            alertBox.className = 'alert alert-success mb-0 d-flex align-items-center justify-content-between p-3 rounded-3';
            if (data.tipo === 'reserva') {
                iconEl.innerHTML = '<i class="bi bi-box-seam-fill text-success fs-3"></i>';
                tituloEl.textContent = 'Reserva activa encontrada: ' + data.datos.codigo;
                descEl.textContent = 'Libro: «' + data.datos.libro_titulo + '» · Lector: ' + data.datos.usuario_nombre;
                accionEl.innerHTML = '<button type="button" class="btn btn-sm btn-success fw-bold" id="btn-entregar-reserva-scanner"><i class="bi bi-arrow-right-circle me-1"></i>Ir a entregar reserva</button>';

                var fnReserva = function() {
                    activarOperacionMostrador('reserva');
                    var inp = document.getElementById('buscar-reserva-input');
                    if (inp) {
                        inp.value = data.datos.codigo;
                        buscarReservaDirecta();
                    }
                    var secRes = document.getElementById('sec-entregar-reserva');
                    if (secRes) {
                        secRes.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                };

                var btnR = document.getElementById('btn-entregar-reserva-scanner');
                if (btnR) {
                    btnR.onclick = fnReserva;
                }
                fnReserva();
            } else if (data.tipo === 'isbn') {
                iconEl.innerHTML = '<i class="bi bi-book-fill text-success fs-3"></i>';
                tituloEl.textContent = 'Libro localizado en catálogo: ' + data.datos.titulo;

                if (data.datos.copias_disponibles > 0) {
                    descEl.textContent = 'Autor: ' + data.datos.autor + ' · ' + data.datos.copias_disponibles + ' copia(s) disponible(s).';
                    accionEl.innerHTML = '<button type="button" class="btn btn-sm btn-success fw-bold" id="btn-continuar-entrega-scanner"><i class="bi bi-arrow-right-circle me-1"></i>Continuar entrega</button>';

                    var fnContinuar = function() {
                        continuarEntregaLibro(
                            data.datos.libro_id,
                            data.datos.titulo,
                            data.datos.autor,
                            data.datos.isbn13 || '',
                            data.datos.copias_disponibles,
                            data.datos.ejemplar_id || null,
                            data.datos.condicion || 'bueno',
                            data.datos.ubicacion || 'MOSTRADOR'
                        );
                    };

                    var btnCont = document.getElementById('btn-continuar-entrega-scanner');
                    if (btnCont) {
                        btnCont.onclick = fnContinuar;
                    }

                    // Ejecutar automáticamente para preparar la entrega sin demoras
                    fnContinuar();
                } else {
                    descEl.textContent = 'Autor: ' + data.datos.autor + ' · Actualmente no tiene copias físicas disponibles para entrega.';
                    accionEl.innerHTML = '<a href="/libros/entrada?isbn=' + encodeURIComponent(data.datos.isbn13 || '') + '" class="btn btn-sm btn-warning fw-bold"><i class="bi bi-plus-circle me-1"></i>Añadir copias</a>';
                }
            } else if (data.tipo === 'usuario') {
                iconEl.innerHTML = '<i class="bi bi-person-badge-fill text-success fs-3"></i>';
                tituloEl.textContent = 'Lector identificado: ' + data.datos.nombre;
                descEl.textContent = 'Lector: ' + data.datos.nombre + ' (' + data.datos.email + ') · Saldo disponible: ' + data.datos.saldo + ' tokens';
                accionEl.innerHTML = '<span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i>Lector seleccionado</span>';

                if (typeof activarOperacionMostrador === 'function') {
                    activarOperacionMostrador('directa');
                }
                seleccionarUsuario(data.datos.usuario_id, data.datos.nombre, data.datos.email, data.datos.saldo);

                var secDir = document.getElementById('sec-entrega-directa');
                if (secDir) {
                    secDir.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }

                setTimeout(function() {
                    if (!estadoDirecta || !estadoDirecta.ejemplarSeleccionado) {
                        var bInp = document.getElementById('buscar-copia-input');
                        if (bInp) bInp.focus();
                    } else {
                        var btnConf = document.getElementById('btn-confirmar-entrega-directa');
                        if (btnConf && !btnConf.disabled) btnConf.focus();
                    }
                }, 350);
            }
        } else {
            alertBox.className = 'alert alert-danger mb-0 d-flex align-items-center justify-content-between p-3 rounded-3';
            iconEl.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger fs-3"></i>';
            tituloEl.textContent = 'Lectura no procesada';
            descEl.textContent = data.error || 'Código no reconocido.';
            if (data.accion_sugerida) {
                accionEl.innerHTML = '<a href="' + data.accion_sugerida + '" class="btn btn-sm btn-outline-danger fw-bold">Ver sugerencia</a>';
            } else {
                accionEl.innerHTML = '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById(\'resultado-escaneo\').classList.add(\'d-none\');">Cerrar</button>';
            }
        }
    })
    .catch(function(err) {
        escanerUniversalInput.disabled = false;
        escanerUniversalInput.focus();
        if (resCont) resCont.classList.remove('d-none');
        alertBox.className = 'alert alert-warning mb-0 p-3 rounded-3';
        tituloEl.textContent = 'Error de comunicación';
        descEl.textContent = 'No se pudo conectar con el servidor para procesar el escaneo.';
    });

    return false;
}

function buscarReservaDirecta() {
    var inp = document.getElementById('buscar-reserva-input');
    if (inp && inp.form) {
        inp.form.submit();
    }
}

function filtrarTablaReservas(termino) {
    var q = (termino || '').toLowerCase().trim();
    var filas = document.querySelectorAll('.fila-reserva-item');
    var visibles = 0;
    filas.forEach(function(f) {
        var texto = f.getAttribute('data-busqueda') || '';
        if (!q || texto.indexOf(q) !== -1) {
            f.style.display = '';
            visibles++;
        } else {
            f.style.display = 'none';
        }
    });
    var badgeTotal = document.getElementById('badge-total-reservas');
    if (badgeTotal) {
        if (q) {
            badgeTotal.textContent = visibles + (visibles === 1 ? ' coincidencia' : ' coincidencias');
        } else {
            badgeTotal.textContent = filas.length + (filas.length === 1 ? ' pendiente' : ' pendientes');
        }
    }
    var sinResultados = document.getElementById('sin-coincidencias-filtro');
    if (sinResultados) {
        sinResultados.classList.toggle('d-none', visibles > 0 || filas.length === 0);
    }
}
</script>
