<?php
/**
 * BookSwap · Vista "Cómo funciona" (Fase 18 §3.6)
 *
 * Página explicativa completa con 3 pasos, mini-FAQ y recomendaciones de escaneo.
 */
declare(strict_types=1);

$nombreCentro = $config['centro_nombre'] ?? 'LibrosBro';
?>

<div class="py-2">
  <!-- Cabecera -->
  <div class="text-center max-w-700 mx-auto mb-5">
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-bold mb-3">
      <i class="bi bi-arrow-repeat me-1"></i>Economía Circular de Libros
    </span>
    <h1 class="display-5 fw-800 mb-3">¿Cómo funciona <?= e($nombreCentro) ?>?</h1>
    <p class="lead text-muted">
      Intercambia lecturas de manera sencilla, justa y gratuita en nuestro centro educativo.
    </p>
  </div>

  <!-- Los 3 Pasos Principales -->
  <div class="row g-4 mb-5" id="pasos-como-funciona">
    <div class="col-md-4">
      <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-surface text-center transition-hover">
        <div class="p-3 bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 70px; height: 70px;">
          <i class="bi bi-search fs-2"></i>
        </div>
        <div class="badge bg-light text-primary border rounded-pill mb-2 align-self-center px-3">Paso 1</div>
        <h2 class="h5 fw-bold mb-2">1. Busca tu libro</h2>
        <p class="text-muted small mb-0">
          Explora nuestro catálogo en vivo por título, autor o género y comprueba la disponibilidad inmediata de ejemplares físicos.
        </p>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-surface text-center transition-hover">
        <div class="p-3 bg-warning-subtle text-warning-emphasis rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 70px; height: 70px;">
          <i class="bi bi-bookmark-check fs-2"></i>
        </div>
        <div class="badge bg-light text-warning-emphasis border rounded-pill mb-2 align-self-center px-3">Paso 2</div>
        <h2 class="h5 fw-bold mb-2">2. Reserva en 1 clic</h2>
        <p class="text-muted small mb-0">
          Se descuenta temporalmente 1 🪙 token de tu saldo hasta que recojas el libro o anules la reserva. El ejemplar queda reservado durante 72 horas.
        </p>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card border-0 shadow-sm rounded-4 p-4 h-100 bg-surface text-center transition-hover">
        <div class="p-3 bg-success-subtle text-success rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 70px; height: 70px;">
          <i class="bi bi-shop fs-2"></i>
        </div>
        <div class="badge bg-light text-success border rounded-pill mb-2 align-self-center px-3">Paso 3</div>
        <h2 class="h5 fw-bold mb-2">3. Recoge en el mostrador</h2>
        <p class="text-muted small mb-0">
          Pasa por el mostrador físico y muestra tu código. Al haber sido pagada la reserva por adelantado, la entrega se confirma al instante sin cobro adicional.
        </p>
      </div>
    </div>
  </div>

  <!-- Consejo especial de hardware: Lector 1D y Móvil -->
  <div class="card border border-info shadow-sm rounded-4 p-4 mb-5 bg-surface" id="consejo-escaner-movil">
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center gap-3">
      <div class="p-3 bg-info-subtle text-info rounded-4">
        <i class="bi bi-upc-scan fs-1"></i>
      </div>
      <div>
        <h2 class="h5 fw-bold mb-1">
          <i class="bi bi-lightbulb-fill text-warning me-1"></i>Consejo para la recogida en mostrador
        </h2>
        <p class="text-muted small mb-0">
          El mostrador de <?= e($nombreCentro) ?> cuenta con un <strong>lector de códigos de barras óptico 1D de alta velocidad</strong>. Al presentar tu reserva desde la pantalla de tu teléfono móvil, <strong>sube el brillo al máximo</strong> o pulsa sobre <em>«Imprimir comprobante»</em> para una lectura instantánea sin esperas.
        </p>
      </div>
    </div>
  </div>

  <!-- Aviso especial: Catálogo cerrado -->
  <div class="card border-0 bg-primary-subtle text-primary-emphasis rounded-4 p-4 mb-5 shadow-sm">
    <div class="d-flex align-items-start gap-3">
      <div class="p-2 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; min-width: 44px;">
        <i class="bi bi-shield-exclamation fs-5"></i>
      </div>
      <div>
        <h3 class="h5 fw-bold mb-1">Importante: ¿Qué libros se admiten para intercambio?</h3>
        <p class="mb-0 small">
          En <?= e($nombreCentro) ?> operamos bajo un <strong>catálogo cerrado</strong>: <strong>únicamente se admiten los libros que figuran en nuestro catálogo oficial</strong>. No se admite cualquier título ni libros que no formen parte de los fondos admitidos. Antes de traer tus libros al mostrador, por favor verifica en el buscador del catálogo que están registrados.
        </p>
      </div>
    </div>
  </div>

  <!-- Mini-FAQ -->
  <div class="max-w-800 mx-auto mb-5" id="faq-como-funciona">
    <h2 class="h4 fw-800 text-center mb-4">Preguntas Frecuentes</h2>
    <div class="accordion accordion-flush rounded-4 shadow-sm overflow-hidden" id="accordionFaq">
      <div class="accordion-item border-0 border-bottom">
        <h3 class="accordion-header">
          <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
            ¿Cómo consigo tokens para retirar libros?
          </button>
        </h3>
        <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#accordionFaq">
          <div class="accordion-body text-muted small">
            Consigues tokens únicamente cuando traes libros al mostrador. <strong>Atención: solo se admiten los títulos incluidos en nuestro catálogo de libros</strong> (no se acepta cualquier título al azar). Al registrarte no se otorga ningún bono de bienvenida; la única forma de obtener tokens es trayendo y depositando un libro catalogado que esté en buen estado.
          </div>
        </div>
      </div>

      <div class="accordion-item border-0 border-bottom">
        <h3 class="accordion-header">
          <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq-admitidos">
            ¿Puedo traer cualquier libro para intercambiar?
          </button>
        </h3>
        <div id="faq-admitidos" class="accordion-collapse collapse" data-bs-parent="#accordionFaq">
          <div class="accordion-body text-muted small">
            <strong>No, no se admite cualquier libro.</strong> Solo se aceptan los títulos catalogados y aprobados en la plataforma. Si traes un ejemplar de un libro que no está en el catálogo, el mostrador no podrá aceptarlo para depósito ni bonificarlo con tokens.
          </div>
        </div>
      </div>

      <div class="accordion-item border-0 border-bottom">
        <h3 class="accordion-header">
          <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
            ¿Qué ocurre si no recojo mi reserva a tiempo?
          </button>
        </h3>
        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#accordionFaq">
          <div class="accordion-body text-muted small">
            Dispones de 72 horas para acudir al mostrador. Si transcurre ese plazo sin retirarlo, la reserva expira automáticamente y el ejemplar vuelve a quedar disponible en el catálogo para el resto de compañeros. No se te cobra ninguna penalización.
          </div>
        </div>
      </div>

      <div class="accordion-item border-0">
        <h3 class="accordion-header">
          <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
            ¿Cómo funciona la reserva de libros?
          </button>
        </h3>
        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#accordionFaq">
          <div class="accordion-body text-muted small">
            Puedes reservar cualquier ejemplar disponible desde el catálogo. Se bloqueará temporalmente el token correspondiente hasta que recojas el libro en el mostrador o anules la reserva.
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- CTA Inferior -->
  <div class="text-center py-4">
    <a href="/catalogo" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold shadow-sm mb-2">
      <i class="bi bi-book me-2"></i>Ver catálogo de libros admitidos
    </a>
  </div>
</div>
