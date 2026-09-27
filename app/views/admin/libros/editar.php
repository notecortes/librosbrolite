<?php
/**
 * BookSwap · Edición de Libro (Admin / Personal).
 * Permite modificar los metadatos bibliográficos de un libro admitido en el catálogo.
 */
declare(strict_types=1);

$libro = $libro ?? [];
$error = $error ?? null;
$id = (int) ($libro['id'] ?? 0);
?>

<div class="container-xxl py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h2 fw-800 mb-1">Editar Libro #<?= $id ?></h1>
      <p class="text-muted mb-0">Actualiza los metadatos bibliográficos del título en el catálogo.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="/admin/libros" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver al Catálogo
      </a>
      <a href="/admin/ejemplares?libro_id=<?= $id ?>" class="btn btn-outline-primary">
        <i class="bi bi-bookshelf me-1"></i>Ver Ejemplares (<?= (int) ($libro['total_ejemplares'] ?? 0) ?>)
      </a>
      <a href="/libro/<?= $id ?>" class="btn btn-outline-secondary" target="_blank">
        <i class="bi bi-eye me-1"></i>Ficha Pública
      </a>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4 shadow-sm" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Columna Portada -->
    <div class="col-12 col-lg-4">
      <div class="card border-0 shadow-sm rounded-4 text-center p-4">
        <h2 class="h6 fw-bold text-muted text-uppercase mb-3">Previsualización de Portada</h2>
        <div class="ratio ratio-3x4 bg-light rounded-3 overflow-hidden mx-auto shadow-xs border" style="--bs-aspect-ratio: calc(4 / 3 * 100%); max-width: 220px;">
          <?php
            $portada = catalogo_resolver_url_portada($libro);
            $fallbackSvg = '/portada-svg?titulo=' . urlencode($libro['titulo'] ?? '') . '&autor=' . urlencode($libro['autor'] ?? '');
          ?>
          <img id="img-previsualizacion" src="<?= e($portada) ?>" alt="Portada" width="220" height="293" loading="lazy" class="object-fit-cover w-100 h-100"
               data-fallback="<?= e($fallbackSvg) ?>"
               onerror="this.onerror=null; this.src=this.dataset.fallback;">
        </div>

        <button type="button" class="btn btn-outline-primary fw-bold shadow-xs mt-3 w-100 py-2 btn-disparar-buscador-portadas">
          <i class="bi bi-images me-1"></i>Buscar Portadas en la Red
        </button>
      </div>
    </div>

    <!-- Columna Formulario -->
    <div class="col-12 col-lg-8">
      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
          <form method="POST" action="/admin/libros/editar" id="form-libro-editar" class="row g-3">
            <?= csrf_campo() ?>
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="col-12 col-sm-6">
              <label for="campo-isbn" class="form-label small fw-bold">ISBN-13</label>
              <input type="text" id="campo-isbn" name="isbn13" class="form-control font-monospace"
                     value="<?= e($libro['isbn13'] ?? '') ?>" placeholder="97884...">
            </div>

            <div class="col-12 col-sm-6">
              <label for="campo-idioma" class="form-label small fw-bold">Idioma</label>
              <select id="campo-idioma" name="idioma" class="form-select">
                <option value="es" <?= ($libro['idioma'] ?? 'es') === 'es' ? 'selected' : '' ?>>Español (es)</option>
                <option value="en" <?= ($libro['idioma'] ?? '') === 'en' ? 'selected' : '' ?>>Inglés (en)</option>
                <option value="fr" <?= ($libro['idioma'] ?? '') === 'fr' ? 'selected' : '' ?>>Francés (fr)</option>
                <option value="de" <?= ($libro['idioma'] ?? '') === 'de' ? 'selected' : '' ?>>Alemán (de)</option>
                <option value="it" <?= ($libro['idioma'] ?? '') === 'it' ? 'selected' : '' ?>>Italiano (it)</option>
                <option value="ca" <?= ($libro['idioma'] ?? '') === 'ca' ? 'selected' : '' ?>>Catalán (ca)</option>
                <option value="gl" <?= ($libro['idioma'] ?? '') === 'gl' ? 'selected' : '' ?>>Gallego (gl)</option>
                <option value="eu" <?= ($libro['idioma'] ?? '') === 'eu' ? 'selected' : '' ?>>Euskera (eu)</option>
              </select>
            </div>

            <div class="col-12">
              <label for="campo-titulo" class="form-label small fw-bold">Título <span class="text-danger">*</span></label>
              <input type="text" id="campo-titulo" name="titulo" class="form-control" required
                     value="<?= e($libro['titulo'] ?? '') ?>">
            </div>

            <div class="col-12 col-sm-7">
              <label for="campo-autor" class="form-label small fw-bold">Autor / Autores <span class="text-danger">*</span></label>
              <input type="text" id="campo-autor" name="autor" class="form-control" required
                     value="<?= e($libro['autor'] ?? '') ?>">
            </div>

            <div class="col-12 col-sm-5">
              <label for="campo-anio" class="form-label small fw-bold">Año de Publicación</label>
              <input type="number" id="campo-anio" name="anio" class="form-control" min="1400" max="<?= date('Y') + 1 ?>"
                     value="<?= e((string)($libro['anio'] ?? '')) ?>">
            </div>

            <div class="col-12 col-sm-6">
              <label for="campo-editorial" class="form-label small fw-bold">Editorial</label>
              <input type="text" id="campo-editorial" name="editorial" class="form-control"
                     value="<?= e($libro['editorial'] ?? '') ?>">
            </div>

            <div class="col-12 col-sm-6">
              <label for="campo-genero" class="form-label small fw-bold">Género literario</label>
              <input type="text" id="campo-genero" name="genero" class="form-control"
                     value="<?= e($libro['genero'] ?? '') ?>">
            </div>

            <div class="col-12">
              <label for="campo-portada" class="form-label small fw-bold">URL o ruta de la Portada</label>
              <div class="input-group">
                <input type="text" id="campo-portada" name="portada_url" class="form-control"
                       placeholder="https://... o /uploads/covers/..."
                       value="<?= e($libro['portada_url'] ?? '') ?>">
                <button type="button" class="btn btn-primary fw-semibold px-3 btn-disparar-buscador-portadas" title="Buscar alternativas en Google Books y Open Library">
                  <i class="bi bi-images me-1"></i>Buscar Portadas
                </button>
              </div>
              <div class="form-text">Puedes introducir una URL/ruta directa o pulsar <strong>Buscar Portadas</strong> para encontrar opciones en Google Books y Open Library.</div>
            </div>

            <div class="col-12">
              <label for="campo-observaciones" class="form-label small fw-bold">Observaciones / Sinopsis</label>
              <textarea id="campo-observaciones" name="observaciones" class="form-control" rows="3"><?= e($libro['observaciones'] ?? '') ?></textarea>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 pt-3 border-top mt-4">
              <a href="/admin/libros" class="btn btn-secondary">Cancelar</a>
              <button type="submit" class="btn btn-primary fw-bold px-4">
                <i class="bi bi-save me-1"></i>Actualizar Libro
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Buscador de Portadas -->
<div class="modal fade" id="modal-buscar-portadas" tabindex="-1" aria-labelledby="modalBuscarPortadasLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header border-bottom p-4">
        <div>
          <h2 class="modal-title h5 fw-bold mb-1" id="modalBuscarPortadasLabel">
            <i class="bi bi-images text-primary me-2"></i>Buscador de Portadas en la Red
          </h2>
          <p class="text-muted small mb-0">Explora portadas alternativas en Open Library y Google Books según el título, la editorial y el ISBN.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body p-4 bg-light">
        <!-- Barra de refinamiento de búsqueda -->
        <div class="card border-0 shadow-xs rounded-3 p-3 mb-4 bg-white">
          <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
              <label for="modal-busca-titulo" class="form-label small fw-bold text-muted mb-1">Título</label>
              <input type="text" id="modal-busca-titulo" class="form-control form-control-sm" placeholder="Título del libro">
            </div>
            <div class="col-12 col-sm-6 col-md-3">
              <label for="modal-busca-autor" class="form-label small fw-bold text-muted mb-1">Autor</label>
              <input type="text" id="modal-busca-autor" class="form-control form-control-sm" placeholder="Autor">
            </div>
            <div class="col-12 col-sm-6 col-md-3">
              <label for="modal-busca-editorial" class="form-label small fw-bold text-muted mb-1">Editorial</label>
              <input type="text" id="modal-busca-editorial" class="form-control form-control-sm" placeholder="Editorial">
            </div>
            <div class="col-12 col-sm-6 col-md-2">
              <label for="modal-busca-isbn" class="form-label small fw-bold text-muted mb-1">ISBN</label>
              <input type="text" id="modal-busca-isbn" class="form-control form-control-sm font-monospace" placeholder="ISBN">
            </div>
            <div class="col-12 d-flex justify-content-end gap-2 mt-3 pt-2 border-top">
              <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-modal-limpiar">
                <i class="bi bi-x-circle me-1"></i>Limpiar
              </button>
              <button type="button" class="btn btn-primary btn-sm fw-bold px-3" id="btn-modal-ejecutar-busqueda">
                <span id="spinner-btn-modal" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
                <i class="bi bi-search me-1" id="icono-btn-modal"></i>Buscar Portadas
              </button>
            </div>
          </div>
        </div>

        <!-- Estado de búsqueda: Spinner y Alertas -->
        <div id="modal-estado-busqueda" class="mb-3">
          <div id="modal-cargando" class="text-center py-5 d-none">
            <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
            <h3 class="h6 fw-bold text-secondary mb-1">Consultando bibliotecas digitales...</h3>
            <p class="small text-muted mb-0">Buscando portadas y ediciones coincidentes en Open Library y Google Books.</p>
          </div>

          <div id="modal-alerta" class="alert alert-warning d-none rounded-3 shadow-xs" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><span id="modal-alerta-texto"></span>
          </div>

          <div id="modal-info-resultados" class="d-none d-flex justify-content-between align-items-center mb-3">
            <span class="small fw-bold text-muted" id="modal-contador-texto"></span>
            <span class="small text-muted"><i class="bi bi-cursor me-1"></i>Haz clic en una portada para seleccionarla</span>
          </div>
        </div>

        <!-- Rejilla interactiva de portadas encontradas -->
        <div id="modal-grid-portadas" class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-3">
          <!-- Las tarjetas de portada se insertan dinámicamente mediante JS -->
        </div>
      </div>

      <div class="modal-footer border-top p-3 bg-white">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </div>
  </div>
