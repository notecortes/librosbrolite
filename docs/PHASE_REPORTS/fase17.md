# Reporte de Fase 17 — Escáner de Código de Barras 1D + Escáner Universal

**Fecha:** 25 de septiembre de 2026  
**Proyecto:** BookSwap — Biblioteca Ciudadana (v4.5)  
**Estado:** Completada (0 FAIL, 98-101 PASS según conectividad de red)

---

## 1. Objetivos y Alcance de la Fase 17

La **Fase 17** adapta BookSwap a la realidad física de hardware de biblioteca del centro: **un lector de CÓDIGOS DE BARRAS 1D (USB/HID)** que emula teclado enviando caracteres alfanuméricos seguidos de Enter, y que **NO lee códigos QR 2D**. Para ello, se sustituye la generación de códigos QR por **Code 128 (JsBarcode)**, se reformatea el código de reserva a una longitud óptima de 14 caracteres y se implementa el **Hub de Escáner Universal en Mostrador** capaz de clasificar en tiempo real reservas, libros por ISBN y lectores por NIA.

Principales objetivos cumplidos:
1. **Sustitución de QR por Code 128 (§2.1):**
   - Eliminación de dependencias de `qrcode.js` en las vistas de usuario.
   - Integración de `JsBarcode` (CDN v3.11.6 + fallback nativo en `/assets/js/barcode.js`).
   - Reemplazo de lienzos y modales de reserva en `/mis-reservas` con elementos `<svg id="barcode-...">` renderizados en formato `CODE128`.
   - Adición del botón y vista limpia «Imprimir comprobante» para permitir al usuario acudir con su código impreso en papel o en la pantalla de su móvil.
2. **Formato Canónico de Código de Reserva (§2.1):**
   - Nuevo formato `RES-[A-Z0-9]{10}` (prefijo `RES-` + 10 caracteres alfanuméricos en mayúsculas = 14 caracteres en total), con densidad ideal para lectores láser y CCD 1D estándar.
   - Generación atómica en `app/helpers/reservas.php`.
3. **Escáner Universal en `/mostrador` (§2.2):**
   - Componente visual destacado tipo Hub con input `#escaner-universal` auto-enfocado y feedback visual reactivo (pulso verde cuando está listo).
   - Atajo de teclado global: pulsar `F2` o `/` devuelve el foco inmediatamente al escáner.
   - Enrutado inteligente por software:
     * Si empieza por `RES-`: procesa y carga la reserva activa (abre la pestaña de entrega y precarga los datos).
     * Si es un ISBN (10 o 13 dígitos numéricos o prefijo 978/979): localiza el libro en catálogo y ofrece la entrega directa si hay copias disponibles.
     * Si es un NIA (8 a 12 caracteres alfanuméricos): localiza al usuario del pool, muestra su saldo y lo vincula como lector activo.
4. **Endpoint Unificado `/mostrador/escanear` (§2.3):**
   - Maneja peticiones `POST` identificando el tipo (`'reserva'`, `'isbn'`, `'nia'`, `'desconocido'`).
   - Devuelve JSON enriquecido con `ok`, `tipo`, `datos` y `accion_sugerida`, soportando tanto AJAX como envíos web convencionales con redirección.
5. **Suite de Pruebas `T-SCAN-01..05` (§2.4):** Validación completa con 0 fallos.

---

## 2. Implementación Técnica

### 2.1 Generación de Código Code 128 en `app/helpers/reservas.php`
Se actualizó la asignación del código en `reserva_crear()`:
```php
$alfabeto = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
$sufijo = '';
for ($i = 0; $i < 10; $i++) {
    $sufijo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
}
$codigo = 'RES-' . $sufijo;
```
Esto genera cadenas exactas de 14 caracteres de alta legibilidad en lectores 1D sin ambigüedades.

### 2.2 Vistas de Usuario (`app/views/usuario/reservas.php`)
- Inclusión del script CDN de JsBarcode.
- Cada reserva activa renderiza su `<svg id="barcode-<?= $res['id'] ?>">` al cargarse la página mediante `JsBarcode("#barcode-...", codeVal, { format: "CODE128", width: 2, height: 60, displayValue: true })`.
- Función JavaScript `imprimirComprobanteReserva()` que abre una ventana optimizada para impresión en escala de grises con el código de barras legible por pistola láser.

### 2.3 Lógica del Escáner en `app/helpers/mostrador.php` y `app/router.php`
Se creó la función `mostrador_escanear(PDO $pdo, string $valorBruto): array` que inspecciona la entrada:
1. `RES-...` -> busca reserva activa en `transacciones` con libros y usuarios relacionados.
2. `978...` / 10-13 dígitos -> consulta `libros.isbn13` y copias disponibles en `ejemplares`.
3. `8-12` caracteres alfanuméricos -> consulta `nias` vinculados a usuarios activos y calcula su saldo en el ledger.
4. En cualquier otro caso devuelve `tipo = 'desconocido'` con sugerencia guiada.

Ruta `POST /mostrador/escanear`: protegida por `mostrador.acceder` y validación de seguridad CSRF obligatoria.

---

## 3. Resultados de las Pruebas (T-SCAN-01..05)

- **T-SCAN-01:** La generación de reserva produce códigos con formato exacto `RES-[A-Z0-9]{10}` y 14 caracteres de longitud. (PASS)
- **T-SCAN-02:** La vista `/mis-reservas` incluye `JsBarcode`, no referencia `qrcode.js` y contiene los elementos SVG para renderizado de barras. (PASS)
- **T-SCAN-03:** `POST /mostrador/escanear` con código `RES-...` válido devuelve tipo `'reserva'` con datos del libro, usuario y código. (PASS)
- **T-SCAN-04:** `POST /mostrador/escanear` con ISBN de libro existente devuelve tipo `'isbn'` con `libro_id` y recuento de copias disponibles. (PASS)
- **T-SCAN-05:** `POST /mostrador/escanear` con NIA asignado devuelve tipo `'nia'` con `usuario_id`, nombre y saldo de tokens. (PASS)

**Resultado Global:** `0 FAIL`, suite verde.
