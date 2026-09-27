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
              <input type="text" id="campo-portada" name="portada_url" class="form-control"
                     placeholder="https://... o /uploads/covers/..."
                     value="<?= e($libro['portada_url'] ?? '') ?>">
              <div class="form-text">Puede ser una dirección web (https://...) o una ruta local del servidor (/uploads/covers/...).</div>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
  var campoPortada = document.getElementById('campo-portada');
  var campoTitulo = document.getElementById('campo-titulo');
  var campoAutor = document.getElementById('campo-autor');
  var imgPrev = document.getElementById('img-previsualizacion');

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
});
</script>
