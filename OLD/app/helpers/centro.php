<?php
/**
 * BookSwap · centro.php — configuración y horario del punto físico (CANÓNICO).
 * Usado por: layout (footer), página Visítanos y tests T-VISIT-*.
 */
declare(strict_types=1);

/** Carga toda la tabla configuracion como [clave => valor]. */
function config_cargar(PDO $pdo): array {
    return $pdo->query('SELECT clave, valor FROM configuracion')->fetchAll(PDO::FETCH_KEY_PAIR);
}

/** JSON de centro_horario → array con los 7 días (claves sin acentos: miercoles, sabado). */
function horario_parsear(?string $json): array {
    $base = ['lunes'=>[], 'martes'=>[], 'miercoles'=>[], 'jueves'=>[],
             'viernes'=>[], 'sabado'=>[], 'domingo'=>[]];
    if ($json) {
        $d = json_decode($json, true);
        if (is_array($d)) $base = array_merge($base, array_intersect_key($d, $base));
    }
    return $base;
}

/** "09:00-14:00, 17:00-20:00" de HOY según Europe/Madrid, o "Cerrado". */
function horario_hoy(?string $json): string {
    $dias = ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'];
    try {
        $hoy = $dias[(int) (new DateTime('now', new DateTimeZone('Europe/Madrid')))->format('w')];
    } catch (Throwable) { return '—'; }
    $rangos = horario_parsear($json)[$hoy] ?? [];
    return $rangos ? implode(' · ', $rangos) : 'Cerrado';
}

/** [bool abierto, string mensaje] · "Abierto ahora · cierra a las 20:00" / "Cerrado · abre mañana a las 09:00". */
function abierto_ahora(?string $json): array {
    $dias    = ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'];
    $nombres = ['lunes'=>'lunes','martes'=>'martes','miercoles'=>'miércoles','jueves'=>'jueves',
                'viernes'=>'viernes','sabado'=>'sábado','domingo'=>'domingo'];
    try { $dt = new DateTime('now', new DateTimeZone('Europe/Madrid')); }
    catch (Throwable) { return [false, 'Horario no disponible']; }

    $h   = horario_parsear($json);
    $hoy = $dias[(int) $dt->format('w')];
    $min = (int) $dt->format('G') * 60 + (int) $dt->format('i');
    $aMin = static function (string $hhmm): int {
        [$g, $m] = array_map('intval', explode(':', trim($hhmm)));
        return $g * 60 + $m;
    };

    foreach ($h[$hoy] as $rango) {
        $p = array_map(trim(...), explode('-', $rango));
        if (count($p) !== 2) continue;
        $a = $aMin($p[0]); $c = $aMin($p[1]);
        if ($min >= $a && $min < $c) return [true, 'Abierto ahora · cierra a las ' . $p[1]];
        if ($min < $a)               return [false, 'Cerrado · abre hoy a las ' . $p[0]];
    }
    for ($i = 1; $i <= 7; $i++) {
        $d = $dias[((int) $dt->format('w') + $i) % 7];
        if (!empty($h[$d])) {
            $p = array_map(trim(...), explode('-', $h[$d][0]));
            $cuando = $i === 1 ? 'mañana' : 'el ' . $nombres[$d];
            return [false, 'Cerrado · abre ' . $cuando . ' a las ' . $p[0]];
        }
    }
    return [false, 'Cerrado temporalmente'];
}