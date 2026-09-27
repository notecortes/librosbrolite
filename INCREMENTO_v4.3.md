Lee BRIEF.md, PROGRESS.md e INCREMENTO_v4.2.md: TODAS sus reglas siguen vigentes
(fases con DoD, regresión = fallo, archivos completos, no editar tests para que
pasen). Este documento añade las fases 12 → 14. No cambia ninguna funcionalidad
de negocio: si un test funcional existente falla, tu cambio es incorrecto.
0. OBJETIVO Y REGLAS DEL INCREMENTO

    RENDIMIENTO: reducir tiempo de respuesta, peso de página y consumo de recursos, MEDIANTE MEDICIONES ANTES/DESPUÉS (nada de optimizar a ciegas).
    SEGURIDAD: auditoría sistemática tipo OWASP sobre TODO el código, correcciónde hallazgos por severidad y hardening, con tests de regresión de seguridad.
    No rompas funcionalidad: la suite completa debe seguir en 0 FAIL al final decada fase. Los únicos cambios autorizados en ficheros canónicos se listanen §2 y §3; todo lo demás se toca solo si un hallazgo lo exige (documéntalo).

1. FASE 12 — RENDIMIENTO Y CONSUMO DE RECURSOS
1.1 Línea base OBLIGATORIA (antes de tocar nada)

    Crea tests/benchmark.php (PHP puro, estilo del runner): para cada ruta clave —/, /catalogo (y con búsqueda), /libro/1, /login, /visitanos, /dashboard(autenticado como usuario), /mostrador (autenticado como personal),/mi-historial (autenticado), /admin (autenticado como admin) — ejecuta 10peticiones con curl (reutilizando sesiones del runner: login_como), midetiempo total de cada una y muestra tabla con min/mediana/max por ruta.Al final imprime el peso en KB de cada página.
    Ejecútalo, guarda la salida como docs/PERFORMANCE-baseline.txt y resume endocs/PERFORMANCE.md (tabla antes).
    Test T-PERF-01: el script existe, todas las rutas responden 200 y la tabla seimprime (sin umbral duro: medir, no flaquear).

1.2 Base de datos

    Ejecuta EXPLAIN sobre las 10 consultas más frecuentes (catálogo con filtros,ficha con copias, historial con JOINs, listado admin de transacciones,notificaciones no leídas, auditoría filtrada). Añade los índices que falten.AUTORIZADO: añadir definiciones INDEX/INDEX KEY en database/01_schema.sql(nada más en ese fichero; documenta cada índice añadido y su query en elreporte de fase). Candidatos evidentes: transacciones(usuario_id),transacciones(gestionada_por), movimientos_tokens(transaccion_id).
    Detecta y elimina consultas N+1 (bucles que lanzan una query por fila):sustituye por JOIN o IN(...) — el historial y las cards de catálogo son lossospechosos habituales.
    En TODAS las listas paginadas, usa SQL_CALC_FOUND_ROWS NO: cuenta conCOUNT(*) separada solo cuando se necesite paginación, y limita siempre conLIMIT/OFFSET.

1.3 Portadas: caché local (privacidad + velocidad)

    Nueva helper portada_src(array $libro): si portada_url es remota Y existecopia local en uploads/covers/, sirve la local; si no, sirve la remota.
    Al dar de alta un libro con portada remota (por ISBN/API, manual o CSV),intenta DESCARGARLA server-side (cURL, timeout 5s, solo si Content-Type esimage/* y tamaño ≤ 2MB) a uploads/covers/{isbn o hash}.jpg y guarda la rutalocal en portada_url; si falla, conserva la remota (degradación elegante).No re-descargues si ya existe. NO modifiques el seed: los libros democonservan sus URLs remotas.
    Test T-PERF-02: alta por ISBN con red → portada_url empieza por /uploads/covers/ y el archivo existe (SKIP sin red).

1.4 Frontend

    Añade width y height (o aspect-ratio) a TODAS las imágenes de portada paraeliminar CLS, y loading="lazy" donde no esté.