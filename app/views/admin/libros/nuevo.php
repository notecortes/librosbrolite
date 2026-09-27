<?php
/**
 * BookSwap · Alta de Nuevo Libro con Asistente ISBN y Escáner (Admin / Personal).
 * Integra tres vías de entrada: manual, por ISBN tecleado / lector USB, y por cámara con html5-qrcode.
 */
declare(strict_types=1);

$error = $error ?? null;
$libroPrevio = $libroPrevio ?? [];
if (empty($libroPrevio['isbn13']) && !empty($_GET['isbn'])) {
    $libroPrevio['isbn13'] = trim((string) $_GET['isbn']);
}
?>

<div class="container-xxl py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h1 class="h2 fw-800 mb-1">Incorporar Nuevo Libro</h1>
      <p class="text-muted mb-0">Alta asistida mediante escáner de código de barras, ISBN o formulario manual.</p>
    </div>
    <a href="/admin/libros" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Volver al Catálogo
    </a>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4 shadow-sm" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Columna Izquierda: Escáner y Asistente ISBN -->
    <div class="col-12 col-lg-5">
      <!-- Tarjeta del Asistente ISBN -->
      <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
          <h2 class="h5 fw-bold mb-1"><i class="bi bi-upc-scan text-primary me-2"></i>Búsqueda Automática por ISBN</h2>
          <p class="small text-muted mb-0">Introduce el código o pulsa para activar la cámara del dispositivo.</p>
        </div>
        <div class="card-body p-4 pt-2">
          <div class="input-group mb-3">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-barcode"></i></span>
            <input type="text" id="input-isbn-asistente" class="form-control form-control-lg font-monospace" 
                   placeholder="97884..." value="<?= e($libroPrevio['isbn13'] ?? '') ?>" autocomplete="off" autofocus>
            <button class="btn btn-primary px-3 fw-bold" type="button" id="btn-buscar-isbn">
              <span id="spinner-isbn" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
              <i class="bi bi-search me-1" id="icono-buscar-isbn"></i>Buscar
            </button>
          </div>

          <!-- Botón para alternar escáner de cámara -->
          <div class="d-grid gap-2 mb-3">
            <button class="btn btn-outline-primary" type="button" id="btn-toggle-camara">
              <i class="bi bi-camera me-1"></i><span id="texto-btn-camara">Activar escáner de cámara</span>
            </button>
          </div>

          <!-- Visor del escáner con html5-qrcode -->
          <div id="contenedor-lector-qr" class="d-none border rounded-3 p-2 bg-dark text-white text-center position-relative mb-3">
            <div id="reader" style="width: 100%;"></div>
            <div class="small mt-2 text-white-50">Apunta con la cámara al código de barras EAN-13 del libro.</div>
          </div>

          <!-- Mensaje de estado de la consulta API -->
          <div id="estado-api" class="d-none alert py-2 px-3 small rounded-3 mb-0" role="alert"></div>

          <!-- Aviso de compatibilidad -->
          <div class="small text-muted mt-3">
            <i class="bi bi-info-circle me-1"></i>También puedes usar un <strong>lector de código de barras USB</strong> enfocando directamente el campo ISBN.
          </div>
        </div>
      </div>

      <!-- Previsualización de Portada -->
      <div class="card border-0 shadow-sm rounded-4 text-center p-3">
        <span class="small text-muted d-block mb-2">Previsualización de Portada</span>
        <div class="ratio ratio-3x4 bg-light rounded-3 overflow-hidden mx-auto shadow-xs border" style="--bs-aspect-ratio: calc(4 / 3 * 100%); max-width: 200px;">
          <img id="img-previsualizacion" src="/portada-svg?titulo=Nuevo+Libro" alt="Portada" width="200" height="267" loading="lazy" class="object-fit-cover w-100 h-100">
        </div>
      </div>
    </div>

    <!-- Columna Derecha: Formulario de Metadatos -->
    <div class="col-12 col-lg-7">
      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
          <h2 class="h5 fw-bold mb-1">Metadatos del Libro</h2>
          <p class="small text-muted mb-0">Revisa o edita los campos obtenidos antes de guardar en el catálogo.</p>
        </div>
        <div class="card-body p-4">
          <form method="POST" action="/admin/libros/nuevo" id="form-libro" class="row g-3">
            <?= csrf_campo() ?>
            <input type="hidden" name="via" id="campo-via" value="manual">

            <!-- ISBN-13 -->
            <div class="col-12 col-sm-6">
              <label for="campo-isbn" class="form-label small fw-bold">ISBN-13</label>
              <input type="text" id="campo-isbn" name="isbn13" class="form-control font-monospace"
                     placeholder="Ej: 9788437604197" value="<?= e($libroPrevio['isbn13'] ?? '') ?>">
              <div class="form-text">Opcional para libros antiguos sin código de barras.</div>
            </div>

            <!-- Idioma -->
            <div class="col-12 col-sm-6">
              <label for="campo-idioma" class="form-label small fw-bold">Idioma</label>
              <select id="campo-idioma" name="idioma" class="form-select">
                <option value="es" <?= ($libroPrevio['idioma'] ?? 'es') === 'es' ? 'selected' : '' ?>>Español (es)</option>
                <option value="en" <?= ($libroPrevio['idioma'] ?? '') === 'en' ? 'selected' : '' ?>>Inglés (en)</option>
                <option value="fr" <?= ($libroPrevio['idioma'] ?? '') === 'fr' ? 'selected' : '' ?>>Francés (fr)</option>
                <option value="de" <?= ($libroPrevio['idioma'] ?? '') === 'de' ? 'selected' : '' ?>>Alemán (de)</option>
                <option value="it" <?= ($libroPrevio['idioma'] ?? '') === 'it' ? 'selected' : '' ?>>Italiano (it)</option>
                <option value="ca" <?= ($libroPrevio['idioma'] ?? '') === 'ca' ? 'selected' : '' ?>>Catalán (ca)</option>
                <option value="gl" <?= ($libroPrevio['idioma'] ?? '') === 'gl' ? 'selected' : '' ?>>Gallego (gl)</option>
                <option value="eu" <?= ($libroPrevio['idioma'] ?? '') === 'eu' ? 'selected' : '' ?>>Euskera (eu)</option>
              </select>
            </div>

            <!-- Título -->
            <div class="col-12">
              <label for="campo-titulo" class="form-label small fw-bold">Título <span class="text-danger">*</span></label>
              <input type="text" id="campo-titulo" name="titulo" class="form-control" required
                     placeholder="Ej: Cien años de soledad" value="<?= e($libroPrevio['titulo'] ?? '') ?>">
            </div>

            <!-- Autor -->
            <div class="col-12 col-sm-7">
              <label for="campo-autor" class="form-label small fw-bold">Autor / Autores <span class="text-danger">*</span></label>
              <input type="text" id="campo-autor" name="autor" class="form-control" required
                     placeholder="Ej: Gabriel García Márquez" value="<?= e($libroPrevio['autor'] ?? '') ?>">
            </div>

            <!-- Año de Publicación -->
            <div class="col-12 col-sm-5">
              <label for="campo-anio" class="form-label small fw-bold">Año de Publicación</label>
              <input type="number" id="campo-anio" name="anio" class="form-control" min="1400" max="<?= date('Y') + 1 ?>"
                     placeholder="Ej: 1967" value="<?= e((string)($libroPrevio['anio'] ?? '')) ?>">
            </div>

            <!-- Editorial -->
            <div class="col-12 col-sm-6">
              <label for="campo-editorial" class="form-label small fw-bold">Editorial</label>
              <input type="text" id="campo-editorial" name="editorial" class="form-control"
                     placeholder="Ej: Sudamericana" value="<?= e($libroPrevio['editorial'] ?? '') ?>">
            </div>

            <!-- Género literario -->
            <div class="col-12 col-sm-6">
              <label for="campo-genero" class="form-label small fw-bold">Género literario</label>
              <input type="text" id="campo-genero" name="genero" class="form-control" list="lista-generos"
                     placeholder="Ej: Novela, Clásicos, Fantasía..." value="<?= e($libroPrevio['genero'] ?? '') ?>">
              <datalist id="lista-generos">
                <option value="Novela">
                <option value="Clásicos">
                <option value="Fantasía">
                <option value="Ciencia ficción">
                <option value="Distopía">
                <option value="Novela negra">
                <option value="Infantil">
                <option value="Juvenil">
                <option value="Poesía">
                <option value="Ensayo">
                <option value="Historia">
                <option value="Biografía">
              </datalist>
            </div>

            <!-- URL o ruta de la Portada -->
            <div class="col-12">
              <label for="campo-portada" class="form-label small fw-bold">URL o ruta de la Portada</label>
              <input type="text" id="campo-portada" name="portada_url" class="form-control"
                     placeholder="https://... o /uploads/covers/..." value="<?= e($libroPrevio['portada_url'] ?? '') ?>">
              <div class="form-text">Puede ser una dirección web (https://...) o una ruta local del servidor (/uploads/covers/...). Si se deja vacío, el sistema generará automáticamente una portada vectorial local.</div>
            </div>

            <!-- Observaciones / Sinopsis -->
            <div class="col-12">
              <label for="campo-observaciones" class="form-label small fw-bold">Observaciones / Sinopsis breve</label>
              <textarea id="campo-observaciones" name="observaciones" class="form-control" rows="3"
                        placeholder="Sinopsis, edición especial, etc."><?= e($libroPrevio['observaciones'] ?? '') ?></textarea>
            </div>

            <hr class="my-3">

            <!-- Checkbox y campos para crear primer ejemplar físico -->
            <div class="col-12">
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" id="check-ejemplar" name="crear_ejemplar" value="1" checked>
                <label class="form-check-label fw-bold" for="check-ejemplar">
                  Registrar de inmediato el 1er ejemplar físico en estantería
                </label>
              </div>
            </div>

            <div id="seccion-primer-ejemplar" class="row g-3 col-12 ps-3 pt-0">
              <div class="col-12 col-sm-6">
                <label for="campo-ej-ubicacion" class="form-label small fw-semibold">Ubicación física en estantería</label>
                <input type="text" id="campo-ej-ubicacion" name="ejemplar_ubicacion" class="form-control font-monospace"
                       value="A-01-01" placeholder="Ej: A-01-01">
              </div>
              <div class="col-12 col-sm-6">
                <label for="campo-ej-condicion" class="form-label small fw-semibold">Condición del ejemplar</label>
                <select id="campo-ej-condicion" name="ejemplar_condicion" class="form-select">
                  <option value="como_nuevo">Como nuevo</option>
                  <option value="bueno" selected>Buen estado</option>
                  <option value="aceptable">Aceptable</option>
                  <option value="nuevo">Nuevo</option>
                </select>
              </div>
            </div>

            <!-- Botones de Guardar -->
            <div class="col-12 d-flex justify-content-end gap-2 pt-3 border-top mt-4">
              <a href="/admin/libros" class="btn btn-secondary">Cancelar</a>
              <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm">
                <i class="bi bi-check-lg me-1"></i>Guardar en el Catálogo
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Carga condicional del CDN html5-qrcode para el escáner de cámara -->
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
/**
 * Script de integración del escáner ISBN, consulta AJAX a /api/isbn y actualización de formulario.
 */
document.addEventListener('DOMContentLoaded', function () {
  var inputAsistente = document.getElementById('input-isbn-asistente');
  var btnBuscar = document.getElementById('btn-buscar-isbn');
  var spinnerIsbn = document.getElementById('spinner-isbn');
  var iconoBuscar = document.getElementById('icono-buscar-isbn');
  var estadoApi = document.getElementById('estado-api');

  var campoIsbn = document.getElementById('campo-isbn');
  var campoTitulo = document.getElementById('campo-titulo');
  var campoAutor = document.getElementById('campo-autor');
  var campoEditorial = document.getElementById('campo-editorial');
  var campoAnio = document.getElementById('campo-anio');
  var campoGenero = document.getElementById('campo-genero');
  var campoIdioma = document.getElementById('campo-idioma');
  var campoPortada = document.getElementById('campo-portada');
  var campoObs = document.getElementById('campo-observaciones');
  var campoVia = document.getElementById('campo-via');
  var imgPrev = document.getElementById('img-previsualizacion');

  var checkEjemplar = document.getElementById('check-ejemplar');
  var seccionEjemplar = document.getElementById('seccion-primer-ejemplar');

  // Alternar visualización de campos del primer ejemplar
  if (checkEjemplar && seccionEjemplar) {
    checkEjemplar.addEventListener('change', function () {
      seccionEjemplar.style.display = this.checked ? 'flex' : 'none';
    });
  }

  // Actualizar imagen de previsualización al cambiar la URL o el título
  var actualizarPrevisualizacion = function () {
    var url = campoPortada.value.trim();
    if (url !== '') {
      imgPrev.src = url;
      imgPrev.onerror = function () {
        imgPrev.src = '/portada-svg?titulo=' + encodeURIComponent(campoTitulo.value || 'Nuevo Libro') + '&autor=' + encodeURIComponent(campoAutor.value || '');
      };
    } else {
      imgPrev.src = '/portada-svg?titulo=' + encodeURIComponent(campoTitulo.value || 'Nuevo Libro') + '&autor=' + encodeURIComponent(campoAutor.value || '') + '&genero=' + encodeURIComponent(campoGenero.value || '');
    }
  };

  if (campoPortada) campoPortada.addEventListener('input', actualizarPrevisualizacion);
  if (campoTitulo) campoTitulo.addEventListener('input', actualizarPrevisualizacion);

  // Función para ejecutar la búsqueda por ISBN
  var ejecutarBusquedaIsbn = function (isbn) {
    isbn = (isbn || inputAsistente.value || '').replace(/[^0-9Xx]/g, '').trim();
    if (isbn.length === 14 && (isbn.startsWith('978') || isbn.startsWith('979'))) {
      isbn = isbn.substring(0, 13);
    }
    if (!isbn) {
      mostrarEstado('Por favor, introduce un código ISBN válido (10 o 13 dígitos).', 'warning');
      return;
    }

    spinnerIsbn.classList.remove('d-none');
    iconoBuscar.classList.add('d-none');
    btnBuscar.disabled = true;
    mostrarEstado('Consultando fuentes bibliográficas para ISBN ' + isbn + '...', 'info');

    fetch('/api/isbn?isbn=' + encodeURIComponent(isbn))
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.ok && data.libro) {
          var l = data.libro;
          campoIsbn.value = l.isbn13 || isbn;
          campoTitulo.value = l.titulo || '';
          campoAutor.value = l.autor || '';
          if (l.editorial) campoEditorial.value = l.editorial;
          if (l.anio) campoAnio.value = l.anio;
          if (l.genero) campoGenero.value = l.genero;
          if (l.idioma && campoIdioma) campoIdioma.value = l.idioma;
          if (l.portada_url) campoPortada.value = l.portada_url;
          if (l.observaciones && !campoObs.value) campoObs.value = l.observaciones;

          campoVia.value = 'isbn';
          actualizarPrevisualizacion();

          var origenTexto = data.origen === 'local' ? 'del catálogo local' : (data.origen === 'google_books' ? 'de Google Books' : 'de Open Library');
          mostrarEstado('✓ Datos cargados correctamente ' + origenTexto + ' («' + (l.titulo || '') + '»).', 'success');
        } else {
          campoIsbn.value = isbn;
          campoVia.value = 'manual';
          mostrarEstado(data.mensaje || 'No se encontraron datos automáticos. Puedes rellenar los campos manualmente.', 'warning');
        }
      })
      .catch(function (err) {
        campoIsbn.value = isbn;
        campoVia.value = 'manual';
        mostrarEstado('Error de comunicación con el servicio bibliográfico. Introduce los datos manualmente.', 'danger');
      })
      .finally(function () {
        spinnerIsbn.classList.add('d-none');
        iconoBuscar.classList.remove('d-none');
        btnBuscar.disabled = false;
      });
  };

  var mostrarEstado = function (msg, tipo) {
    estadoApi.className = 'alert py-2 px-3 small rounded-3 mb-0 alert-' + tipo;
    estadoApi.textContent = msg;
    estadoApi.classList.remove('d-none');
  };

  btnBuscar.addEventListener('click', function () {
    ejecutarBusquedaIsbn();
  });

  inputAsistente.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      ejecutarBusquedaIsbn();
    }
  });

  // Si se abrió la página con un ISBN prefijado (por ejemplo desde el buscador del catálogo), consultar automáticamente
  if (inputAsistente && inputAsistente.value.trim() !== '' && (!campoTitulo || !campoTitulo.value.trim())) {
    setTimeout(function () {
      ejecutarBusquedaIsbn();
    }, 200);
  }

  // -------------------------------------------------------------
  // Integración del Escáner de Cámara con html5-qrcode
  // -------------------------------------------------------------
  var html5QrCode = null;
  var scannerActivo = false;
  var btnCamara = document.getElementById('btn-toggle-camara');
  var textoBtnCamara = document.getElementById('texto-btn-camara');
  var visorCamara = document.getElementById('contenedor-lector-qr');

  if (btnCamara) {
    btnCamara.addEventListener('click', function () {
      if (typeof Html5Qrcode === 'undefined') {
        mostrarEstado('El módulo de escáner no se pudo cargar. Introduce el ISBN manualmente o usa un lector USB.', 'warning');
        return;
      }

      if (scannerActivo) {
        detenerEscaner();
      } else {
        iniciarEscaner();
      }
    });
  }

  var iniciarEscaner = function () {
    visorCamara.classList.remove('d-none');
    textoBtnCamara.textContent = 'Detener cámara';
    btnCamara.classList.replace('btn-outline-primary', 'btn-outline-danger');

    html5QrCode = new Html5Qrcode('reader');
    var config = {
      fps: 10,
      qrbox: { width: 250, height: 160 },
      aspectRatio: 1.333334
    };

    html5QrCode.start(
      { facingMode: 'environment' },
      config,
      function onScanSuccess(decodedText) {
        detenerEscaner();
        inputAsistente.value = decodedText;
        ejecutarBusquedaIsbn(decodedText);
      },
      function onScanFailure(error) {
        // Ignorar fallos continuos de lectura frame a frame
      }
    ).then(function () {
      scannerActivo = true;
    }).catch(function (err) {
      detenerEscaner();
      mostrarEstado('No se pudo acceder a la cámara (' + err + '). Asegúrate de otorgar permisos o utiliza conexión segura (HTTPS/localhost).', 'danger');
    });
  };

  var detenerEscaner = function () {
    if (html5QrCode && scannerActivo) {
      html5QrCode.stop().then(function () {
        html5QrCode.clear();
        visorCamara.classList.add('d-none');
        textoBtnCamara.textContent = 'Activar escáner de cámara';
        btnCamara.classList.replace('btn-outline-danger', 'btn-outline-primary');
        scannerActivo = false;
      }).catch(function () {
        visorCamara.classList.add('d-none');
        scannerActivo = false;
      });
    } else {
      visorCamara.classList.add('d-none');
      textoBtnCamara.textContent = 'Activar escáner de cámara';
      if (btnCamara) btnCamara.classList.replace('btn-outline-danger', 'btn-outline-primary');
      scannerActivo = false;
    }
  };
});
</script>