</div>

<!-- Toast flotante de confirmación de selección -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
  <div id="toast-portada-seleccionada" class="toast align-items-center text-bg-success border-0 shadow-lg rounded-3" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill fs-5"></i>
        <div>
          <strong>¡Portada seleccionada!</strong>
          <div class="small">Recuerda pulsar «Actualizar Libro» para guardar los cambios.</div>
        </div>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var campoPortada = document.getElementById('campo-portada');
  var campoTitulo = document.getElementById('campo-titulo');
  var campoAutor = document.getElementById('campo-autor');
  var campoEditorial = document.getElementById('campo-editorial');
  var campoIsbn = document.getElementById('campo-isbn');
  var campoAnio = document.getElementById('campo-anio');
  var imgPrev = document.getElementById('img-previsualizacion');

  var modalEl = document.getElementById('modal-buscar-portadas');
  var bsModal = modalEl ? new bootstrap.Modal(modalEl) : null;

  var modalTitulo = document.getElementById('modal-busca-titulo');
  var modalAutor = document.getElementById('modal-busca-autor');
  var modalEditorial = document.getElementById('modal-busca-editorial');
  var modalIsbn = document.getElementById('modal-busca-isbn');
  var btnModalBuscar = document.getElementById('btn-modal-ejecutar-busqueda');
  var btnModalLimpiar = document.getElementById('btn-modal-limpiar');
  var spinnerModal = document.getElementById('spinner-btn-modal');
  var iconoModal = document.getElementById('icono-btn-modal');

  var cargandoEl = document.getElementById('modal-cargando');
  var alertaEl = document.getElementById('modal-alerta');
  var alertaTexto = document.getElementById('modal-alerta-texto');
  var infoResultados = document.getElementById('modal-info-resultados');
  var contadorTexto = document.getElementById('modal-contador-texto');
  var gridPortadas = document.getElementById('modal-grid-portadas');

  var toastEl = document.getElementById('toast-portada-seleccionada');
  var bsToast = toastEl ? new bootstrap.Toast(toastEl, { delay: 4500 }) : null;

  // Refrescar preview en el formulario
  var refrescar = function () {
    var url = campoPortada.value.trim();
    if (url !== '') {
      imgPrev.src = url;
      imgPrev.onerror = function () {
        imgPrev.src = '/portada-svg?titulo=' + encodeURIComponent(campoTitulo.value || '') + '&autor=' + encodeURIComponent(campoAutor.value || '');
      };
    } else {
      imgPrev.src = '/portada-svg?titulo=' + encodeURIComponent(campoTitulo.value || '') + '&autor=' + encodeURIComponent(campoAutor.value || '');
    }
  };

  if (campoPortada) campoPortada.addEventListener('input', refrescar);
  if (campoTitulo) campoTitulo.addEventListener('input', refrescar);
  if (campoAutor) campoAutor.addEventListener('input', refrescar);

  // Función para ejecutar búsqueda de portadas
  var ejecutarBusquedaPortadas = function () {
    var tit = modalTitulo ? modalTitulo.value.trim() : '';
    var aut = modalAutor ? modalAutor.value.trim() : '';
    var edi = modalEditorial ? modalEditorial.value.trim() : '';
    var isb = modalIsbn ? modalIsbn.value.trim() : '';
    var ani = campoAnio ? campoAnio.value.trim() : '';

    if (tit === '' && isb === '') {
      alertaTexto.textContent = 'Introduce al menos el título o el ISBN para buscar portadas.';
      alertaEl.classList.remove('d-none');
      infoResultados.classList.add('d-none');
      gridPortadas.innerHTML = '';
      return;
    }

    alertaEl.classList.add('d-none');
    cargandoEl.classList.remove('d-none');
    infoResultados.classList.add('d-none');
    gridPortadas.innerHTML = '';
    if (spinnerModal) spinnerModal.classList.remove('d-none');
    if (iconoModal) iconoModal.classList.add('d-none');
    if (btnModalBuscar) btnModalBuscar.disabled = true;

    var qs = new URLSearchParams({
      titulo: tit,
      autor: aut,
      editorial: edi,
      isbn: isb,
      anio: ani
    });

    fetch('/admin/libros/buscar-portadas?' + qs.toString(), {
      headers: { 'Accept': 'application/json' }
    })
    .then(function (r) {
      if (!r.ok) throw new Error('Error de servidor al buscar portadas');
      return r.json();
    })
    .then(function (data) {
      cargandoEl.classList.add('d-none');
      if (spinnerModal) spinnerModal.classList.add('d-none');
      if (iconoModal) iconoModal.classList.remove('d-none');
      if (btnModalBuscar) btnModalBuscar.disabled = false;

      var portadas = data.portadas || [];
      if (portadas.length === 0) {
        alertaTexto.textContent = 'No se encontraron portadas alternativas para estos criterios. Prueba a simplificar el título o eliminar la editorial.';
        alertaEl.classList.remove('d-none');
        return;
      }

      contadorTexto.textContent = 'Se han encontrado ' + portadas.length + ' portadas disponibles';
      infoResultados.classList.remove('d-none');

      // Renderizar rejilla
      portadas.forEach(function (p) {
        var col = document.createElement('div');
        col.className = 'col';

        var card = document.createElement('div');
        card.className = 'card h-100 border-0 shadow-sm rounded-3 overflow-hidden position-relative portada-card-hover';
        card.style.cursor = 'pointer';
        card.style.transition = 'all 0.2s ease-in-out';

        // Ratio contenedor de la imagen
        var ratioDiv = document.createElement('div');
        ratioDiv.className = 'ratio ratio-3x4 bg-light overflow-hidden';

        var img = document.createElement('img');
        img.src = p.thumbnail || p.url;
        img.alt = p.titulo || 'Portada encontrada';
        img.className = 'object-fit-cover w-100 h-100';
        img.loading = 'lazy';
        img.onerror = function () {
          // Si falla la miniatura, probar url directa o fallback
          if (this.src !== p.url) {
            this.src = p.url;
          } else {
            this.src = '/portada-svg?titulo=' + encodeURIComponent(p.titulo || '') + '&autor=' + encodeURIComponent(p.autor || '');
          }
        };
        ratioDiv.appendChild(img);

        // Badge editorial si coincide
        if (p.coincide_editorial) {
          var badgeEd = document.createElement('span');
          badgeEd.className = 'badge bg-success position-absolute top-0 start-0 m-2 shadow-xs';
          badgeEd.innerHTML = '<i class="bi bi-check2 me-1"></i>Editorial coincidente';
          ratioDiv.appendChild(badgeEd);
        }

        // Badge fuente (Google Books / Open Library)
        var badgeSrc = document.createElement('span');
        badgeSrc.className = 'badge bg-dark bg-opacity-75 position-absolute top-0 end-0 m-2 shadow-xs small';
        badgeSrc.textContent = p.fuente;
        ratioDiv.appendChild(badgeSrc);

        card.appendChild(ratioDiv);

        // Card body con detalles y botón
        var body = document.createElement('div');
        body.className = 'card-body p-2 d-flex flex-column';

        var pEd = document.createElement('div');
        pEd.className = 'small fw-bold text-truncate text-secondary mb-1';
        pEd.title = p.editorial || 'Editorial no especificada';
        pEd.textContent = p.editorial ? p.editorial : 'Edición general';
        body.appendChild(pEd);

        if (p.anio) {
          var pAn = document.createElement('div');
          pAn.className = 'small text-muted mb-2';
          pAn.textContent = 'Año: ' + p.anio;
          body.appendChild(pAn);
        }

        var btnSel = document.createElement('button');
        btnSel.type = 'button';
        btnSel.className = 'btn btn-outline-primary btn-sm w-100 fw-bold mt-auto py-1';
        btnSel.innerHTML = '<i class="bi bi-check-lg me-1"></i>Elegir';
        body.appendChild(btnSel);

        card.appendChild(body);

        // Acción al hacer clic en cualquier parte de la tarjeta o botón
        var seleccionarEstaPortada = function () {
          campoPortada.value = p.url;
          refrescar();
          if (bsModal) bsModal.hide();
          if (bsToast) bsToast.show();
        };

        card.addEventListener('click', seleccionarEstaPortada);
        col.appendChild(card);
        gridPortadas.appendChild(col);
      });
    })
    .catch(function (err) {
      cargandoEl.classList.add('d-none');
      if (spinnerModal) spinnerModal.classList.add('d-none');
      if (iconoModal) iconoModal.classList.remove('d-none');
      if (btnModalBuscar) btnModalBuscar.disabled = false;
      alertaTexto.textContent = 'Hubo un problema al buscar portadas. Comprueba la conexión o intenta más tarde.';
      alertaEl.classList.remove('d-none');
    });
  };

  // Abrir modal y sincronizar campos
  var disparadores = document.querySelectorAll('.btn-disparar-buscador-portadas');
  disparadores.forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (modalTitulo) modalTitulo.value = campoTitulo ? campoTitulo.value : '';
      if (modalAutor) modalAutor.value = campoAutor ? campoAutor.value : '';
      if (modalEditorial) modalEditorial.value = campoEditorial ? campoEditorial.value : '';
      if (modalIsbn) modalIsbn.value = campoIsbn ? campoIsbn.value : '';

      if (bsModal) {
        bsModal.show();
        ejecutarBusquedaPortadas();
      }
    });
  });

  if (btnModalBuscar) {
    btnModalBuscar.addEventListener('click', ejecutarBusquedaPortadas);
  }

  if (btnModalLimpiar) {
    btnModalLimpiar.addEventListener('click', function () {
      if (modalTitulo) modalTitulo.value = '';
      if (modalAutor) modalAutor.value = '';
      if (modalEditorial) modalEditorial.value = '';
      if (modalIsbn) modalIsbn.value = '';
      if (modalTitulo) modalTitulo.focus();
    });
  }

  // Permitir pulsar Enter en los inputs del modal
  [modalTitulo, modalAutor, modalEditorial, modalIsbn].forEach(function (inp) {
    if (inp) {
      inp.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          ejecutarBusquedaPortadas();
        }
      });
    }
  });
});
</script>

<style>
.portada-card-hover:hover {
  transform: translateY(-4px);
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
  border-color: var(--bs-primary) !important;
}
</style>

