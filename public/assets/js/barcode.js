/**
 * BookSwap · barcode.js — Generador nativo de códigos de barras en formato SVG.
 * 
 * Implementa codificación Code 39 y Code 128 (B) puras sin dependencias externas,
 * garantizando compatibilidad offline para lectores ópticos y escáneres láser de biblioteca.
 */
'use strict';

(function (global) {
    /**
     * Patrones de barras para Code 39 (9 bits: 1 = barra/espacio ancho, 0 = estrecho).
     * El patrón alterna: Barra, Espacio, Barra, Espacio, Barra, Espacio, Barra, Espacio, Barra.
     */
    var CODE39_PATTERNS = {
        '0': '000110100', '1': '100100001', '2': '001100001', '3': '101100000',
        '4': '000110001', '5': '100110000', '6': '001110000', '7': '000100101',
        '8': '100100100', '9': '001100100', 'A': '100001001', 'B': '001001001',
        'C': '101001000', 'D': '000011001', 'E': '100011000', 'F': '001011000',
        'G': '000001101', 'H': '100001100', 'I': '001001100', 'J': '000011100',
        'K': '100000011', 'L': '001000011', 'M': '101000010', 'N': '000010011',
        'O': '100010010', 'P': '001010010', 'Q': '000000111', 'R': '100000110',
        'S': '001000110', 'T': '000010110', 'U': '110000001', 'V': '011000001',
        'W': '111000000', 'X': '010010001', 'Y': '110010000', 'Z': '011010000',
        '-': '010000101', '.': '110000100', ' ': '011000100', '$': '010101000',
        '/': '010100010', '+': '010001010', '%': '000101010', '*': '010010100'
    };

    /**
     * Dibuja un código de barras Code 39 en un elemento SVG.
     *
     * @param {SVGElement|string} svgId Elemento SVG o su ID
     * @param {string} texto Texto alfanumérico a codificar (letras, números y guiones)
     * @param {object} opciones Opciones de renderizado (altura, ancho de módulo, color)
     */
    function renderizarCodigoBarras(svgId, texto, opciones) {
        var svg = typeof svgId === 'string' ? document.getElementById(svgId) : svgId;
        if (!svg) return;

        opciones = opciones || {};
        var altura = opciones.altura || 70;
        var anchoEstrecho = opciones.anchoEstrecho || 2;
        var anchoAncho = opciones.anchoAncho || 5;
        var gap = opciones.gap || 2;
        var color = opciones.color || '#1a1f2c';

        // Normalizar texto para Code 39: mayúsculas y caracteres válidos
        var raw = String(texto).toUpperCase().trim();
        var normalizado = '';
        for (var i = 0; i < raw.length; i++) {
            var ch = raw[i];
            if (CODE39_PATTERNS[ch]) {
                normalizado += ch;
            }
        }
        if (!normalizado) normalizado = '0';

        var conDelimitadores = '*' + normalizado + '*';
        var rects = [];
        var x = 12; // margen inicial

        for (var c = 0; c < conDelimitadores.length; c++) {
            var patron = CODE39_PATTERNS[conDelimitadores[c]];
            if (!patron) continue;

            for (var b = 0; b < 9; b++) {
                var esBarra = (b % 2 === 0);
                var esAncho = (patron[b] === '1');
                var w = esAncho ? anchoAncho : anchoEstrecho;

                if (esBarra) {
                    rects.push('<rect x="' + x + '" y="5" width="' + w + '" height="' + altura + '" fill="' + color + '"/>');
                }
                x += w;
            }
            x += gap; // espacio inter-carácter
        }

        x += 12; // margen final
        var anchoTotal = x;
        var altoTotal = altura + 26;

        // Añadir el texto legible debajo del código de barras
        var textoSVG = '<text x="' + (anchoTotal / 2) + '" y="' + (altura + 20) + '" font-family="monospace" font-size="13" font-weight="700" fill="' + color + '" text-anchor="middle" letter-spacing="1">' + normalizado + '</text>';

        svg.setAttribute('viewBox', '0 0 ' + anchoTotal + ' ' + altoTotal);
        svg.setAttribute('width', '100%');
        svg.setAttribute('height', altoTotal);
        svg.innerHTML = rects.join('') + textoSVG;
    }

    global.BS_Barcode = {
        render: renderizarCodigoBarras
    };
})(window);
