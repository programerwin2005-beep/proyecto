# KPIs para el Sistema de Gestión de Iglesia

> **Base:** `PROMPT_BASE_DATOS.md` (esquema verificado el 28/10/2026, migraciones `2026_10_28_000001` a `000004`).
> **Stack:** Laravel 13.34 · MySQL 8.4 · zona horaria `America/La_Paz`.
> **Alcance:** diseño de KPIs, SQL, equivalente Laravel, visualización, frecuencia, rol, índices y datos faltantes.
>
> **Regla de oro financiera:** el universo de ingresos es **siempre** `ingresos`.
> `pago_cursos` se usa para el detalle de cobranza y, cuando `ingresos.monto_total` es
> `NULL` (Opción A de la migración `2026_10_21_000001`), para resolver el monto con
> `COALESCE(i.monto_total, pc.monto)`. Nunca se suman ambas tablas como fuentes independientes.

---

## 0. Tabla resumen

| ID | KPI | Área | Fórmula | Frecuencia | Rol | Prioridad |
|---|---|---|---|---|---|---|
| A1 | Altas mensuales y acumulado | Membresía | `COUNT(*)` por mes de `fecha_ingreso`; acumulado con `SUM() OVER` | Mensual | Administrador / Secretario | Imprescindible |
| A2 | % activos vs. inactivos | Membresía | `activos / total * 100` | Mensual | Administrador / Secretario | Imprescindible |
| A3 | Pirámide de edad y sexo | Membresía | conteo por rango etario (`f_nacimiento`) y `sexo` | Trimestral | Administrador / Secretario | Útil |
| A4 | Miembros sin ministerio | Membresía | activos sin `miembro_ministerio` vigente | Mensual | Administrador / Secretario | Útil |
| A5 | Miembros que nunca se inscribieron | Membresía | miembros sin fila en `inscripciones` | Semestral | Administrador / Secretario | Útil |
| A6 | Retención y bajas | Membresía | `bajas del período / miembros al inicio * 100` | Mensual | Administrador | Avanzado |
| B1 | Miembros activos por ministerio | Ministerios | `COUNT(DISTINCT miembro_id)` vigente por ministerio | Mensual | Administrador / Secretario | Imprescindible |
| B2 | Ministerios sin líder | Ministerios | ministerios con `id_lider_iglesia` nulo | Mensual | Administrador | Imprescindible |
| B3 | Actividades por estado | Actividades | `COUNT(*)` agrupado por estado normalizado | Semanal | Administrador / Secretario | Imprescindible |
| B4 | Avance promedio de actividades | Actividades | `AVG(último porcentaje_avance)` por actividad | Semanal | Administrador / Secretario | Imprescindible |
| B5 | Actividades atrasadas o < 50 % | Actividades | `fecha < hoy` y estado no final, o último avance < 50 | Semanal | Administrador / Secretario | Imprescindible |
| B6 | Ingresos generados por actividad | Actividades | `SUM(ingresos.monto_total)` por `id_actividad` | Mensual | Tesorero / Administrador | Útil |
| B7 | Actividades sin seguimiento | Actividades | actividades sin fila en `seguimiento_actividades` | Semanal | Secretario | Útil |
| C1 | Ocupación de cupo | Academia | `inscripciones activas / cupo_max * 100` | Semanal | Administrador / Secretario / Docente | Imprescindible |
| C2 | % asistencia por curso | Academia | `(presente + tardanza) / registros únicos * 100` | Semanal | Administrador / Secretario / Docente | Imprescindible |
| C3 | Ausentismo por sesión | Academia | `ausentes / registros únicos * 100` | Semanal | Docente / Secretario | Imprescindible |
| C4 | Cursos con baja ocupación | Academia | cursos con ocupación < 50 % | Semanal | Administrador / Secretario | Útil |
| C5 | Carga por docente | Academia | cursos, sesiones y horas semanales por docente | Mensual | Administrador / Secretario | Útil |
| C6 | Alumnos en riesgo | Academia | alumnos con asistencia < 75 % | Semanal | Docente / Secretario | Útil |
| D1 | Ingresos vs. egresos y saldo | Finanzas | `SUM(ingresos)`, `SUM(egresos)`, diferencia por mes | Mensual | Tesorero / Administrador | Imprescindible |
| D2 | Diezmos y ofrendas por culto | Finanzas | `SUM` por tipo normalizado y culto | Mensual | Tesorero / Administrador | Imprescindible |
| D3 | Composición por tipo normalizado | Finanzas | `% = tipo / total` con `CASE` | Mensual | Tesorero / Administrador | Imprescindible |
| D4 | % egresos de personal | Finanzas | `egresos de personal / ingresos * 100` | Mensual | Tesorero / Administrador | Imprescindible |
| D5 | Ingresos sin origen | Finanzas | ingresos con `id_actividad`, `id_culto` y `pago_curso_id` nulos | Mensual | Tesorero | Útil |
| D6 | Variación mes a mes | Finanzas | `(mes actual - mes anterior) / mes anterior * 100` | Mensual | Tesorero / Administrador | Útil |
| D7 | Promedio de ofrenda por culto | Finanzas | `ofrendas / servicios con ofrenda` | Mensual | Tesorero / Administrador | Útil |
| E1 | Cobrado vs. esperado por curso | Cobranza | `SUM(pagos) / SUM(monto_inscripción)` | Mensual | Tesorero / Administrador | Imprescindible |
| E2 | Saldo pendiente | Cobranza | `MAX(0, esperado - cobrado)` | Semanal | Tesorero | Imprescindible |
| E3 | Alumnos Pagado / Parcial / Pendiente | Cobranza | conteo por estado de pago derivado | Semanal | Tesorero / Secretario | Imprescindible |
| E4 | Mora por curso | Cobranza | deuda con más de 30 días desde `fecha_inscripcion` | Mensual | Tesorero | Útil |
| E5 | Tasa de recaudación | Cobranza | `cobrado / esperado * 100` | Mensual | Tesorero | Imprescindible |
| E6 | Conciliación `pago_cursos` ↔ `ingresos` | Cobranza | pagos sin ingreso y ingresos huérfanos | Semanal | Tesorero | Útil |
| F1 | Estado de contratos | Personal | conteo por vigencia calculada | Mensual | Administrador / Tesorero | Imprescindible |
| F2 | Contratos por vencer (≤ 30 días) | Personal | contratos con `fecha_fin` entre hoy y hoy + 30 | Semanal | Administrador / Tesorero | Imprescindible |
| F3 | Planilla mensual total | Personal | salarios vigentes + pagos directos sin contrato | Mensual | Tesorero | Imprescindible |
| F4 | Costo de personal por cargo | Personal | `SUM(salario)` por `id_cargo` | Mensual | Tesorero | Útil |
| F5 | Contratos solapados | Personal | autounión por miembro y rango de fechas | Por evento | Administrador | Útil |
| G1 | Usuarios por rol | Seguridad | `COUNT(DISTINCT user_id)` por rol | Mensual | Administrador | Imprescindible |
| G2 | Usuarios sin rol | Seguridad | usuarios sin fila vigente en `role_user` | Semanal | Administrador | Imprescindible |
| G3 | Cobertura de acceso docente | Seguridad | docentes sin usuario y usuarios Docente sin docente | Mensual | Administrador | Útil |
| G4 | Módulos con mayor actividad | Seguridad | `COUNT(*)` por `modulo` en `audit_logs` | Semanal | Administrador | Avanzado |
| G5 | Cobertura de permisos por rol | Seguridad | módulos con permiso / módulos esperados | Trimestral | Administrador | Útil |
| H1 | Cobertura de pruebas | Calidad | `coveredstatements / statements * 100` | Por release | Administrador | Imprescindible |
| H2 | Tests pasando / fallando | Calidad | `tests - failures - errors - skipped` sobre `tests` | Por release | Administrador | Imprescindible |
| H3 | Hallazgos de seguridad por severidad | Calidad | conteo Sonar + ZAP por severidad | Por release | Administrador | Imprescindible |
| H4 | Deuda técnica y code smells | Calidad | `sqale_index` / esfuerzo de mantenibilidad | Mensual | Administrador | Útil |
| H5 | Quality gate | Calidad | `OK` / `ERROR` | Por release | Administrador | Imprescindible |

**Prioridad:** imprescindible = se usa para decidir cada semana/mes; útil = mejora el contexto; avanzado = requiere datos nuevos o más volumen.

---

## 1. Supuestos, decisiones y deriva de esquema

### 1.1 Supuestos de negocio

1. **Rol Pastor ≈ Administrador.** La tabla `roles` no tiene “Pastor”; el pastor debe operar con el rol Administrador (acceso total por código).
2. **`contratos.salario` es mensual.** Si el contrato es anual, la planilla debe dividirse entre los meses de vigencia. Validar con el tesorero antes de publicar el KPI F3.
3. **“Mora” no existe como fecha de vencimiento.** Hoy se aproxima con `DATEDIFF(hoy, inscripciones.fecha_inscripcion)`. Para mora real hace falta `inscripciones.fecha_vencimiento` (ver §7).
4. **“Promedio de ofrenda por culto”** se calcula por **servicio con ofrenda** (`COUNT(DISTINCT ingresos.fecha)` dentro del culto), no por definición de culto. Si dos servicios caen el mismo día, se cuentan una vez; si eso ocurre, usar la tabla `asistencias_culto` o una tabla de servicios.
5. **Asistencia:** `Presente` y `Tardanza` cuentan como asistencia; `Justificado` no cuenta como asistencia efectiva pero se muestra aparte; `Ausente` cuenta como ausentismo.
6. **Estados libres:** todos los `CASE` de normalización son deliberadamente tolerantes (`LIKE`) y deben validarse con `SELECT DISTINCT` antes de cerrar el catálogo.
7. **Fechas:** los KPIs mensuales usan `:desde` y `:hasta` inclusivos para columnas `DATE`, y rangos semiabiertos (`>= :desde`, `< :hasta_excl`) para `TIMESTAMP`.
8. **Zona horaria:** pasar las fechas desde PHP (`now()->startOfMonth()`) y no confiar en `CURDATE()` del servidor MySQL. Recomendación: `'timezone' => '-04:00'` en `config/database.php` para la conexión MySQL.

### 1.2 Deriva de esquema (histórica y resuelta)

Las migraciones `2026_10_28_000001` a `000004` alinearon el esquema con este documento. Esta tabla conserva el detalle de la deriva para no reintroducir consultas obsoletas:

| Tema | Estado anterior | Resolución vigente |
|---|---|---|
| Líder de ministerio | `ministerios.liderable_type` + `liderable_id` (polimórfico) | `2026_10_27_000001` agregó `ministerios.id_lider_iglesia` y eliminó `liderable_*`. Usa un solo `LEFT JOIN` con `id_lider_iglesia` |
| Cargo en ministerio | `miembro_ministerio.cargo_id` | La misma migración eliminó `cargo_id`; el pivote solo guarda vigencia (`fecha_inicio`, `fecha_fin`) |
| `asistencias_sesion.user_id` | Se eliminó y volvió a agregar | Existe en el esquema actual; se puede auditar quién registró |
| `ingresos.monto_total/fecha/metodo_pago` | `NOT NULL` | `2026_10_21` los volvió `NULL` para pagos de curso; usar `COALESCE(i.monto_total, pc.monto)` |
| `contratos.id_cargo` | Existía solo en la base viva | `2026_10_28_000001` la reconcilia en migraciones |
| `egresos.id_lider_iglesia` | Existía solo en la base viva | `2026_10_28_000001` la reconcilia; `EgresoRequest` impide contrato y líder simultáneos |
| `permisos.deleted_at` | No existe | Sigue sin existir: **no filtres `permisos.deleted_at`** |
| Valores de estado y tipo | Texto libre | Enums de PHP respaldados por `VARCHAR`; no hay ENUM nativo en MySQL |
| Categorías financieras | Solo texto (`tipo`, `tipo_egreso`) | `categorias_financieras` + `categoria_id`, con el texto como respaldo |

> **Acción recomendada:** ejecutar el apéndice de revalidación del PROMPT y comparar con `Schema::getColumns()`/`Schema::getIndexes()` antes de añadir nuevos KPIs.

### 1.3 Correcciones al dashboard actual

El `DashboardController` actual funciona, pero tiene cuatro problemas que conviene corregir al integrar estos KPIs:

1. `Ingreso::total()` hace `leftJoin('pago_cursos')` **sin** `pago_cursos.deleted_at IS NULL`. Un pago eliminado lógicamente puede seguir aportando monto.
2. Los conteos de asistencia (`Asistencia::where('estado', ...)`) **no deduplican** `(sesion_id, miembro_id)`; con duplicados, presentes/ausentes se inflan.
3. `ingresosPorTipo` agrupa por `tipo` crudo: “Inscripción” e “Inscripción Curso: Taller de Canto” quedan separados.
4. `contratos_activos` cuenta `activo = 1` sin validar `fecha_inicio`/`fecha_fin`; un contrato vencido pero con `activo = 1` se muestra como activo.

---

## 2. Propuesta de layout

### 2.1 Dashboard del Administrador / Pastor

**Fila 1 — tarjetas (6):**
| Tarjeta | KPI | Semáforo |
|---|---|---|
| Miembros activos | A2 | Verde ≥ 80 %, ámbar 60–79 %, rojo < 60 % |
| Altas del mes | A1 | Sin semáforo; comparar con promedio de 3 meses |
| Ingresos del mes | D1 | Sin semáforo; variación vs. mes anterior |
| Egresos del mes | D1 | Sin semáforo; variación vs. mes anterior |
| Saldo del mes | D1 | Verde ≥ 0, rojo < 0 |
| Contratos por vencer | F2 | Verde 0, ámbar 1–2, rojo ≥ 3 |

**Fila 2 — gráficos grandes:**
- Línea/columnas de **ingresos vs. egresos vs. saldo** de 12 meses (D1).
- Dona de **composición de ingresos** normalizada (D3).
- Barras horizontales de **asistencia por curso** (C2) o **ocupación** (C1).

**Fila 3 — operación:**
- Línea de **altas mensuales** (A1) + tarjeta de **ministerios sin líder** (B2).
- Barras de **avance por actividad** (B4) + listado de **actividades atrasadas** (B5).
- **Top deudores** (E2/E4) y **pagos sin ingreso** (E6).

**Fila 4 — alertas accionables (semáforo):**
- Ingresos sin origen > 0 (D5).
- Contratos vencidos o por vencer (F1/F2).
- Usuarios sin rol (G2).
- Quality gate en `ERROR` (H5) y hallazgos críticos (H3).
- Cursos con ocupación < 50 % (C4) y alumnos en riesgo (C6).

### 2.2 Dashboard del Secretario

No debe ver finanzas. Prioridad:
1. Miembros activos, altas del mes, miembros sin ministerio (A2, A1, A4).
2. Ministerios sin líder (B2), actividades atrasadas (B5), avance promedio (B4).
3. Ocupación por curso (C1), asistencia por curso (C2), alumnos en riesgo (C6).
4. Usuarios sin rol no aplica (solo Administrador); en su lugar, **cobertura de registro de asistencia** (C7, avanzado).

### 2.3 Dashboard del Tesorero

1. Ingresos, egresos, saldo del mes (D1).
2. Por cobrar total y tasa de recaudación (E2, E5).
3. % de personal sobre ingresos (D4).
4. Contratos por vencer (F2) y planilla mensual (F3).
5. Diezmos/ofrendas por culto (D2) y promedio por culto (D7).
6. Ingresos sin origen (D5) y conciliación de pagos (E6).

### 2.4 Dashboard del Docente

El sistema ya redirige al docente a `sesiones.index`. Mantener esa decisión. Si se habilita un panel docente, debe ser **solo de sus cursos**:
- Ocupación de sus cursos (C1 filtrado por docente).
- Asistencia de su última sesión (C3).
- Alumnos en riesgo de sus cursos (C6).
- Nunca finanzas globales ni miembros fuera de sus cursos.

### 2.5 Reportes

| Reporte | KPIs | Rol |
|---|---|---|
| `reportes.miembros` | A1–A6 + tabla detallada | Administrador / Secretario |
| `reportes.asistencia` | C1–C6 + detalle por sesión | Administrador / Secretario / Docente (solo sus cursos) |
| `reportes.financiero` | D1–D7 + E1–E6 + F1–F5 | Administrador / Tesorero |
| `reportes.academia` (nuevo) | C1–C6, consolidado y por curso | Administrador / Secretario / Docente |
| `reportes.ministerios` (nuevo) | B1–B7 | Administrador / Secretario |
| `reportes.seguridad` (nuevo) | G1–G5 | Administrador |
| `reportes.calidad` (nuevo) | H1–H5 | Administrador |

Para los reportes nuevos hay que agregar módulos a `config/permisos.php` y sembrar permisos:
`reportes.academia`, `reportes.ministerios`, `reportes.seguridad`, `reportes.calidad`.

### 2.6 Umbrales de color recomendados

| KPI | Verde | Ámbar | Rojo |
|---|---|---|---|
| A2 % activos | ≥ 80 % | 60–79 % | < 60 % |
| B4 avance promedio | ≥ 80 % | 50–79 % | < 50 % |
| B5 atrasadas | 0 | 1–2 | ≥ 3 |
| C1 ocupación | 80–100 % | 50–79 % | < 50 % o > 100 % |
| C2 asistencia | ≥ 85 % | 70–84 % | < 70 % |
| C3 ausentismo | < 10 % | 10–20 % | > 20 % |
| C4 baja ocupación | 0 cursos | 1 curso | ≥ 2 cursos |
| C6 alumnos en riesgo | 0 | 1–2 | ≥ 3 |
| D4 personal / ingresos | ≤ 50 % | 51–65 % | > 65 % |
| D5 ingresos sin origen | 0 % | < 5 % | ≥ 5 % |
| E1/E5 recaudación | ≥ 90 % | 70–89 % | < 70 % |
| E4 mora | 0 alumnos | 1–10 % de alumnos | > 10 % |
| F1 contratos vencidos | 0 | — | ≥ 1 |
| F2 por vencer | 0 | 1–2 | ≥ 3 |
| G2 usuarios sin rol | 0 | — | ≥ 1 |
| H1 cobertura | ≥ 80 % | 60–79 % | < 60 % |
| H3 seguridad | 0 critical/blocker | 1+ major | 1+ critical/blocker |
| H5 quality gate | OK | — | ERROR |

---

## 3. Área A — Membresía

### A1. Altas mensuales y acumulado

- **Decisión:** saber si la iglesia está creciendo y en qué meses se concentran las altas.
- **Fórmula:** `COUNT(*)` de miembros por mes de `fecha_ingreso`; acumulado con suma móvil.
- **SQL:**
```sql
SELECT DATE_FORMAT(m.fecha_ingreso, '%Y-%m') AS mes,
       COUNT(*) AS altas,
       SUM(COUNT(*)) OVER (ORDER BY DATE_FORMAT(m.fecha_ingreso, '%Y-%m')) AS acumulado
FROM miembros m
WHERE m.deleted_at IS NULL
  AND m.fecha_ingreso BETWEEN :desde AND :hasta
GROUP BY mes
ORDER BY mes;
```
- **Laravel:**
```php
$altas = DB::table('miembros')
    ->selectRaw("DATE_FORMAT(fecha_ingreso, '%Y-%m') AS mes, COUNT(*) AS altas")
    ->whereNull('deleted_at')
    ->whereBetween('fecha_ingreso', [$desde, $hasta])
    ->groupBy('mes')
    ->orderBy('mes')
    ->get();

$acumulado = 0;
$altas->each(function ($fila) use (&$acumulado) {
    $acumulado += (int) $fila->altas;
    $fila->acumulado = $acumulado;
});
```
- **Visualización:** línea de altas + área acumulada. Sin semáforo; comparar contra el promedio de 3 meses.
- **Frecuencia/rol:** mensual; Administrador y Secretario.

> **Con más datos:** con menos de 6 meses de historia la línea no muestra tendencia. Mostrar solo barras y el total del período.

### A2. % activos vs. inactivos

- **Decisión:** dimensionar la membresía real y detectar si el estado “Inactivo” está creciendo.
- **Fórmula:** `activos / total * 100`.
- **SQL:**
```sql
SELECT COUNT(*) AS total,
       SUM(CASE WHEN m.estado = 'Activo' THEN 1 ELSE 0 END) AS activos,
       SUM(CASE WHEN m.estado <> 'Activo' OR m.estado IS NULL THEN 1 ELSE 0 END) AS no_activos,
       ROUND(100 * SUM(CASE WHEN m.estado = 'Activo' THEN 1 ELSE 0 END)
             / NULLIF(COUNT(*), 0), 2) AS pct_activos
FROM miembros m
WHERE m.deleted_at IS NULL;
```
- **Laravel:**
```php
$total = Miembro::count();
$activos = Miembro::where('estado', 'Activo')->count();

$pctActivos = $total > 0 ? round(100 * $activos / $total, 2) : null;
```
- **Visualización:** dona activos/inactivos + tarjeta con %. Semáforo según §2.6.
- **Frecuencia/rol:** mensual; Administrador y Secretario.

### A3. Pirámide de edad y sexo

- **Decisión:** planificar discipulado, escuelita, jóvenes y actividades por grupo etario.
- **Fórmula:** conteo por rango etario (`TIMESTAMPDIFF(YEAR, f_nacimiento, hoy)`) y `sexo`.
- **SQL:**
```sql
SELECT CASE
         WHEN m.f_nacimiento IS NULL THEN 'Sin fecha'
         WHEN TIMESTAMPDIFF(YEAR, m.f_nacimiento, :hoy) < 13 THEN '0-12'
         WHEN TIMESTAMPDIFF(YEAR, m.f_nacimiento, :hoy) < 18 THEN '13-17'
         WHEN TIMESTAMPDIFF(YEAR, m.f_nacimiento, :hoy) < 26 THEN '18-25'
         WHEN TIMESTAMPDIFF(YEAR, m.f_nacimiento, :hoy) < 36 THEN '26-35'
         WHEN TIMESTAMPDIFF(YEAR, m.f_nacimiento, :hoy) < 46 THEN '36-45'
         WHEN TIMESTAMPDIFF(YEAR, m.f_nacimiento, :hoy) < 56 THEN '46-55'
         WHEN TIMESTAMPDIFF(YEAR, m.f_nacimiento, :hoy) < 66 THEN '56-65'
         ELSE '66+'
       END AS rango_edad,
       COUNT(*) AS total,
       SUM(CASE WHEN m.sexo = 'M' THEN 1 ELSE 0 END) AS hombres,
       SUM(CASE WHEN m.sexo = 'F' THEN 1 ELSE 0 END) AS mujeres,
       SUM(CASE WHEN m.sexo NOT IN ('M', 'F') OR m.sexo IS NULL THEN 1 ELSE 0 END) AS sin_definir
FROM miembros m
WHERE m.deleted_at IS NULL
GROUP BY rango_edad
ORDER BY FIELD(rango_edad, '0-12', '13-17', '18-25', '26-35',
               '36-45', '46-55', '56-65', '66+', 'Sin fecha');
```
- **Laravel:**
```php
$edades = DB::table('miembros')
    ->selectRaw("CASE
        WHEN f_nacimiento IS NULL THEN 'Sin fecha'
        WHEN TIMESTAMPDIFF(YEAR, f_nacimiento, ?) < 13 THEN '0-12'
        WHEN TIMESTAMPDIFF(YEAR, f_nacimiento, ?) < 18 THEN '13-17'
        WHEN TIMESTAMPDIFF(YEAR, f_nacimiento, ?) < 26 THEN '18-25'
        WHEN TIMESTAMPDIFF(YEAR, f_nacimiento, ?) < 36 THEN '26-35'
        WHEN TIMESTAMPDIFF(YEAR, f_nacimiento, ?) < 46 THEN '36-45'
        WHEN TIMESTAMPDIFF(YEAR, f_nacimiento, ?) < 56 THEN '46-55'
        WHEN TIMESTAMPDIFF(YEAR, f_nacimiento, ?) < 66 THEN '56-65'
        ELSE '66+' END AS rango_edad,
        COUNT(*) AS total,
        SUM(CASE WHEN sexo = 'M' THEN 1 ELSE 0 END) AS hombres,
        SUM(CASE WHEN sexo = 'F' THEN 1 ELSE 0 END) AS mujeres", array_fill(0, 7, $hoy))
    ->whereNull('deleted_at')
    ->groupBy('rango_edad')
    ->get();
```
- **Visualización:** barras apiladas por rango etario (hombres/mujeres) o pirámide. Semáforo solo para “Sin fecha”: rojo si > 20 % de la membresía.
- **Frecuencia/rol:** trimestral; Administrador y Secretario.

> **Con 7 miembros** la distribución es ilustrativa. No tomar decisiones de segmentación hasta tener ≥ 50 miembros.

### A4. Miembros sin ministerio

- **Decisión:** detectar personas activas que no están integradas a ningún ministerio y asignarles acompañamiento.
- **Fórmula:** activos sin membresía vigente en `miembro_ministerio`.
- **SQL:**
```sql
SELECT COUNT(*) AS activos_sin_ministerio
FROM miembros m
WHERE m.deleted_at IS NULL
  AND m.estado = 'Activo'
  AND NOT EXISTS (
      SELECT 1
      FROM miembro_ministerio mm
      WHERE mm.miembro_id = m.id
        AND mm.deleted_at IS NULL
        AND (mm.fecha_fin IS NULL OR mm.fecha_fin >= :hoy)
  );
```
- **Laravel:**
```php
$sinMinisterio = Miembro::where('estado', 'Activo')
    ->whereDoesntHave('ministerios', function ($q) use ($hoy) {
        $q->whereNull('miembro_ministerio.deleted_at')
          ->where(fn ($q) => $q->whereNull('miembro_ministerio.fecha_fin')
                               ->orWhere('miembro_ministerio.fecha_fin', '>=', $hoy));
    })
    ->count();
```
- **Visualización:** tarjeta + listado nominal (tabla). Semáforo: rojo si > 20 % de los activos.
- **Frecuencia/rol:** mensual; Administrador y Secretario.

> **Hoy el pivote está vacío:** el KPI dará 100 %. No publicarlo como alerta hasta que `miembro_ministerio` tenga datos; mostrarlo como “sin datos”.

### A5. Miembros que nunca se inscribieron a un curso

- **Decisión:** medir participación en formación y enfocar la invitación a cursos.
- **Fórmula:** miembros sin ninguna fila en `inscripciones`.
- **SQL:**
```sql
SELECT COUNT(*) AS miembros_sin_curso
FROM miembros m
WHERE m.deleted_at IS NULL
  AND m.estado = 'Activo'
  AND NOT EXISTS (
      SELECT 1
      FROM inscripciones i
      WHERE i.miembro_id = m.id
        AND i.deleted_at IS NULL
  );
```
- **Laravel:**
```php
$sinCurso = Miembro::where('estado', 'Activo')
    ->whereDoesntHave('inscripciones', fn ($q) => $q->whereNull('deleted_at'))
    ->count();
```
- **Visualización:** tarjeta + tabla nominal. Semáforo: rojo si > 50 % de los activos.
- **Frecuencia/rol:** semestral; Administrador y Secretario.

### A6. Retención y bajas

- **Decisión:** saber cuántas personas se van y en qué mes, para actuar sobre retención.
- **Fórmula:** `bajas del período / miembros al inicio * 100`.
- **SQL:** requiere `miembros.fecha_baja` (no existe hoy; ver §7).
```sql
SELECT DATE_FORMAT(m.fecha_baja, '%Y-%m') AS mes,
       COUNT(*) AS bajas,
       ROUND(100 * COUNT(*) / NULLIF((
           SELECT COUNT(*)
           FROM miembros m2
           WHERE m2.deleted_at IS NULL
             AND m2.fecha_ingreso < DATE_FORMAT(m.fecha_baja, '%Y-%m-01')
             AND (m2.fecha_baja IS NULL OR m2.fecha_baja >= DATE_FORMAT(m.fecha_baja, '%Y-%m-01'))
       ), 0), 2) AS tasa_baja_pct
FROM miembros m
WHERE m.deleted_at IS NULL
  AND m.fecha_baja BETWEEN :desde AND :hasta
GROUP BY mes
ORDER BY mes;
```
- **Laravel:** igual que A1, con `whereNotNull('fecha_baja')` y una subconsulta para el denominador.
- **Visualización:** línea de tasa de baja + barras de bajas. Semáforo: verde < 2 % mensual, ámbar 2–5 %, rojo > 5 %.
- **Frecuencia/rol:** mensual; Administrador.

> **Bloqueado:** sin `fecha_baja` no se puede calcular. Mientras tanto, usar A2 (inactivos) como proxy y no etiquetarlo como “retención”.

---

## 4. Área B — Ministerios y actividades

### B1. Miembros activos por ministerio

- **Decisión:** detectar ministerios con poca participación o sobrecargados y balancear equipos.
- **Fórmula:** `COUNT(DISTINCT miembro_id)` con membresía vigente, por ministerio.
- **SQL (esquema vigente):**
```sql
SELECT mi.id, mi.nombre,
       COUNT(DISTINCT mm.miembro_id) AS miembros_activos,
       MAX(li.nombre) AS lider
FROM ministerios mi
LEFT JOIN miembro_ministerio mm
       ON mm.ministerio_id = mi.id
      AND mm.deleted_at IS NULL
      AND (mm.fecha_fin IS NULL OR mm.fecha_fin >= :hoy)
LEFT JOIN lideres_iglesia li
       ON li.id = mi.id_lider_iglesia
      AND li.deleted_at IS NULL
WHERE mi.deleted_at IS NULL
GROUP BY mi.id, mi.nombre
ORDER BY miembros_activos DESC, mi.nombre;
```
- **Laravel (variante `id_lider_iglesia`, la del repositorio actual):**
```php
$porMinisterio = DB::table('ministerios as mi')
    ->leftJoin('miembro_ministerio as mm', function ($j) use ($hoy) {
        $j->on('mm.ministerio_id', '=', 'mi.id')
          ->whereNull('mm.deleted_at')
          ->where(fn ($q) => $q->whereNull('mm.fecha_fin')
                               ->orWhere('mm.fecha_fin', '>=', $hoy));
    })
    ->leftJoin('lideres_iglesia as li', function ($j) {
        $j->on('li.id', '=', 'mi.id_lider_iglesia')->whereNull('li.deleted_at');
    })
    ->whereNull('mi.deleted_at')
    ->select('mi.id', 'mi.nombre', 'li.nombre as lider')
    ->selectRaw('COUNT(DISTINCT mm.miembro_id) AS miembros_activos')
    ->groupBy('mi.id', 'mi.nombre', 'li.nombre')
    ->orderByDesc('miembros_activos')
    ->get();
```
- **Visualización:** barras horizontales. Semáforo: ámbar si 0 miembros; rojo si 0 miembros y 0 actividades en 90 días.
- **Frecuencia/rol:** mensual; Administrador y Secretario.

> **Hoy el pivote está vacío:** todos los ministerios saldrán en 0. Úsalo como KPI de carga cuando empieces a registrar membresías.

### B2. Ministerios sin líder

- **Decisión:** asignar responsabilidad y evitar ministerios huérfanos.
- **Fórmula:** ministerios con `id_lider_iglesia` nulo.
- **SQL (esquema vigente):**
```sql
SELECT mi.id, mi.nombre
FROM ministerios mi
WHERE mi.deleted_at IS NULL
  AND mi.id_lider_iglesia IS NULL
ORDER BY mi.nombre;
```
- **Laravel:**
```php
$sinLider = Ministerio::whereNull('id_lider_iglesia')->orderBy('nombre')->get();
```
- **Visualización:** tarjeta con número + tabla. Semáforo: verde 0, ámbar 1, rojo ≥ 2.
- **Frecuencia/rol:** mensual; Administrador.

> **Esquema vigente:** la migración `2026_10_27_000001` eliminó `ministerios.liderable_type` y `liderable_id`. Usa siempre `id_lider_iglesia`; el esquema polimórfico es histórico y ya no existe en la base.

### B3. Actividades por estado

- **Decisión:** ver si la agenda está equilibrada y si hay actividades estancadas.
- **Fórmula:** `COUNT(*)` agrupado por estado normalizado.
- **SQL:**
```sql
SELECT CASE
         WHEN a.estado IS NULL OR TRIM(a.estado) = '' THEN 'Sin estado'
         WHEN LOWER(a.estado) LIKE 'program%' THEN 'Programado'
         WHEN LOWER(a.estado) LIKE 'en progreso%' OR LOWER(a.estado) LIKE 'en curso%' THEN 'En Progreso'
         WHEN LOWER(a.estado) LIKE 'complet%' OR LOWER(a.estado) LIKE 'finaliz%' THEN 'Completada'
         WHEN LOWER(a.estado) LIKE 'cancel%' THEN 'Cancelada'
         ELSE a.estado
       END AS estado_normalizado,
       COUNT(*) AS total
FROM actividades a
WHERE a.deleted_at IS NULL
  AND a.fecha BETWEEN :desde AND :hasta
GROUP BY estado_normalizado
ORDER BY total DESC;
```
- **Laravel:**
```php
$actividadesPorEstado = Actividad::whereBetween('fecha', [$desde, $hasta])
    ->selectRaw("CASE
        WHEN estado IS NULL OR TRIM(estado) = '' THEN 'Sin estado'
        WHEN LOWER(estado) LIKE 'program%' THEN 'Programado'
        WHEN LOWER(estado) LIKE 'en progreso%' OR LOWER(estado) LIKE 'en curso%' THEN 'En Progreso'
        WHEN LOWER(estado) LIKE 'complet%' OR LOWER(estado) LIKE 'finaliz%' THEN 'Completada'
        WHEN LOWER(estado) LIKE 'cancel%' THEN 'Cancelada'
        ELSE estado END AS estado_normalizado, COUNT(*) AS total")
    ->groupBy('estado_normalizado')
    ->orderByDesc('total')
    ->get();
```
- **Visualización:** dona o barras. Semáforo: rojo si > 30 % en “Programado” con fecha pasada.
- **Frecuencia/rol:** semanal; Administrador y Secretario.

### B4. Avance promedio de actividades

- **Decisión:** saber si los planes avanzan y priorizar las actividades con menor progreso.
- **Fórmula:** promedio del **último** `porcentaje_avance` de cada actividad (no el promedio de todos los registros históricos).
- **SQL:**
```sql
WITH ultimo AS (
    SELECT s.id_actividad,
           s.porcentaje_avance,
           ROW_NUMBER() OVER (
               PARTITION BY s.id_actividad
               ORDER BY s.fecha_registro DESC, s.id DESC
           ) AS rn
    FROM seguimiento_actividades s
    WHERE s.deleted_at IS NULL
)
SELECT a.id,
       a.nombre,
       a.fecha,
       a.estado,
       COALESCE(u.porcentaje_avance, 0) AS avance_actual
FROM actividades a
LEFT JOIN ultimo u
       ON u.id_actividad = a.id
      AND u.rn = 1
WHERE a.deleted_at IS NULL
ORDER BY avance_actual ASC, a.fecha ASC;
```
Para el promedio global:
```sql
WITH ultimo AS (
    SELECT s.id_actividad, s.porcentaje_avance,
           ROW_NUMBER() OVER (PARTITION BY s.id_actividad ORDER BY s.fecha_registro DESC, s.id DESC) AS rn
    FROM seguimiento_actividades s
    WHERE s.deleted_at IS NULL
)
SELECT ROUND(AVG(COALESCE(u.porcentaje_avance, 0)), 2) AS avance_promedio
FROM actividades a
LEFT JOIN ultimo u ON u.id_actividad = a.id AND u.rn = 1
WHERE a.deleted_at IS NULL;
```
- **Laravel:**
```php
$ultimo = DB::table('seguimiento_actividades')
    ->select('id_actividad', 'porcentaje_avance')
    ->selectRaw('ROW_NUMBER() OVER (PARTITION BY id_actividad ORDER BY fecha_registro DESC, id DESC) AS rn')
    ->whereNull('deleted_at');

$avance = DB::table('actividades as a')
    ->leftJoinSub($ultimo, 'u', fn ($j) => $j->on('u.id_actividad', '=', 'a.id')->where('u.rn', 1))
    ->whereNull('a.deleted_at')
    ->select('a.id', 'a.nombre', 'a.fecha', 'a.estado')
    ->selectRaw('COALESCE(u.porcentaje_avance, 0) AS avance_actual')
    ->orderBy('avance_actual')
    ->get();
```
- **Visualización:** barras horizontales por actividad + tarjeta de promedio. Semáforo según §2.6.
- **Frecuencia/rol:** semanal; Administrador y Secretario.

> **Trampa:** promediar todos los seguimientos castiga actividades antiguas con muchos registros. Usar el último por actividad.

### B5. Actividades atrasadas o con avance < 50 %

- **Decisión:** lista de trabajo accionable para el secretario y el pastor.
- **Fórmula:** `fecha < hoy` y estado no final, **o** último avance < 50 %.
- **SQL:**
```sql
WITH ultimo AS (
    SELECT s.id_actividad, s.porcentaje_avance,
           ROW_NUMBER() OVER (PARTITION BY s.id_actividad ORDER BY s.fecha_registro DESC, s.id DESC) AS rn
    FROM seguimiento_actividades s
    WHERE s.deleted_at IS NULL
)
SELECT a.id,
       a.nombre,
       a.fecha,
       a.estado,
       COALESCE(u.porcentaje_avance, 0) AS avance_actual,
       DATEDIFF(:hoy, a.fecha) AS dias_desde_fecha,
       CASE
         WHEN a.fecha < :hoy
          AND LOWER(COALESCE(a.estado, '')) NOT LIKE 'complet%'
          AND LOWER(COALESCE(a.estado, '')) NOT LIKE 'finaliz%'
          AND LOWER(COALESCE(a.estado, '')) NOT LIKE 'cancel%'
         THEN 'Atrasada'
         WHEN COALESCE(u.porcentaje_avance, 0) < 50 THEN 'Avance bajo'
         ELSE 'En riesgo'
       END AS motivo
FROM actividades a
LEFT JOIN ultimo u ON u.id_actividad = a.id AND u.rn = 1
WHERE a.deleted_at IS NULL
  AND (
      (a.fecha < :hoy
       AND LOWER(COALESCE(a.estado, '')) NOT LIKE 'complet%'
       AND LOWER(COALESCE(a.estado, '')) NOT LIKE 'finaliz%'
       AND LOWER(COALESCE(a.estado, '')) NOT LIKE 'cancel%')
      OR COALESCE(u.porcentaje_avance, 0) < 50
  )
ORDER BY dias_desde_fecha DESC, avance_actual ASC;
```
- **Laravel:**
```php
$ultimo = DB::table('seguimiento_actividades')
    ->select('id_actividad', 'porcentaje_avance')
    ->selectRaw('ROW_NUMBER() OVER (PARTITION BY id_actividad ORDER BY fecha_registro DESC, id DESC) AS rn')
    ->whereNull('deleted_at');

$enRiesgo = DB::table('actividades as a')
    ->leftJoinSub($ultimo, 'u', fn ($j) => $j->on('u.id_actividad', '=', 'a.id')->where('u.rn', 1))
    ->whereNull('a.deleted_at')
    ->where(function ($q) use ($hoy) {
        $q->where(function ($q) use ($hoy) {
            $q->where('a.fecha', '<', $hoy)
              ->whereRaw("LOWER(COALESCE(a.estado, '')) NOT LIKE 'complet%'")
              ->whereRaw("LOWER(COALESCE(a.estado, '')) NOT LIKE 'finaliz%'")
              ->whereRaw("LOWER(COALESCE(a.estado, '')) NOT LIKE 'cancel%'");
        })->orWhereRaw('COALESCE(u.porcentaje_avance, 0) < 50');
    })
    ->select('a.*', DB::raw('COALESCE(u.porcentaje_avance, 0) AS avance_actual'))
    ->orderBy('a.fecha')
    ->get();
```
- **Visualización:** tabla con badges (rojo atrasada, ámbar avance bajo) + contador en tarjeta.
- **Frecuencia/rol:** semanal; Administrador y Secretario.

### B6. Ingresos generados por actividad

- **Decisión:** evaluar qué actividades generan recursos y cuáles solo costo.
- **Fórmula:** `SUM(ingresos.monto_total)` por `id_actividad`.
- **SQL:**
```sql
SELECT a.id,
       a.nombre,
       a.fecha,
       mi.nombre AS ministerio,
       COUNT(i.id) AS num_ingresos,
       ROUND(COALESCE(SUM(i.monto_total), 0), 2) AS total_ingresos
FROM actividades a
JOIN ministerios mi
  ON mi.id = a.id_ministerio
 AND mi.deleted_at IS NULL
LEFT JOIN ingresos i
       ON i.id_actividad = a.id
      AND i.deleted_at IS NULL
WHERE a.deleted_at IS NULL
  AND a.fecha BETWEEN :desde AND :hasta
GROUP BY a.id, a.nombre, a.fecha, mi.nombre
ORDER BY total_ingresos DESC;
```
- **Laravel:**
```php
$ingresosPorActividad = DB::table('actividades as a')
    ->join('ministerios as mi', function ($j) {
        $j->on('mi.id', '=', 'a.id_ministerio')->whereNull('mi.deleted_at');
    })
    ->leftJoin('ingresos as i', function ($j) {
        $j->on('i.id_actividad', '=', 'a.id')->whereNull('i.deleted_at');
    })
    ->whereNull('a.deleted_at')
    ->whereBetween('a.fecha', [$desde, $hasta])
    ->select('a.id', 'a.nombre', 'a.fecha', 'mi.nombre as ministerio')
    ->selectRaw('COUNT(i.id) AS num_ingresos, ROUND(COALESCE(SUM(i.monto_total), 0), 2) AS total_ingresos')
    ->groupBy('a.id', 'a.nombre', 'a.fecha', 'mi.nombre')
    ->orderByDesc('total_ingresos')
    ->get();
```
- **Visualización:** barras horizontales. Sin semáforo; usar junto con costos cuando existan (B8 avanzado).
- **Frecuencia/rol:** mensual; Tesorero y Administrador.

> **No incluye costos:** `actividades` no tiene `presupuesto` ni `costo_real`. Sin ellos no se puede calcular ROI; ver §7.

### B7. Actividades sin seguimiento

- **Decisión:** detectar actividades que se programaron pero nadie está midiendo.
- **Fórmula:** actividades sin ninguna fila en `seguimiento_actividades`.
- **SQL:**
```sql
SELECT a.id, a.nombre, a.fecha, a.estado, mi.nombre AS ministerio
FROM actividades a
JOIN ministerios mi
  ON mi.id = a.id_ministerio
 AND mi.deleted_at IS NULL
WHERE a.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM seguimiento_actividades s
      WHERE s.id_actividad = a.id
        AND s.deleted_at IS NULL
  )
ORDER BY a.fecha ASC;
```
- **Laravel:**
```php
$sinSeguimiento = Actividad::whereDoesntHave('seguimientos', fn ($q) => $q->whereNull('deleted_at'))
    ->with('ministerio')
    ->orderBy('fecha')
    ->get();
```
- **Visualización:** tabla + contador. Semáforo: rojo si hay actividades pasadas sin seguimiento.
- **Frecuencia/rol:** semanal; Secretario.

---

## 5. Área C — Academia

### C1. Ocupación de cupo

- **Decisión:** decidir si abrir un curso, cerrar inscripciones o lanzar una campaña de captación.
- **Fórmula:** `inscripciones activas / cupo_max * 100`.
- **SQL:**
```sql
SELECT c.id,
       c.nombre,
       c.cupo_max,
       COUNT(DISTINCT i.id) AS inscritos_activos,
       ROUND(100 * COUNT(DISTINCT i.id) / NULLIF(c.cupo_max, 0), 2) AS pct_ocupacion,
       GREATEST(c.cupo_max - COUNT(DISTINCT i.id), 0) AS cupos_libres
FROM cursos c
LEFT JOIN inscripciones i
       ON i.curso_id = c.id
      AND i.deleted_at IS NULL
      AND i.estado = 'Activo'
WHERE c.deleted_at IS NULL
GROUP BY c.id, c.nombre, c.cupo_max
ORDER BY pct_ocupacion DESC, c.nombre;
```
- **Laravel:**
```php
$ocupacion = DB::table('cursos as c')
    ->leftJoin('inscripciones as i', function ($j) {
        $j->on('i.curso_id', '=', 'c.id')
          ->whereNull('i.deleted_at')
          ->where('i.estado', 'Activo');
    })
    ->whereNull('c.deleted_at')
    ->select('c.id', 'c.nombre', 'c.cupo_max')
    ->selectRaw('COUNT(DISTINCT i.id) AS inscritos_activos')
    ->selectRaw('ROUND(100 * COUNT(DISTINCT i.id) / NULLIF(c.cupo_max, 0), 2) AS pct_ocupacion')
    ->selectRaw('GREATEST(c.cupo_max - COUNT(DISTINCT i.id), 0) AS cupos_libres')
    ->groupBy('c.id', 'c.nombre', 'c.cupo_max')
    ->orderByDesc('pct_ocupacion')
    ->get();
```
- **Visualización:** barras horizontales por curso + tarjeta de ocupación global ponderada. Semáforo según §2.6. Si `pct_ocupacion > 100`, revisar `cupo_max` o inscripciones duplicadas.
- **Frecuencia/rol:** semanal; Administrador, Secretario y Docente (solo sus cursos).

> **Cálculo global:** no promedies porcentajes; usa `SUM(inscritos) / SUM(cupo_max)` para ponderar por capacidad.

### C2. % de asistencia por curso

- **Decisión:** detectar cursos con baja participación y comparar docentes/cursos.
- **Fórmula:** `(presentes + tardanzas) / registros únicos * 100`.
- **SQL:**
```sql
WITH asistencia_ordenada AS (
    SELECT a.sesion_id,
           a.miembro_id,
           a.estado,
           ROW_NUMBER() OVER (
               PARTITION BY a.sesion_id, a.miembro_id
               ORDER BY a.id DESC
           ) AS rn
    FROM asistencias_sesion a
    WHERE a.deleted_at IS NULL
),
asistencia_unica AS (
    SELECT sesion_id, miembro_id, estado
    FROM asistencia_ordenada
    WHERE rn = 1
)
SELECT c.id,
       c.nombre,
       COUNT(*) AS registros,
       SUM(CASE WHEN au.estado = 'Presente' THEN 1 ELSE 0 END) AS presentes,
       SUM(CASE WHEN au.estado = 'Tardanza' THEN 1 ELSE 0 END) AS tardanzas,
       SUM(CASE WHEN au.estado = 'Ausente' THEN 1 ELSE 0 END) AS ausentes,
       SUM(CASE WHEN au.estado = 'Justificado' THEN 1 ELSE 0 END) AS justificados,
       ROUND(100 * SUM(CASE WHEN au.estado IN ('Presente', 'Tardanza') THEN 1 ELSE 0 END)
             / NULLIF(COUNT(*), 0), 2) AS pct_asistencia,
       ROUND(100 * SUM(CASE WHEN au.estado = 'Ausente' THEN 1 ELSE 0 END)
             / NULLIF(COUNT(*), 0), 2) AS pct_ausentismo
FROM cursos c
JOIN sesiones s
  ON s.id_curso = c.id
 AND s.deleted_at IS NULL
JOIN asistencia_unica au
  ON au.sesion_id = s.id
WHERE c.deleted_at IS NULL
GROUP BY c.id, c.nombre
ORDER BY pct_asistencia DESC;
```
- **Laravel:**
```php
$asistenciaUnica = DB::table('asistencias_sesion')
    ->select('sesion_id', 'miembro_id', 'estado')
    ->selectRaw('ROW_NUMBER() OVER (PARTITION BY sesion_id, miembro_id ORDER BY id DESC) AS rn')
    ->whereNull('deleted_at');

$asistenciaPorCurso = DB::table('cursos as c')
    ->join('sesiones as s', function ($j) {
        $j->on('s.id_curso', '=', 'c.id')->whereNull('s.deleted_at');
    })
    ->joinSub($asistenciaUnica, 'au', function ($j) {
        $j->on('au.sesion_id', '=', 's.id')->where('au.rn', 1);
    })
    ->whereNull('c.deleted_at')
    ->select('c.id', 'c.nombre')
    ->selectRaw('COUNT(*) AS registros')
    ->selectRaw("SUM(CASE WHEN au.estado = 'Presente' THEN 1 ELSE 0 END) AS presentes")
    ->selectRaw("SUM(CASE WHEN au.estado = 'Tardanza' THEN 1 ELSE 0 END) AS tardanzas")
    ->selectRaw("SUM(CASE WHEN au.estado = 'Ausente' THEN 1 ELSE 0 END) AS ausentes")
    ->selectRaw("ROUND(100 * SUM(CASE WHEN au.estado IN ('Presente','Tardanza') THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0), 2) AS pct_asistencia")
    ->groupBy('c.id', 'c.nombre')
    ->orderByDesc('pct_asistencia')
    ->get();
```
- **Visualización:** barras por curso con línea de meta en 85 %. Semáforo según §2.6.
- **Frecuencia/rol:** semanal; Administrador, Secretario y Docente (solo sus cursos).

> **Denominador:** se usan registros únicos de asistencia, no “inscripciones × sesiones”. Si la asistencia no se registra para todos, el porcentaje mide la calidad del registro, no la asistencia real. Para eso está C7 (avanzado).

### C3. Ausentismo por sesión

- **Decisión:** identificar sesiones o fechas con alta deserción y revisar horario, tema o docente.
- **Fórmula:** `ausentes / registros únicos * 100` por sesión.
- **SQL:**
```sql
WITH asistencia_ordenada AS (
    SELECT a.sesion_id, a.miembro_id, a.estado,
           ROW_NUMBER() OVER (PARTITION BY a.sesion_id, a.miembro_id ORDER BY a.id DESC) AS rn
    FROM asistencias_sesion a
    WHERE a.deleted_at IS NULL
),
asistencia_unica AS (
    SELECT sesion_id, miembro_id, estado
    FROM asistencia_ordenada
    WHERE rn = 1
)
SELECT s.id AS sesion_id,
       c.nombre AS curso,
       s.fecha,
       s.tema,
       COUNT(*) AS registros,
       SUM(CASE WHEN au.estado = 'Ausente' THEN 1 ELSE 0 END) AS ausentes,
       ROUND(100 * SUM(CASE WHEN au.estado = 'Ausente' THEN 1 ELSE 0 END)
             / NULLIF(COUNT(*), 0), 2) AS pct_ausentismo
FROM sesiones s
JOIN cursos c
  ON c.id = s.id_curso
 AND c.deleted_at IS NULL
JOIN asistencia_unica au
  ON au.sesion_id = s.id
WHERE s.deleted_at IS NULL
  AND s.fecha BETWEEN :desde AND :hasta
GROUP BY s.id, c.nombre, s.fecha, s.tema
ORDER BY pct_ausentismo DESC, s.fecha DESC;
```
- **Laravel:**
```php
$ausentismo = DB::table('sesiones as s')
    ->join('cursos as c', function ($j) {
        $j->on('c.id', '=', 's.id_curso')->whereNull('c.deleted_at');
    })
    ->joinSub($asistenciaUnica, 'au', function ($j) {
        $j->on('au.sesion_id', '=', 's.id')->where('au.rn', 1);
    })
    ->whereNull('s.deleted_at')
    ->whereBetween('s.fecha', [$desde, $hasta])
    ->select('s.id as sesion_id', 'c.nombre as curso', 's.fecha', 's.tema')
    ->selectRaw('COUNT(*) AS registros')
    ->selectRaw("SUM(CASE WHEN au.estado = 'Ausente' THEN 1 ELSE 0 END) AS ausentes")
    ->selectRaw("ROUND(100 * SUM(CASE WHEN au.estado = 'Ausente' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0), 2) AS pct_ausentismo")
    ->groupBy('s.id', 'c.nombre', 's.fecha', 's.tema')
    ->orderByDesc('pct_ausentismo')
    ->get();
```
- **Visualización:** barras por sesión (últimas 10) + tabla. Semáforo: verde < 10 %, ámbar 10–20 %, rojo > 20 %.
- **Frecuencia/rol:** semanal; Docente y Secretario.

### C4. Cursos con baja ocupación

- **Decisión:** cancelar, fusionar o promocionar cursos antes de que inicien.
- **Fórmula:** cursos con `pct_ocupacion < 50 %` (o con más de 50 % de cupos libres).
- **SQL:**
```sql
SELECT c.id,
       c.nombre,
       c.cupo_max,
       COUNT(DISTINCT i.id) AS inscritos_activos,
       ROUND(100 * COUNT(DISTINCT i.id) / NULLIF(c.cupo_max, 0), 2) AS pct_ocupacion,
       GREATEST(c.cupo_max - COUNT(DISTINCT i.id), 0) AS cupos_libres
FROM cursos c
LEFT JOIN inscripciones i
       ON i.curso_id = c.id
      AND i.deleted_at IS NULL
      AND i.estado = 'Activo'
WHERE c.deleted_at IS NULL
GROUP BY c.id, c.nombre, c.cupo_max
HAVING pct_ocupacion < 50
ORDER BY pct_ocupacion ASC;
```
- **Laravel:**
```php
$bajaOcupacion = DB::table('cursos as c')
    ->leftJoin('inscripciones as i', function ($j) {
        $j->on('i.curso_id', '=', 'c.id')
          ->whereNull('i.deleted_at')
          ->where('i.estado', 'Activo');
    })
    ->whereNull('c.deleted_at')
    ->select('c.id', 'c.nombre', 'c.cupo_max')
    ->selectRaw('COUNT(DISTINCT i.id) AS inscritos_activos')
    ->selectRaw('ROUND(100 * COUNT(DISTINCT i.id) / NULLIF(c.cupo_max, 0), 2) AS pct_ocupacion')
    ->groupBy('c.id', 'c.nombre', 'c.cupo_max')
    ->havingRaw('pct_ocupacion < 50')
    ->orderBy('pct_ocupacion')
    ->get();
```
- **Visualización:** tabla con badge rojo + contador. Semáforo: verde 0, ámbar 1, rojo ≥ 2.
- **Frecuencia/rol:** semanal; Administrador y Secretario.

### C5. Carga por docente

- **Decisión:** equilibrar cursos y detectar docentes sobrecargados o sin asignación.
- **Fórmula:** cursos distintos, sesiones del período y horas semanales por docente.
- **SQL:**
```sql
WITH asignaciones AS (
    SELECT dc.docente_id, dc.curso_id
    FROM docente_curso dc
    WHERE dc.deleted_at IS NULL
    UNION
    SELECT s.id_docente AS docente_id, s.id_curso AS curso_id
    FROM sesiones s
    WHERE s.deleted_at IS NULL
      AND s.id_docente IS NOT NULL
),
cursos_docente AS (
    SELECT a.docente_id,
           a.curso_id,
           MAX(c.hora_inicio) AS hora_inicio,
           MAX(c.hora_fin) AS hora_fin
    FROM asignaciones a
    JOIN cursos c
      ON c.id = a.curso_id
     AND c.deleted_at IS NULL
    GROUP BY a.docente_id, a.curso_id
)
SELECT d.id,
       m.nombre AS docente,
       COUNT(DISTINCT cd.curso_id) AS cursos,
       ROUND(COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(cd.hora_fin, cd.hora_inicio))) / 3600, 0), 2) AS horas_semana,
       (SELECT COUNT(*)
        FROM sesiones s2
        WHERE s2.id_docente = d.id
          AND s2.deleted_at IS NULL
          AND s2.fecha BETWEEN :desde AND :hasta) AS sesiones_periodo
FROM docentes d
JOIN miembros m
  ON m.id = d.id_miembro
 AND m.deleted_at IS NULL
LEFT JOIN cursos_docente cd
       ON cd.docente_id = d.id
WHERE d.deleted_at IS NULL
  AND d.activo = 1
GROUP BY d.id, m.nombre
ORDER BY cursos DESC, horas_semana DESC;
```
- **Laravel:**
```php
$asignaciones = DB::table('docente_curso')
    ->select('docente_id', 'curso_id')
    ->whereNull('deleted_at')
    ->unionAll(
        DB::table('sesiones')
            ->select('id_docente as docente_id', 'id_curso as curso_id')
            ->whereNull('deleted_at')
            ->whereNotNull('id_docente')
    );

$carga = DB::table('docentes as d')
    ->join('miembros as m', function ($j) {
        $j->on('m.id', '=', 'd.id_miembro')->whereNull('m.deleted_at');
    })
    ->leftJoinSub($asignaciones, 'a', fn ($j) => $j->on('a.docente_id', '=', 'd.id'))
    ->leftJoin('cursos as c', function ($j) {
        $j->on('c.id', '=', 'a.curso_id')->whereNull('c.deleted_at');
    })
    ->whereNull('d.deleted_at')
    ->where('d.activo', 1)
    ->select('d.id', 'm.nombre as docente')
    ->selectRaw('COUNT(DISTINCT a.curso_id) AS cursos')
    ->selectRaw("ROUND(COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(c.hora_fin, c.hora_inicio))) / 3600, 0), 2) AS horas_semana")
    ->groupBy('d.id', 'm.nombre')
    ->orderByDesc('cursos')
    ->get();
```
- **Visualización:** barras por docente. Semáforo: rojo si 0 cursos, ámbar si > 20 horas semanales (ajustar según política).
- **Frecuencia/rol:** mensual; Administrador y Secretario.

> **Hoy `docente_curso` está vacía**, pero `sesiones.id_docente` sí tiene datos. El `UNION` evita depender de una sola ruta. No sumes ambas rutas sin `UNION`: duplicarías cursos.

### C6. Alumnos en riesgo

- **Decisión:** intervenir antes de que el alumno abandone el curso.
- **Fórmula:** alumnos con asistencia < 75 % sobre sesiones realizadas.
- **SQL:**
```sql
WITH asistencia_ordenada AS (
    SELECT a.sesion_id, a.miembro_id, a.estado,
           ROW_NUMBER() OVER (PARTITION BY a.sesion_id, a.miembro_id ORDER BY a.id DESC) AS rn
    FROM asistencias_sesion a
    WHERE a.deleted_at IS NULL
),
asistencia_unica AS (
    SELECT sesion_id, miembro_id, estado
    FROM asistencia_ordenada
    WHERE rn = 1
)
SELECT i.id AS inscripcion_id,
       m.nombre AS alumno,
       c.nombre AS curso,
       COUNT(DISTINCT s.id) AS sesiones_realizadas,
       COUNT(DISTINCT CASE WHEN au.estado IN ('Presente', 'Tardanza') THEN s.id END) AS asistidas,
       ROUND(100 * COUNT(DISTINCT CASE WHEN au.estado IN ('Presente', 'Tardanza') THEN s.id END)
             / NULLIF(COUNT(DISTINCT s.id), 0), 2) AS pct_asistencia
FROM inscripciones i
JOIN miembros m
  ON m.id = i.miembro_id
 AND m.deleted_at IS NULL
JOIN cursos c
  ON c.id = i.curso_id
 AND c.deleted_at IS NULL
JOIN sesiones s
  ON s.id_curso = c.id
 AND s.deleted_at IS NULL
 AND s.fecha <= :hoy
LEFT JOIN asistencia_unica au
       ON au.sesion_id = s.id
      AND au.miembro_id = i.miembro_id
WHERE i.deleted_at IS NULL
  AND i.estado = 'Activo'
GROUP BY i.id, m.nombre, c.nombre
HAVING pct_asistencia < 75
ORDER BY pct_asistencia ASC, m.nombre;
```
- **Laravel:**
```php
$enRiesgo = DB::table('inscripciones as i')
    ->join('miembros as m', function ($j) {
        $j->on('m.id', '=', 'i.miembro_id')->whereNull('m.deleted_at');
    })
    ->join('cursos as c', function ($j) {
        $j->on('c.id', '=', 'i.curso_id')->whereNull('c.deleted_at');
    })
    ->join('sesiones as s', function ($j) use ($hoy) {
        $j->on('s.id_curso', '=', 'c.id')
          ->whereNull('s.deleted_at')
          ->where('s.fecha', '<=', $hoy);
    })
    ->leftJoinSub($asistenciaUnica, 'au', function ($j) {
        $j->on('au.sesion_id', '=', 's.id')->on('au.miembro_id', '=', 'i.miembro_id');
    })
    ->whereNull('i.deleted_at')
    ->where('i.estado', 'Activo')
    ->select('i.id as inscripcion_id', 'm.nombre as alumno', 'c.nombre as curso')
    ->selectRaw('COUNT(DISTINCT s.id) AS sesiones_realizadas')
    ->selectRaw("COUNT(DISTINCT CASE WHEN au.estado IN ('Presente','Tardanza') THEN s.id END) AS asistidas")
    ->selectRaw("ROUND(100 * COUNT(DISTINCT CASE WHEN au.estado IN ('Presente','Tardanza') THEN s.id END) / NULLIF(COUNT(DISTINCT s.id), 0), 2) AS pct_asistencia")
    ->groupBy('i.id', 'm.nombre', 'c.nombre')
    ->havingRaw('pct_asistencia < 75')
    ->orderBy('pct_asistencia')
    ->get();
```
- **Visualización:** tabla con semáforo rojo (< 60 %), ámbar (60–74 %). Contador en el dashboard.
- **Frecuencia/rol:** semanal; Docente (sus cursos) y Secretario.

> **Supuesto:** una sesión realizada sin registro de asistencia cuenta como no asistida. Eso es deliberado para priorizar el seguimiento; si el registro está incompleto, primero corregir C7.

---

## 6. Área D — Finanzas

### D1. Ingresos vs. egresos y saldo por mes

- **Decisión:** saber si la iglesia gasta más de lo que recibe y en qué meses.
- **Fórmula:** `SUM(ingresos)`, `SUM(egresos)`, `ingresos - egresos` por mes.
- **SQL:**
```sql
WITH ingresos_mes AS (
    SELECT DATE_FORMAT(COALESCE(i.fecha, pc.fecha_pago), '%Y-%m') AS mes,
           SUM(COALESCE(i.monto_total, pc.monto)) AS total
    FROM ingresos i
    LEFT JOIN pago_cursos pc
           ON pc.id = i.pago_curso_id
          AND pc.deleted_at IS NULL
    WHERE i.deleted_at IS NULL
      AND COALESCE(i.fecha, pc.fecha_pago) BETWEEN :desde AND :hasta
    GROUP BY mes
),
egresos_mes AS (
    SELECT DATE_FORMAT(e.fecha, '%Y-%m') AS mes,
           SUM(e.monto) AS total
    FROM egresos e
    WHERE e.deleted_at IS NULL
      AND e.fecha BETWEEN :desde AND :hasta
    GROUP BY mes
),
meses AS (
    SELECT mes FROM ingresos_mes
    UNION
    SELECT mes FROM egresos_mes
)
SELECT m.mes,
       ROUND(COALESCE(im.total, 0), 2) AS ingresos,
       ROUND(COALESCE(em.total, 0), 2) AS egresos,
       ROUND(COALESCE(im.total, 0) - COALESCE(em.total, 0), 2) AS saldo
FROM meses m
LEFT JOIN ingresos_mes im ON im.mes = m.mes
LEFT JOIN egresos_mes em ON em.mes = m.mes
ORDER BY m.mes;
```
- **Laravel (dos consultas + merge en PHP):**
```php
$ingresosMes = DB::table('ingresos as i')
    ->leftJoin('pago_cursos as pc', function ($j) {
        $j->on('pc.id', '=', 'i.pago_curso_id')->whereNull('pc.deleted_at');
    })
    ->whereNull('i.deleted_at')
    ->whereBetween(DB::raw('COALESCE(i.fecha, pc.fecha_pago)'), [$desde, $hasta])
    ->selectRaw("DATE_FORMAT(COALESCE(i.fecha, pc.fecha_pago), '%Y-%m') AS mes")
    ->selectRaw('SUM(COALESCE(i.monto_total, pc.monto)) AS total')
    ->groupBy('mes')
    ->pluck('total', 'mes');

$egresosMes = DB::table('egresos')
    ->whereNull('deleted_at')
    ->whereBetween('fecha', [$desde, $hasta])
    ->selectRaw("DATE_FORMAT(fecha, '%Y-%m') AS mes, SUM(monto) AS total")
    ->groupBy('mes')
    ->pluck('total', 'mes');

$meses = $ingresosMes->keys()->merge($egresosMes->keys())->unique()->sort();
$flujo = $meses->map(fn ($mes) => [
    'mes' => $mes,
    'ingresos' => round((float) ($ingresosMes[$mes] ?? 0), 2),
    'egresos' => round((float) ($egresosMes[$mes] ?? 0), 2),
    'saldo' => round((float) ($ingresosMes[$mes] ?? 0) - (float) ($egresosMes[$mes] ?? 0), 2),
]);
```
- **Visualización:** columnas agrupadas (ingresos/egresos) + línea de saldo. Semáforo del saldo: verde ≥ 0, rojo < 0.
- **Frecuencia/rol:** mensual; Tesorero y Administrador.

> **Doble conteo:** el `LEFT JOIN pago_cursos` es 1:1 por el UNIQUE `ingresos.pago_curso_id`. No se suman dos veces; solo se resuelve el monto cuando `ingresos.monto_total` es `NULL`.

### D2. Diezmos y ofrendas por culto

- **Decisión:** ver qué culto sostiene la recaudación y si los diezmos/ ofrendas están creciendo.
- **Fórmula:** `SUM` de ingresos por tipo normalizado (`Diezmo%`, `Ofrenda%`) agrupado por culto.
- **SQL:**
```sql
SELECT cu.id,
       cu.nombre,
       cu.dia_semana,
       COUNT(DISTINCT i.fecha) AS servicios_con_ingreso,
       ROUND(SUM(CASE WHEN i.tipo LIKE 'Diezmo%' THEN COALESCE(i.monto_total, pc.monto) ELSE 0 END), 2) AS diezmos,
       ROUND(SUM(CASE WHEN i.tipo LIKE 'Ofrenda%' THEN COALESCE(i.monto_total, pc.monto) ELSE 0 END), 2) AS ofrendas,
       ROUND(SUM(COALESCE(i.monto_total, pc.monto)), 2) AS total_culto
FROM cultos cu
LEFT JOIN ingresos i
       ON i.id_culto = cu.id
      AND i.deleted_at IS NULL
      AND i.fecha BETWEEN :desde AND :hasta
LEFT JOIN pago_cursos pc
       ON pc.id = i.pago_curso_id
      AND pc.deleted_at IS NULL
WHERE cu.deleted_at IS NULL
GROUP BY cu.id, cu.nombre, cu.dia_semana
ORDER BY total_culto DESC;
```
- **Laravel:**
```php
$porCulto = DB::table('cultos as cu')
    ->leftJoin('ingresos as i', function ($j) use ($desde, $hasta) {
        $j->on('i.id_culto', '=', 'cu.id')
          ->whereNull('i.deleted_at')
          ->whereBetween('i.fecha', [$desde, $hasta]);
    })
    ->leftJoin('pago_cursos as pc', function ($j) {
        $j->on('pc.id', '=', 'i.pago_curso_id')->whereNull('pc.deleted_at');
    })
    ->whereNull('cu.deleted_at')
    ->select('cu.id', 'cu.nombre', 'cu.dia_semana')
    ->selectRaw('COUNT(DISTINCT i.fecha) AS servicios_con_ingreso')
    ->selectRaw("ROUND(SUM(CASE WHEN i.tipo LIKE 'Diezmo%' THEN COALESCE(i.monto_total, pc.monto) ELSE 0 END), 2) AS diezmos")
    ->selectRaw("ROUND(SUM(CASE WHEN i.tipo LIKE 'Ofrenda%' THEN COALESCE(i.monto_total, pc.monto) ELSE 0 END), 2) AS ofrendas")
    ->selectRaw('ROUND(SUM(COALESCE(i.monto_total, pc.monto)), 2) AS total_culto')
    ->groupBy('cu.id', 'cu.nombre', 'cu.dia_semana')
    ->orderByDesc('total_culto')
    ->get();
```
- **Visualización:** barras apiladas (diezmo/ofrenda) por culto + tabla. Semáforo: rojo si un culto concentra > 60 % del total (dependencia).
- **Frecuencia/rol:** mensual; Tesorero y Administrador.

> **Trampa:** `tipo` es texto libre. `LIKE 'Diezmo%'` atrapa “Diezmo”, “Diezmos”, etc. Validar con `SELECT DISTINCT tipo` y migrar a catálogo (§7).

### D3. Composición por tipo normalizado

- **Decisión:** entender de dónde vienen los ingresos y en qué se gastan; detectar dependencia de una sola fuente.
- **Fórmula:** `% = total del tipo / total del período * 100`.
- **SQL — ingresos:**
```sql
SELECT CASE
         WHEN i.tipo LIKE 'Diezmo%' THEN 'Diezmo'
         WHEN i.tipo LIKE 'Ofrenda%' THEN 'Ofrenda'
         WHEN i.tipo LIKE 'Donaci%' THEN 'Donación'
         WHEN i.tipo LIKE 'Inscripci%' THEN 'Curso'
         WHEN i.tipo LIKE '%Actividad%' THEN 'Actividad'
         ELSE COALESCE(NULLIF(TRIM(i.tipo), ''), 'Sin tipo')
       END AS tipo_normalizado,
       COUNT(*) AS movimientos,
       ROUND(SUM(COALESCE(i.monto_total, pc.monto)), 2) AS total,
       ROUND(100 * SUM(COALESCE(i.monto_total, pc.monto))
             / NULLIF(SUM(SUM(COALESCE(i.monto_total, pc.monto))) OVER (), 0), 2) AS pct
FROM ingresos i
LEFT JOIN pago_cursos pc
       ON pc.id = i.pago_curso_id
      AND pc.deleted_at IS NULL
WHERE i.deleted_at IS NULL
  AND COALESCE(i.fecha, pc.fecha_pago) BETWEEN :desde AND :hasta
GROUP BY tipo_normalizado
ORDER BY total DESC;
```
**SQL — egresos:**
```sql
SELECT CASE
         WHEN e.id_contrato IS NOT NULL OR e.tipo_egreso LIKE 'Salario%' OR e.tipo_egreso LIKE 'Sueldo%' THEN 'Personal'
         WHEN e.tipo_egreso LIKE 'Mantenimiento%' THEN 'Mantenimiento'
         WHEN e.tipo_egreso LIKE 'Servicio%' THEN 'Servicios'
         WHEN e.tipo_egreso LIKE 'Ayuda Social%' THEN 'Ayuda Social'
         ELSE COALESCE(NULLIF(TRIM(e.tipo_egreso), ''), 'Sin tipo')
       END AS tipo_normalizado,
       COUNT(*) AS movimientos,
       ROUND(SUM(e.monto), 2) AS total,
       ROUND(100 * SUM(e.monto) / NULLIF(SUM(SUM(e.monto)) OVER (), 0), 2) AS pct
FROM egresos e
WHERE e.deleted_at IS NULL
  AND e.fecha BETWEEN :desde AND :hasta
GROUP BY tipo_normalizado
ORDER BY total DESC;
```
- **Laravel:**
```php
$composicionIngresos = DB::table('ingresos as i')
    ->leftJoin('pago_cursos as pc', function ($j) {
        $j->on('pc.id', '=', 'i.pago_curso_id')->whereNull('pc.deleted_at');
    })
    ->whereNull('i.deleted_at')
    ->whereBetween(DB::raw('COALESCE(i.fecha, pc.fecha_pago)'), [$desde, $hasta])
    ->selectRaw("CASE
        WHEN i.tipo LIKE 'Diezmo%' THEN 'Diezmo'
        WHEN i.tipo LIKE 'Ofrenda%' THEN 'Ofrenda'
        WHEN i.tipo LIKE 'Donaci%' THEN 'Donación'
        WHEN i.tipo LIKE 'Inscripci%' THEN 'Curso'
        WHEN i.tipo LIKE '%Actividad%' THEN 'Actividad'
        ELSE COALESCE(NULLIF(TRIM(i.tipo), ''), 'Sin tipo') END AS tipo_normalizado")
    ->selectRaw('COUNT(*) AS movimientos')
    ->selectRaw('ROUND(SUM(COALESCE(i.monto_total, pc.monto)), 2) AS total')
    ->groupBy('tipo_normalizado')
    ->orderByDesc('total')
    ->get();

$totalIngresos = $composicionIngresos->sum('total');
$composicionIngresos->each(function ($fila) use ($totalIngresos) {
    $fila->pct = $totalIngresos > 0 ? round(100 * $fila->total / $totalIngresos, 2) : null;
});
```
- **Visualización:** dona de ingresos y dona/torta de egresos. Semáforo de concentración: rojo si un tipo > 70 % del total.
- **Frecuencia/rol:** mensual; Tesorero y Administrador.

> **Mejora:** crear `categorias_financieras` y reemplazar el `CASE` por `categoria_id` (§7). El `CASE` es un parche mientras el texto siga libre.

### D4. % de egresos de personal sobre ingresos

- **Decisión:** saber si la estructura de personal es sostenible.
- **Fórmula:** `egresos de personal / ingresos totales * 100`.
- **SQL:**
```sql
WITH ingresos_totales AS (
    SELECT SUM(COALESCE(i.monto_total, pc.monto)) AS total
    FROM ingresos i
    LEFT JOIN pago_cursos pc
           ON pc.id = i.pago_curso_id
          AND pc.deleted_at IS NULL
    WHERE i.deleted_at IS NULL
      AND COALESCE(i.fecha, pc.fecha_pago) BETWEEN :desde AND :hasta
),
personal AS (
    SELECT SUM(e.monto) AS total
    FROM egresos e
    WHERE e.deleted_at IS NULL
      AND e.fecha BETWEEN :desde AND :hasta
      AND (
          e.id_contrato IS NOT NULL
          OR e.tipo_egreso LIKE 'Salario%'
          OR e.tipo_egreso LIKE 'Sueldo%'
          OR e.tipo_egreso LIKE 'Honorario%'
          OR e.tipo_egreso LIKE 'Planilla%'
      )
)
SELECT ROUND(it.total, 2) AS ingresos,
       ROUND(COALESCE(p.total, 0), 2) AS egresos_personal,
       ROUND(100 * COALESCE(p.total, 0) / NULLIF(it.total, 0), 2) AS pct_personal
FROM ingresos_totales it
CROSS JOIN personal p;
```
- **Laravel:**
```php
$ingresos = (float) DB::table('ingresos as i')
    ->leftJoin('pago_cursos as pc', function ($j) {
        $j->on('pc.id', '=', 'i.pago_curso_id')->whereNull('pc.deleted_at');
    })
    ->whereNull('i.deleted_at')
    ->whereBetween(DB::raw('COALESCE(i.fecha, pc.fecha_pago)'), [$desde, $hasta])
    ->sum(DB::raw('COALESCE(i.monto_total, pc.monto)'));

$personal = (float) DB::table('egresos')
    ->whereNull('deleted_at')
    ->whereBetween('fecha', [$desde, $hasta])
    ->where(function ($q) {
        $q->whereNotNull('id_contrato')
          ->orWhere('tipo_egreso', 'like', 'Salario%')
          ->orWhere('tipo_egreso', 'like', 'Sueldo%')
          ->orWhere('tipo_egreso', 'like', 'Honorario%')
          ->orWhere('tipo_egreso', 'like', 'Planilla%');
    })
    ->sum('monto');

$pctPersonal = $ingresos > 0 ? round(100 * $personal / $ingresos, 2) : null;
```
- **Visualización:** tarjeta con % + línea histórica. Semáforo según §2.6 (ajustar con el tesorero; en iglesias suele ser 40–60 %).
- **Frecuencia/rol:** mensual; Tesorero y Administrador.

> **Pagos directos a líderes:** si un egreso tiene `id_lider_iglesia` pero **no** `id_contrato`, solo se considera personal cuando `tipo_egreso` es salario/honorario/planilla. Si el negocio considera que todo pago a líder es compensación, agregar esa condición y documentarlo.

### D5. Ingresos sin origen

- **Decisión:** detectar dinero que entró sin trazabilidad (posible donación suelta, error de registro o falta de imputación).
- **Fórmula:** ingresos con `id_actividad`, `id_culto` y `pago_curso_id` nulos; monto y % del total.
- **SQL:**
```sql
SELECT COUNT(*) AS cantidad,
       ROUND(SUM(COALESCE(i.monto_total, pc.monto)), 2) AS monto,
       ROUND(100 * COUNT(*) / NULLIF((
           SELECT COUNT(*)
           FROM ingresos i2
           LEFT JOIN pago_cursos pc2
                  ON pc2.id = i2.pago_curso_id
                 AND pc2.deleted_at IS NULL
           WHERE i2.deleted_at IS NULL
             AND COALESCE(i2.fecha, pc2.fecha_pago) BETWEEN :desde AND :hasta
       ), 0), 2) AS pct_cantidad
FROM ingresos i
LEFT JOIN pago_cursos pc
       ON pc.id = i.pago_curso_id
      AND pc.deleted_at IS NULL
WHERE i.deleted_at IS NULL
  AND i.id_actividad IS NULL
  AND i.id_culto IS NULL
  AND i.pago_curso_id IS NULL
  AND COALESCE(i.fecha, pc.fecha_pago) BETWEEN :desde AND :hasta;
```
- **Laravel:**
```php
$sinOrigen = DB::table('ingresos as i')
    ->leftJoin('pago_cursos as pc', function ($j) {
        $j->on('pc.id', '=', 'i.pago_curso_id')->whereNull('pc.deleted_at');
    })
    ->whereNull('i.deleted_at')
    ->whereNull('i.id_actividad')
    ->whereNull('i.id_culto')
    ->whereNull('i.pago_curso_id')
    ->whereBetween(DB::raw('COALESCE(i.fecha, pc.fecha_pago)'), [$desde, $hasta])
    ->selectRaw('COUNT(*) AS cantidad, ROUND(SUM(COALESCE(i.monto_total, pc.monto)), 2) AS monto')
    ->first();
```
- **Visualización:** tarjeta + tabla de los ingresos sin origen. Semáforo: verde 0, ámbar < 5 %, rojo ≥ 5 %.
- **Frecuencia/rol:** mensual; Tesorero.

### D6. Variación mes a mes

- **Decisión:** detectar caídas o picos anómalos antes de que se conviertan en problemas de caja.
- **Fórmula:** `(mes actual - mes anterior) / mes anterior * 100`.
- **SQL:**
```sql
WITH mensual AS (
    SELECT DATE_FORMAT(COALESCE(i.fecha, pc.fecha_pago), '%Y-%m') AS mes,
           SUM(COALESCE(i.monto_total, pc.monto)) AS ingresos
    FROM ingresos i
    LEFT JOIN pago_cursos pc
           ON pc.id = i.pago_curso_id
          AND pc.deleted_at IS NULL
    WHERE i.deleted_at IS NULL
      AND COALESCE(i.fecha, pc.fecha_pago) BETWEEN :desde AND :hasta
    GROUP BY mes
)
SELECT mes,
       ROUND(ingresos, 2) AS ingresos,
       ROUND(LAG(ingresos) OVER (ORDER BY mes), 2) AS ingresos_mes_anterior,
       ROUND(100 * (ingresos - LAG(ingresos) OVER (ORDER BY mes))
             / NULLIF(LAG(ingresos) OVER (ORDER BY mes), 0), 2) AS variacion_pct
FROM mensual
ORDER BY mes;
```
- **Laravel:** reutilizar el arreglo `$flujo` de D1 y calcular en PHP:
```php
$variacion = collect($flujo)->values()->map(function ($fila, $i) use ($flujo) {
    $anterior = $i > 0 ? (float) $flujo[$i - 1]['ingresos'] : null;
    $fila['variacion_pct'] = $anterior && $anterior > 0
        ? round(100 * ((float) $fila['ingresos'] - $anterior) / $anterior, 2)
        : null;
    return $fila;
});
```
- **Visualización:** línea de variación % con banda de ±10 %. Semáforo: rojo si < -20 %, ámbar -20 % a -10 %, verde > -10 %.
- **Frecuencia/rol:** mensual; Tesorero y Administrador.

### D7. Promedio de ofrenda por culto

- **Decisión:** comparar la generosidad por servicio y detectar cultos con baja recaudación.
- **Fórmula:** `ofrendas / servicios con ofrenda` (por culto y por período).
- **SQL:**
```sql
SELECT cu.id,
       cu.nombre,
       cu.dia_semana,
       COUNT(DISTINCT i.fecha) AS servicios_con_ofrenda,
       ROUND(SUM(CASE WHEN i.tipo LIKE 'Ofrenda%' THEN COALESCE(i.monto_total, pc.monto) ELSE 0 END), 2) AS total_ofrendas,
       ROUND(SUM(CASE WHEN i.tipo LIKE 'Ofrenda%' THEN COALESCE(i.monto_total, pc.monto) ELSE 0 END)
             / NULLIF(COUNT(DISTINCT i.fecha), 0), 2) AS promedio_por_servicio
FROM cultos cu
JOIN ingresos i
  ON i.id_culto = cu.id
 AND i.deleted_at IS NULL
 AND i.fecha BETWEEN :desde AND :hasta
LEFT JOIN pago_cursos pc
       ON pc.id = i.pago_curso_id
      AND pc.deleted_at IS NULL
WHERE cu.deleted_at IS NULL
GROUP BY cu.id, cu.nombre, cu.dia_semana
ORDER BY promedio_por_servicio DESC;
```
- **Laravel:**
```php
$promedioOfrenda = DB::table('cultos as cu')
    ->join('ingresos as i', function ($j) use ($desde, $hasta) {
        $j->on('i.id_culto', '=', 'cu.id')
          ->whereNull('i.deleted_at')
          ->whereBetween('i.fecha', [$desde, $hasta]);
    })
    ->leftJoin('pago_cursos as pc', function ($j) {
        $j->on('pc.id', '=', 'i.pago_curso_id')->whereNull('pc.deleted_at');
    })
    ->whereNull('cu.deleted_at')
    ->select('cu.id', 'cu.nombre', 'cu.dia_semana')
    ->selectRaw('COUNT(DISTINCT i.fecha) AS servicios_con_ofrenda')
    ->selectRaw("ROUND(SUM(CASE WHEN i.tipo LIKE 'Ofrenda%' THEN COALESCE(i.monto_total, pc.monto) ELSE 0 END), 2) AS total_ofrendas")
    ->selectRaw("ROUND(SUM(CASE WHEN i.tipo LIKE 'Ofrenda%' THEN COALESCE(i.monto_total, pc.monto) ELSE 0 END) / NULLIF(COUNT(DISTINCT i.fecha), 0), 2) AS promedio_por_servicio")
    ->groupBy('cu.id', 'cu.nombre', 'cu.dia_semana')
    ->orderByDesc('promedio_por_servicio')
    ->get();
```
- **Visualización:** barras por culto + línea histórica. Semáforo: rojo si cae > 25 % respecto al promedio de 3 meses.
- **Frecuencia/rol:** mensual; Tesorero y Administrador.

> **Denominador:** solo cuenta servicios que tienen al menos un ingreso de ofrenda. Si quieres “promedio por culto realizado”, necesitas la tabla de servicios/asistencia de culto (§7).

---

## 7. Área E — Cobranza de cursos

### E1. Cobrado vs. esperado por curso

- **Decisión:** saber si la inscripción se está cobrando y qué curso tiene brecha.
- **Fórmula:** `SUM(pago_cursos.monto)` vs. `SUM(cursos.monto_inscripcion)` por inscripción activa.
- **SQL:**
```sql
WITH pagos AS (
    SELECT pc.inscripcion_id,
           SUM(pc.monto) AS pagado
    FROM pago_cursos pc
    WHERE pc.deleted_at IS NULL
    GROUP BY pc.inscripcion_id
)
SELECT c.id,
       c.nombre,
       c.tiene_pago,
       c.monto_inscripcion,
       COUNT(DISTINCT i.id) AS inscritos_activos,
       ROUND(SUM(CASE WHEN c.tiene_pago = 1 THEN c.monto_inscripcion ELSE 0 END), 2) AS esperado,
       ROUND(COALESCE(SUM(p.pagado), 0), 2) AS cobrado,
       ROUND(GREATEST(
           SUM(CASE WHEN c.tiene_pago = 1 THEN c.monto_inscripcion ELSE 0 END)
           - COALESCE(SUM(p.pagado), 0), 0
       ), 2) AS saldo_pendiente,
       ROUND(100 * COALESCE(SUM(p.pagado), 0)
             / NULLIF(SUM(CASE WHEN c.tiene_pago = 1 THEN c.monto_inscripcion ELSE 0 END), 0), 2) AS pct_recaudado
FROM cursos c
JOIN inscripciones i
  ON i.curso_id = c.id
 AND i.deleted_at IS NULL
 AND i.estado = 'Activo'
LEFT JOIN pagos p
       ON p.inscripcion_id = i.id
WHERE c.deleted_at IS NULL
GROUP BY c.id, c.nombre, c.tiene_pago, c.monto_inscripcion
ORDER BY saldo_pendiente DESC;
```
- **Laravel:**
```php
$pagos = DB::table('pago_cursos')
    ->select('inscripcion_id')
    ->selectRaw('SUM(monto) AS pagado')
    ->whereNull('deleted_at')
    ->groupBy('inscripcion_id');

$cobranza = DB::table('cursos as c')
    ->join('inscripciones as i', function ($j) {
        $j->on('i.curso_id', '=', 'c.id')
          ->whereNull('i.deleted_at')
          ->where('i.estado', 'Activo');
    })
    ->leftJoinSub($pagos, 'p', fn ($j) => $j->on('p.inscripcion_id', '=', 'i.id'))
    ->whereNull('c.deleted_at')
    ->select('c.id', 'c.nombre', 'c.tiene_pago', 'c.monto_inscripcion')
    ->selectRaw('COUNT(DISTINCT i.id) AS inscritos_activos')
    ->selectRaw('ROUND(SUM(CASE WHEN c.tiene_pago = 1 THEN c.monto_inscripcion ELSE 0 END), 2) AS esperado')
    ->selectRaw('ROUND(COALESCE(SUM(p.pagado), 0), 2) AS cobrado')
    ->selectRaw('ROUND(GREATEST(SUM(CASE WHEN c.tiene_pago = 1 THEN c.monto_inscripcion ELSE 0 END) - COALESCE(SUM(p.pagado), 0), 0), 2) AS saldo_pendiente')
    ->selectRaw('ROUND(100 * COALESCE(SUM(p.pagado), 0) / NULLIF(SUM(CASE WHEN c.tiene_pago = 1 THEN c.monto_inscripcion ELSE 0 END), 0), 2) AS pct_recaudado')
    ->groupBy('c.id', 'c.nombre', 'c.tiene_pago', 'c.monto_inscripcion')
    ->orderByDesc('saldo_pendiente')
    ->get();
```
- **Visualización:** barras apiladas cobrado/pendiente por curso + tabla. Semáforo según §2.6.
- **Frecuencia/rol:** mensual; Tesorero y Administrador.

> **Cursos exentos:** si `tiene_pago = 0`, el esperado es 0 y el curso no debe aparecer como moroso. Filtrar `c.tiene_pago = 1` para el ranking de cobranza.

### E2. Saldo pendiente total

- **Decisión:** dimensionar el dinero por cobrar y priorizar la gestión.
- **Fórmula:** `SUM(esperado - cobrado)` con piso 0 por inscripción.
- **SQL:**
```sql
WITH pagos AS (
    SELECT pc.inscripcion_id, SUM(pc.monto) AS pagado
    FROM pago_cursos pc
    WHERE pc.deleted_at IS NULL
    GROUP BY pc.inscripcion_id
),
saldos AS (
    SELECT i.id,
           i.curso_id,
           GREATEST(c.monto_inscripcion - COALESCE(p.pagado, 0), 0) AS saldo
    FROM inscripciones i
    JOIN cursos c
      ON c.id = i.curso_id
     AND c.deleted_at IS NULL
     AND c.tiene_pago = 1
    LEFT JOIN pagos p
           ON p.inscripcion_id = i.id
    WHERE i.deleted_at IS NULL
      AND i.estado = 'Activo'
)
SELECT COUNT(*) AS inscripciones_con_saldo,
       ROUND(SUM(saldo), 2) AS saldo_total,
       ROUND(AVG(saldo), 2) AS saldo_promedio
FROM saldos
WHERE saldo > 0;
```
- **Laravel:**
```php
$saldo = DB::table('inscripciones as i')
    ->join('cursos as c', function ($j) {
        $j->on('c.id', '=', 'i.curso_id')
          ->whereNull('c.deleted_at')
          ->where('c.tiene_pago', 1);
    })
    ->leftJoinSub($pagos, 'p', fn ($j) => $j->on('p.inscripcion_id', '=', 'i.id'))
    ->whereNull('i.deleted_at')
    ->where('i.estado', 'Activo')
    ->selectRaw('COUNT(*) AS inscripciones_con_saldo')
    ->selectRaw('ROUND(SUM(GREATEST(c.monto_inscripcion - COALESCE(p.pagado, 0), 0)), 2) AS saldo_total')
    ->havingRaw('saldo_total > 0')
    ->first();
```
- **Visualización:** tarjeta grande + tabla por curso. Semáforo: verde 0, ámbar < 10 % del esperado, rojo ≥ 10 %.
- **Frecuencia/rol:** semanal; Tesorero.

### E3. Alumnos Pagado / Pago Parcial / Pendiente

- **Decisión:** segmentar la cobranza y decidir a quién contactar primero.
- **Fórmula:** por inscripción, `saldo = monto_inscripcion - pagado`; `Pagado` si saldo ≤ 0, `Parcial` si pagado > 0, `Pendiente` si pagado = 0.
- **SQL:**
```sql
WITH pagos AS (
    SELECT pc.inscripcion_id, SUM(pc.monto) AS pagado
    FROM pago_cursos pc
    WHERE pc.deleted_at IS NULL
    GROUP BY pc.inscripcion_id
),
estado AS (
    SELECT i.id,
           i.curso_id,
           c.monto_inscripcion,
           COALESCE(p.pagado, 0) AS pagado,
           GREATEST(c.monto_inscripcion - COALESCE(p.pagado, 0), 0) AS saldo
    FROM inscripciones i
    JOIN cursos c
      ON c.id = i.curso_id
     AND c.deleted_at IS NULL
     AND c.tiene_pago = 1
    LEFT JOIN pagos p
           ON p.inscripcion_id = i.id
    WHERE i.deleted_at IS NULL
      AND i.estado = 'Activo'
)
SELECT CASE
         WHEN saldo <= 0 THEN 'Pagado'
         WHEN pagado > 0 THEN 'Pago Parcial'
         ELSE 'Pendiente'
       END AS estado_pago,
       COUNT(*) AS alumnos,
       ROUND(SUM(pagado), 2) AS cobrado,
       ROUND(SUM(saldo), 2) AS saldo
FROM estado
GROUP BY estado_pago
ORDER BY FIELD(estado_pago, 'Pagado', 'Pago Parcial', 'Pendiente');
```
- **Laravel:**
```php
$estado = DB::table('inscripciones as i')
    ->join('cursos as c', function ($j) {
        $j->on('c.id', '=', 'i.curso_id')
          ->whereNull('c.deleted_at')
          ->where('c.tiene_pago', 1);
    })
    ->leftJoinSub($pagos, 'p', fn ($j) => $j->on('p.inscripcion_id', '=', 'i.id'))
    ->whereNull('i.deleted_at')
    ->where('i.estado', 'Activo')
    ->selectRaw("CASE
        WHEN GREATEST(c.monto_inscripcion - COALESCE(p.pagado, 0), 0) <= 0 THEN 'Pagado'
        WHEN COALESCE(p.pagado, 0) > 0 THEN 'Pago Parcial'
        ELSE 'Pendiente' END AS estado_pago")
    ->selectRaw('COALESCE(p.pagado, 0) AS pagado')
    ->selectRaw('GREATEST(c.monto_inscripcion - COALESCE(p.pagado, 0), 0) AS saldo');

$distribucion = DB::query()
    ->fromSub($estado, 'e')
    ->select('estado_pago')
    ->selectRaw('COUNT(*) AS alumnos, ROUND(SUM(pagado), 2) AS cobrado, ROUND(SUM(saldo), 2) AS saldo')
    ->groupBy('estado_pago')
    ->get();
```
- **Visualización:** dona de 3 estados + tabla por curso. Semáforo: rojo si > 30 % en “Pendiente” o > 50 % en “Parcial + Pendiente”.
- **Frecuencia/rol:** semanal; Tesorero y Secretario.

### E4. Mora por curso

- **Decisión:** priorizar la cobranza judicial/administrativa y detectar cursos con problemas de pago.
- **Fórmula (proxy):** saldo > 0 y `DATEDIFF(hoy, fecha_inscripcion) > 30`; segmentar 31–60, 61–90, > 90 días.
- **SQL:**
```sql
WITH pagos AS (
    SELECT pc.inscripcion_id, SUM(pc.monto) AS pagado
    FROM pago_cursos pc
    WHERE pc.deleted_at IS NULL
    GROUP BY pc.inscripcion_id
),
deuda AS (
    SELECT i.id,
           i.curso_id,
           i.fecha_inscripcion,
           GREATEST(c.monto_inscripcion - COALESCE(p.pagado, 0), 0) AS saldo
    FROM inscripciones i
    JOIN cursos c
      ON c.id = i.curso_id
     AND c.deleted_at IS NULL
     AND c.tiene_pago = 1
    LEFT JOIN pagos p
           ON p.inscripcion_id = i.id
    WHERE i.deleted_at IS NULL
      AND i.estado = 'Activo'
)
SELECT c.id,
       c.nombre,
       COUNT(*) AS alumnos_en_mora,
       ROUND(SUM(d.saldo), 2) AS saldo_mora,
       ROUND(AVG(DATEDIFF(:hoy, d.fecha_inscripcion)), 1) AS dias_promedio,
       SUM(CASE WHEN DATEDIFF(:hoy, d.fecha_inscripcion) BETWEEN 31 AND 60 THEN 1 ELSE 0 END) AS d31_60,
       SUM(CASE WHEN DATEDIFF(:hoy, d.fecha_inscripcion) BETWEEN 61 AND 90 THEN 1 ELSE 0 END) AS d61_90,
       SUM(CASE WHEN DATEDIFF(:hoy, d.fecha_inscripcion) > 90 THEN 1 ELSE 0 END) AS d90_mas
FROM deuda d
JOIN cursos c
  ON c.id = d.curso_id
 AND c.deleted_at IS NULL
WHERE d.saldo > 0
  AND DATEDIFF(:hoy, d.fecha_inscripcion) > 30
GROUP BY c.id, c.nombre
ORDER BY saldo_mora DESC;
```
- **Laravel:**
```php
$mora = DB::query()
    ->fromSub($deuda, 'd')
    ->join('cursos as c', function ($j) {
        $j->on('c.id', '=', 'd.curso_id')->whereNull('c.deleted_at');
    })
    ->where('d.saldo', '>', 0)
    ->whereRaw('DATEDIFF(?, d.fecha_inscripcion) > 30', [$hoy])
    ->select('c.id', 'c.nombre')
    ->selectRaw('COUNT(*) AS alumnos_en_mora')
    ->selectRaw('ROUND(SUM(d.saldo), 2) AS saldo_mora')
    ->selectRaw("SUM(CASE WHEN DATEDIFF(?, d.fecha_inscripcion) BETWEEN 31 AND 60 THEN 1 ELSE 0 END) AS d31_60", [$hoy])
    ->selectRaw("SUM(CASE WHEN DATEDIFF(?, d.fecha_inscripcion) BETWEEN 61 AND 90 THEN 1 ELSE 0 END) AS d61_90", [$hoy])
    ->selectRaw("SUM(CASE WHEN DATEDIFF(?, d.fecha_inscripcion) > 90 THEN 1 ELSE 0 END) AS d90_mas", [$hoy])
    ->groupBy('c.id', 'c.nombre')
    ->orderByDesc('saldo_mora')
    ->get();
```
- **Visualización:** barras por curso + tabla de antigüedad. Semáforo: verde 0, ámbar 1–10 % de alumnos, rojo > 10 %.
- **Frecuencia/rol:** mensual; Tesorero.

> **Limitación:** `fecha_inscripcion` no es fecha de vencimiento. El KPI mide “antigüedad de la deuda”, no mora contractual. Agregar `inscripciones.fecha_vencimiento` (§8) para mora real.

### E5. Tasa de recaudación

- **Decisión:** medir la eficiencia global de cobranza y fijar metas.
- **Fórmula:** `cobrado / esperado * 100`.
- **SQL:**
```sql
WITH pagos AS (
    SELECT pc.inscripcion_id, SUM(pc.monto) AS pagado
    FROM pago_cursos pc
    WHERE pc.deleted_at IS NULL
    GROUP BY pc.inscripcion_id
)
SELECT ROUND(SUM(CASE WHEN c.tiene_pago = 1 THEN c.monto_inscripcion ELSE 0 END), 2) AS esperado,
       ROUND(COALESCE(SUM(p.pagado), 0), 2) AS cobrado,
       ROUND(100 * COALESCE(SUM(p.pagado), 0)
             / NULLIF(SUM(CASE WHEN c.tiene_pago = 1 THEN c.monto_inscripcion ELSE 0 END), 0), 2) AS pct_recaudacion
FROM cursos c
JOIN inscripciones i
  ON i.curso_id = c.id
 AND i.deleted_at IS NULL
 AND i.estado = 'Activo'
LEFT JOIN pagos p
       ON p.inscripcion_id = i.id
WHERE c.deleted_at IS NULL;
```
- **Laravel:** sumar la colección de E1:
```php
$esperado = (float) $cobranza->sum('esperado');
$cobrado = (float) $cobranza->sum('cobrado');
$pctRecaudacion = $esperado > 0 ? round(100 * $cobrado / $esperado, 2) : null;
```
- **Visualización:** gauge/tarjeta con % + línea histórica. Semáforo según §2.6.
- **Frecuencia/rol:** mensual; Tesorero.

### E6. Conciliación `pago_cursos` ↔ `ingresos`

- **Decisión:** detectar dinero cobrado que no se refleja en ingresos o ingresos que apuntan a pagos inexistentes.
- **Fórmula:** pagos sin ingreso; ingresos con `pago_curso_id` huérfano.
- **SQL:**
```sql
SELECT 'pago_sin_ingreso' AS hallazgo,
       COUNT(*) AS cantidad,
       ROUND(SUM(pc.monto), 2) AS monto
FROM pago_cursos pc
LEFT JOIN ingresos i
       ON i.pago_curso_id = pc.id
      AND i.deleted_at IS NULL
WHERE pc.deleted_at IS NULL
  AND i.id IS NULL

UNION ALL

SELECT 'ingreso_huerfano' AS hallazgo,
       COUNT(*) AS cantidad,
       ROUND(SUM(COALESCE(i.monto_total, 0)), 2) AS monto
FROM ingresos i
LEFT JOIN pago_cursos pc
       ON pc.id = i.pago_curso_id
      AND pc.deleted_at IS NULL
WHERE i.deleted_at IS NULL
  AND i.pago_curso_id IS NOT NULL
  AND pc.id IS NULL;
```
- **Laravel:**
```php
$pagosSinIngreso = DB::table('pago_cursos as pc')
    ->leftJoin('ingresos as i', function ($j) {
        $j->on('i.pago_curso_id', '=', 'pc.id')->whereNull('i.deleted_at');
    })
    ->whereNull('pc.deleted_at')
    ->whereNull('i.id')
    ->count();

$ingresosHuerfanos = DB::table('ingresos as i')
    ->leftJoin('pago_cursos as pc', function ($j) {
        $j->on('pc.id', '=', 'i.pago_curso_id')->whereNull('pc.deleted_at');
    })
    ->whereNull('i.deleted_at')
    ->whereNotNull('i.pago_curso_id')
    ->whereNull('pc.id')
    ->count();
```
- **Visualización:** tabla de hallazgos + semáforo rojo si cantidad > 0. Es un KPI de calidad de datos, no de negocio.
- **Frecuencia/rol:** semanal; Tesorero.

---

## 8. Área F — Personal y contratos

### F1. Estado de contratos

- **Decisión:** saber cuántos contratos están vigentes, vencidos o próximos a vencer.
- **Fórmula:** conteo por vigencia calculada (mutuamente excluyente).
- **SQL:**
```sql
SELECT CASE
         WHEN c.fecha_inicio > :hoy THEN 'Futuro'
         WHEN c.fecha_fin IS NOT NULL AND c.fecha_fin < :hoy THEN 'Vencido'
         WHEN c.fecha_fin IS NOT NULL AND c.fecha_fin <= DATE_ADD(:hoy, INTERVAL 30 DAY) THEN 'Por vencer'
         ELSE 'Vigente'
       END AS estado_contrato,
       COUNT(*) AS contratos,
       ROUND(SUM(c.salario), 2) AS salario_mensual
FROM contratos c
WHERE c.deleted_at IS NULL
  AND c.activo = 1
GROUP BY estado_contrato
ORDER BY FIELD(estado_contrato, 'Vigente', 'Por vencer', 'Vencido', 'Futuro');
```
- **Laravel:**
```php
$estadoContratos = Contrato::where('activo', true)
    ->selectRaw("CASE
        WHEN fecha_inicio > ? THEN 'Futuro'
        WHEN fecha_fin IS NOT NULL AND fecha_fin < ? THEN 'Vencido'
        WHEN fecha_fin IS NOT NULL AND fecha_fin <= DATE_ADD(?, INTERVAL 30 DAY) THEN 'Por vencer'
        ELSE 'Vigente' END AS estado_contrato", [$hoy, $hoy, $hoy])
    ->selectRaw('COUNT(*) AS contratos, ROUND(SUM(salario), 2) AS salario_mensual')
    ->groupBy('estado_contrato')
    ->get();
```
- **Visualización:** dona o barras. Semáforo: rojo si hay vencidos, ámbar si hay por vencer, verde si todos vigentes.
- **Frecuencia/rol:** mensual; Administrador y Tesorero.

### F2. Contratos por vencer (≤ 30 días)

- **Decisión:** renovar a tiempo y evitar que un colaborador quede sin contrato.
- **Fórmula:** `fecha_fin BETWEEN hoy AND hoy + 30`.
- **SQL:**
```sql
SELECT c.id,
       m.nombre AS miembro,
       ca.nombre AS cargo,
       c.tipo_compensacion,
       c.salario,
       c.fecha_inicio,
       c.fecha_fin,
       DATEDIFF(c.fecha_fin, :hoy) AS dias_para_vencer
FROM contratos c
JOIN miembros m
  ON m.id = c.id_miembro
 AND m.deleted_at IS NULL
LEFT JOIN cargos ca
       ON ca.id = c.id_cargo
      AND ca.deleted_at IS NULL
WHERE c.deleted_at IS NULL
  AND c.activo = 1
  AND c.fecha_fin BETWEEN :hoy AND DATE_ADD(:hoy, INTERVAL 30 DAY)
ORDER BY c.fecha_fin ASC;
```
- **Laravel:**
```php
$porVencer = Contrato::with(['miembro', 'cargo'])
    ->where('activo', true)
    ->whereBetween('fecha_fin', [$hoy, Carbon::parse($hoy)->addDays(30)->toDateString()])
    ->orderBy('fecha_fin')
    ->get();
```
- **Visualización:** tabla con días restantes + badge. Semáforo: verde 0, ámbar 1–2, rojo ≥ 3.
- **Frecuencia/rol:** semanal; Administrador y Tesorero.

> **Dependencia:** `contratos.id_cargo` existe en la BD viva pero no en las migraciones. Si no está, quitar `ca.nombre` del `SELECT` y del `LEFT JOIN`. La migración de §10 la reconcilia.

### F3. Planilla mensual total

- **Decisión:** saber cuánto cuesta la estructura de personal cada mes y compararlo con los ingresos.
- **Fórmula:** salarios de contratos vigentes + pagos directos a líderes sin contrato (sin duplicar).
- **SQL (devengado):**
```sql
WITH contratos_vigentes AS (
    SELECT c.id, c.salario
    FROM contratos c
    WHERE c.deleted_at IS NULL
      AND c.activo = 1
      AND c.fecha_inicio <= :hoy
      AND (c.fecha_fin IS NULL OR c.fecha_fin >= :hoy)
),
pagos_directos AS (
    SELECT COALESCE(SUM(e.monto), 0) AS total
    FROM egresos e
    WHERE e.deleted_at IS NULL
      AND e.fecha BETWEEN :desde AND :hasta
      AND e.id_contrato IS NULL
      AND e.id_lider_iglesia IS NOT NULL
      AND (
          e.tipo_egreso LIKE 'Salario%'
          OR e.tipo_egreso LIKE 'Honorario%'
          OR e.tipo_egreso LIKE 'Pago%'
      )
)
SELECT ROUND(COALESCE(SUM(cv.salario), 0), 2) AS planilla_contratos,
       ROUND((SELECT total FROM pagos_directos), 2) AS pagos_directos,
       ROUND(COALESCE(SUM(cv.salario), 0) + (SELECT total FROM pagos_directos), 2) AS planilla_total
FROM contratos_vigentes cv;
```
**SQL (pagado real del período, para conciliar):**
```sql
SELECT ROUND(SUM(CASE WHEN e.id_contrato IS NOT NULL THEN e.monto ELSE 0 END), 2) AS pagado_contratos,
       ROUND(SUM(CASE WHEN e.id_contrato IS NULL AND e.id_lider_iglesia IS NOT NULL THEN e.monto ELSE 0 END), 2) AS pagado_lideres,
       ROUND(SUM(e.monto), 2) AS total_pagado
FROM egresos e
WHERE e.deleted_at IS NULL
  AND e.fecha BETWEEN :desde AND :hasta;
```
- **Laravel:**
```php
$planillaContratos = (float) Contrato::where('activo', true)
    ->where('fecha_inicio', '<=', $hoy)
    ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $hoy))
    ->sum('salario');

$pagosDirectos = (float) DB::table('egresos')
    ->whereNull('deleted_at')
    ->whereBetween('fecha', [$desde, $hasta])
    ->whereNull('id_contrato')
    ->whereNotNull('id_lider_iglesia')
    ->where(function ($q) {
        $q->where('tipo_egreso', 'like', 'Salario%')
          ->orWhere('tipo_egreso', 'like', 'Honorario%')
          ->orWhere('tipo_egreso', 'like', 'Pago%');
    })
    ->sum('monto');

$planillaTotal = $planillaContratos + $pagosDirectos;
```
- **Visualización:** tarjeta de planilla total + desglose contratos/directos + % sobre ingresos. Semáforo: rojo si > 65 % de los ingresos.
- **Frecuencia/rol:** mensual; Tesorero.

> **Doble conteo:** un egreso con `id_contrato` ya está pagando un contrato; no se suma como pago directo. Un egreso con `id_lider_iglesia` y sin `id_contrato` es pago directo. Si un egreso tuviera ambos, la condición `id_contrato IS NULL` lo excluye de directos.
>
> **Supuesto:** `salario` es mensual. Si es anual, dividir por los meses de vigencia.

### F4. Costo de personal por cargo

- **Decisión:** ver qué cargos concentran el costo y comparar con la estructura organizacional.
- **Fórmula:** `SUM(salario)` por `contratos.id_cargo`.
- **SQL:**
```sql
SELECT COALESCE(ca.nombre, 'Sin cargo') AS cargo,
       COUNT(*) AS contratos,
       ROUND(SUM(c.salario), 2) AS salario_mensual,
       ROUND(100 * SUM(c.salario)
             / NULLIF(SUM(SUM(c.salario)) OVER (), 0), 2) AS pct_planilla
FROM contratos c
LEFT JOIN cargos ca
       ON ca.id = c.id_cargo
      AND ca.deleted_at IS NULL
WHERE c.deleted_at IS NULL
  AND c.activo = 1
  AND c.fecha_inicio <= :hoy
  AND (c.fecha_fin IS NULL OR c.fecha_fin >= :hoy)
GROUP BY cargo
ORDER BY salario_mensual DESC;
```
- **Laravel:**
```php
$porCargo = DB::table('contratos as c')
    ->leftJoin('cargos as ca', function ($j) {
        $j->on('ca.id', '=', 'c.id_cargo')->whereNull('ca.deleted_at');
    })
    ->whereNull('c.deleted_at')
    ->where('c.activo', 1)
    ->where('c.fecha_inicio', '<=', $hoy)
    ->where(fn ($q) => $q->whereNull('c.fecha_fin')->orWhere('c.fecha_fin', '>=', $hoy))
    ->selectRaw("COALESCE(ca.nombre, 'Sin cargo') AS cargo")
    ->selectRaw('COUNT(*) AS contratos, ROUND(SUM(c.salario), 2) AS salario_mensual')
    ->groupBy('cargo')
    ->orderByDesc('salario_mensual')
    ->get();
```
- **Visualización:** barras horizontales. Sin semáforo; usar para decisión.
- **Frecuencia/rol:** mensual; Tesorero.

> **Dependencia:** si `contratos.id_cargo` no existe, este KPI no se puede calcular. La migración de §10 la agrega solo si falta.

### F5. Contratos solapados

- **Decisión:** detectar pagos duplicados o contratos incompatibles para el mismo miembro.
- **Fórmula:** dos contratos del mismo miembro cuyos rangos `[inicio, fin]` se intersectan.
- **SQL:**
```sql
SELECT a.id AS contrato_a,
       b.id AS contrato_b,
       a.id_miembro,
       m.nombre AS miembro,
       a.fecha_inicio AS inicio_a,
       a.fecha_fin AS fin_a,
       b.fecha_inicio AS inicio_b,
       b.fecha_fin AS fin_b
FROM contratos a
JOIN contratos b
  ON b.id_miembro = a.id_miembro
 AND b.id > a.id
 AND b.deleted_at IS NULL
 AND a.fecha_inicio <= COALESCE(b.fecha_fin, '9999-12-31')
 AND COALESCE(a.fecha_fin, '9999-12-31') >= b.fecha_inicio
JOIN miembros m
  ON m.id = a.id_miembro
 AND m.deleted_at IS NULL
WHERE a.deleted_at IS NULL
ORDER BY m.nombre, a.fecha_inicio;
```
- **Laravel:**
```php
$solapados = DB::table('contratos as a')
    ->join('contratos as b', function ($j) {
        $j->on('b.id_miembro', '=', 'a.id_miembro')
          ->on('b.id', '>', 'a.id')
          ->whereNull('b.deleted_at')
          ->whereRaw('a.fecha_inicio <= COALESCE(b.fecha_fin, ?)', ['9999-12-31'])
          ->whereRaw('COALESCE(a.fecha_fin, ?) >= b.fecha_inicio', ['9999-12-31']);
    })
    ->join('miembros as m', function ($j) {
        $j->on('m.id', '=', 'a.id_miembro')->whereNull('m.deleted_at');
    })
    ->whereNull('a.deleted_at')
    ->select('a.id as contrato_a', 'b.id as contrato_b', 'm.nombre as miembro')
    ->get();
```
- **Visualización:** tabla de alertas. Semáforo: rojo si hay ≥ 1.
- **Frecuencia/rol:** por evento (al crear/editar contrato) y mensual; Administrador.

---

## 9. Área G — Seguridad y uso del sistema

### G1. Usuarios por rol

- **Decisión:** saber quién tiene acceso y detectar concentración de privilegios.
- **Fórmula:** `COUNT(DISTINCT user_id)` por rol.
- **SQL:**
```sql
SELECT r.id,
       r.nombre AS rol,
       r.nivel_acceso,
       COUNT(DISTINCT u.id) AS usuarios
FROM roles r
LEFT JOIN role_user ru
       ON ru.role_id = r.id
      AND ru.deleted_at IS NULL
LEFT JOIN users u
       ON u.id = ru.user_id
      AND u.deleted_at IS NULL
WHERE r.deleted_at IS NULL
GROUP BY r.id, r.nombre, r.nivel_acceso
ORDER BY r.nivel_acceso ASC, usuarios DESC;
```
- **Laravel:**
```php
$usuariosPorRol = Role::withCount(['users' => fn ($q) => $q->whereNull('users.deleted_at')])
    ->orderBy('nivel_acceso')
    ->get(['id', 'nombre', 'nivel_acceso']);
```
> `withCount` no filtra `role_user.deleted_at` automáticamente. Para ser estrictos:
```php
$usuariosPorRol = DB::table('roles as r')
    ->leftJoin('role_user as ru', function ($j) {
        $j->on('ru.role_id', '=', 'r.id')->whereNull('ru.deleted_at');
    })
    ->leftJoin('users as u', function ($j) {
        $j->on('u.id', '=', 'ru.user_id')->whereNull('u.deleted_at');
    })
    ->whereNull('r.deleted_at')
    ->select('r.nombre as rol', 'r.nivel_acceso')
    ->selectRaw('COUNT(DISTINCT u.id) AS usuarios')
    ->groupBy('r.id', 'r.nombre', 'r.nivel_acceso')
    ->orderBy('r.nivel_acceso')
    ->get();
```
- **Visualización:** barras + tabla. Semáforo: ámbar si un rol tiene 0 usuarios (salvo Docente), rojo si hay más de 2 administradores sin justificación.
- **Frecuencia/rol:** mensual; Administrador.

### G2. Usuarios sin rol

- **Decisión:** cerrar accesos huérfanos o completar la asignación.
- **Fórmula:** usuarios sin fila vigente en `role_user`.
- **SQL:**
```sql
SELECT u.id, u.name, u.email, u.created_at
FROM users u
WHERE u.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM role_user ru
      WHERE ru.user_id = u.id
        AND ru.deleted_at IS NULL
  )
ORDER BY u.created_at DESC;
```
- **Laravel:**
```php
$sinRol = User::whereDoesntHave('roles', fn ($q) => $q->whereNull('role_user.deleted_at'))
    ->get(['id', 'name', 'email', 'created_at']);
```
- **Visualización:** tarjeta + tabla. Semáforo: rojo si ≥ 1.
- **Frecuencia/rol:** semanal; Administrador.

### G3. Cobertura de acceso docente

- **Decisión:** detectar docentes que no pueden registrar asistencia o usuarios con rol Docente sin ficha de docente.
- **Fórmula:** docentes activos sin `user_id` + usuarios con rol Docente sin fila en `docentes`.
- **SQL:**
```sql
SELECT 'docente_sin_usuario' AS hallazgo,
       COUNT(*) AS cantidad
FROM docentes d
WHERE d.deleted_at IS NULL
  AND d.activo = 1
  AND d.user_id IS NULL

UNION ALL

SELECT 'usuario_docente_sin_ficha' AS hallazgo,
       COUNT(*) AS cantidad
FROM users u
JOIN role_user ru
  ON ru.user_id = u.id
 AND ru.deleted_at IS NULL
JOIN roles r
  ON r.id = ru.role_id
 AND r.deleted_at IS NULL
WHERE u.deleted_at IS NULL
  AND r.nombre = 'Docente'
  AND NOT EXISTS (
      SELECT 1
      FROM docentes d2
      WHERE d2.user_id = u.id
        AND d2.deleted_at IS NULL
  );
```
- **Laravel:**
```php
$docentesSinUsuario = Docente::where('activo', true)->whereNull('user_id')->count();

$usuariosDocenteSinFicha = User::whereHas('roles', fn ($q) => $q->where('nombre', 'Docente'))
    ->whereDoesntHave('docente', fn ($q) => $q->whereNull('deleted_at'))
    ->count();
```
- **Visualización:** tabla de alertas. Semáforo: rojo si ≥ 1.
- **Frecuencia/rol:** mensual; Administrador.

> **Deriva:** `docentes.user_id` existe por la migración `2026_10_23`; el PROMPT no lo lista. Si no existe, este KPI no aplica.

### G4. Módulos con mayor actividad

- **Decisión:** saber qué módulos se usan y cuáles están abandonados; priorizar capacitación.
- **Fórmula:** `COUNT(*)` por `modulo` en el registro de auditoría.
- **SQL (después de la migración de §10):**
```sql
SELECT al.modulo,
       COUNT(*) AS eventos,
       COUNT(DISTINCT al.user_id) AS usuarios,
       MAX(al.created_at) AS ultimo_evento
FROM audit_logs al
LEFT JOIN users u
       ON u.id = al.user_id
      AND u.deleted_at IS NULL
WHERE al.created_at BETWEEN :desde AND :hasta_excl
GROUP BY al.modulo
ORDER BY eventos DESC
LIMIT 15;
```
- **Laravel:**
```php
$modulos = DB::table('audit_logs')
    ->whereBetween('created_at', [$desde, $hastaExcl])
    ->select('modulo')
    ->selectRaw('COUNT(*) AS eventos, COUNT(DISTINCT user_id) AS usuarios, MAX(created_at) AS ultimo_evento')
    ->groupBy('modulo')
    ->orderByDesc('eventos')
    ->limit(15)
    ->get();
```
- **Visualización:** barras horizontales + tabla. Sin semáforo; usar para capacitación y priorización.
- **Frecuencia/rol:** semanal; Administrador.

> **Hoy no existe `audit_logs`.** Proxy temporal (mide **registros creados**, no vistas):
```sql
SELECT 'miembros' AS modulo, COUNT(*) AS registros FROM miembros WHERE deleted_at IS NULL AND created_at BETWEEN :desde AND :hasta_excl
UNION ALL SELECT 'ingresos', COUNT(*) FROM ingresos WHERE deleted_at IS NULL AND created_at BETWEEN :desde AND :hasta_excl
UNION ALL SELECT 'egresos', COUNT(*) FROM egresos WHERE deleted_at IS NULL AND created_at BETWEEN :desde AND :hasta_excl
UNION ALL SELECT 'inscripciones', COUNT(*) FROM inscripciones WHERE deleted_at IS NULL AND created_at BETWEEN :desde AND :hasta_excl
UNION ALL SELECT 'asistencias', COUNT(*) FROM asistencias_sesion WHERE deleted_at IS NULL AND created_at BETWEEN :desde AND :hasta_excl
UNION ALL SELECT 'actividades', COUNT(*) FROM actividades WHERE deleted_at IS NULL AND created_at BETWEEN :desde AND :hasta_excl
ORDER BY registros DESC;
```
No lo etiquetes como “módulos más usados”: mide creación de datos.

### G5. Cobertura de permisos por rol

- **Decisión:** detectar roles con módulos sin configurar y auditar el principio de mínimo privilegio.
- **Fórmula:** filas de `permisos` por rol vs. módulos esperados (21 en `config/permisos.php`).
- **SQL:**
```sql
SELECT r.id,
       r.nombre AS rol,
       COUNT(p.id) AS modulos_configurados,
       SUM(CASE WHEN p.ver = 1 THEN 1 ELSE 0 END) AS modulos_ver,
       SUM(CASE WHEN p.crear = 1 THEN 1 ELSE 0 END) AS modulos_crear,
       SUM(CASE WHEN p.editar = 1 THEN 1 ELSE 0 END) AS modulos_editar,
       SUM(CASE WHEN p.eliminar = 1 THEN 1 ELSE 0 END) AS modulos_eliminar
FROM roles r
LEFT JOIN permisos p
       ON p.role_id = r.id
WHERE r.deleted_at IS NULL
GROUP BY r.id, r.nombre
ORDER BY r.nivel_acceso ASC;
```
- **Laravel:**
```php
$esperados = collect(config('permisos.categorias'))->flatten(1)->count();

$cobertura = Role::withCount('permisos')
    ->where('nombre', '!=', 'Administrador')
    ->get(['id', 'nombre'])
    ->map(fn ($rol) => [
        'rol' => $rol->nombre,
        'configurados' => $rol->permisos_count,
        'esperados' => $esperados,
        'pct' => $esperados > 0 ? round(100 * $rol->permisos_count / $esperados, 2) : null,
    ]);
```
- **Visualización:** tabla con % de cobertura. Semáforo: ámbar < 100 %, rojo si falta un módulo crítico (`miembros`, `ingresos`, `asistencia`).
- **Frecuencia/rol:** trimestral; Administrador.

> **Excepción:** el rol Administrador tiene acceso total por código; su 0 % de permisos configurados no es un hallazgo. No lo incluyas en el semáforo.
>
> **`permisos` no tiene `deleted_at`:** no agregues ese filtro.

---

## 10. Área H — Calidad del software

Estos KPIs **no salen de MySQL**. Salen de:
- `coverage.xml` (Clover) generado por `composer test:coverage` o `composer sonar`.
- `build/logs/junit.xml` generado por `composer sonar`.
- API de SonarQube (`http://localhost:9000`) usando el token `SONAR_TOKEN`.
- Reporte JSON/XML de OWASP ZAP y, si aplica, SARIF del SAST.

`sonar-project.properties` ya define `sonar.php.coverage.reportPaths=coverage.xml` y `sonar.php.tests.reportPaths=build/logs/junit.xml`. La cobertura excluye vistas, config, base de datos, rutas y CSS.

### H1. Cobertura de pruebas

- **Decisión:** saber qué porcentaje de la lógica está protegido por pruebas y si el release cumple la meta.
- **Fórmula:** `coveredstatements / statements * 100` (Clover) o `coverage` de Sonar.
- **Fuente:** `coverage.xml`.
- **PHP:**
```php
$xml = simplexml_load_file(base_path('coverage.xml'));
$metricas = $xml->project->metrics;

$statements = (int) $metricas['statements'];
$cubiertos  = (int) $metricas['coveredstatements'];

$cobertura = $statements > 0
    ? round(100 * $cubiertos / $statements, 2)
    : null;
```
- **Visualización:** gauge + línea histórica. Semáforo: verde ≥ 80 %, ámbar 60–79 %, rojo < 60 %. Si el proyecto recién empieza, usar meta 70 %.
- **Frecuencia/rol:** por release/CI; Administrador.

### H2. Tests pasando / fallando

- **Decisión:** bloquear un release si hay tests rojos.
- **Fórmula:** `pasados = tests - failures - errors - skipped`; `% = pasados / tests * 100`.
- **Fuente:** `build/logs/junit.xml`.
- **PHP:**
```php
$xml = simplexml_load_file(base_path('build/logs/junit.xml'));

$tests    = (int) $xml['tests'];
$failures = (int) $xml['failures'];
$errors   = (int) $xml['errors'];
$skipped  = (int) $xml['skipped'];
$pasados  = max(0, $tests - $failures - $errors - $skipped);

$pct = $tests > 0 ? round(100 * $pasados / $tests, 2) : null;
```
- **Visualización:** tarjeta con `pasados/total` + semáforo. Verde: 100 % y 0 failures/errors; rojo: cualquier failure/error.
- **Frecuencia/rol:** por release/CI; Administrador.

### H3. Hallazgos de seguridad por severidad

- **Decisión:** decidir si se puede desplegar y qué se corrige primero.
- **Fórmula:** conteo de issues abiertos por severidad (Sonar) + alertas ZAP por riesgo.
- **Fuente:** Sonar `/api/issues/search` + `reports/zap-report.json`.
- **PHP (Sonar):**
```php
$sonar = Http::withToken(config('services.sonar.token'))
    ->baseUrl(config('services.sonar.url'))
    ->get('/api/issues/search', [
        'componentKeys' => config('services.sonar.key'),
        'resolved' => 'false',
        'types' => 'VULNERABILITY', // Sonar <= 10.2
        'facets' => 'severities',
        'ps' => 1,
    ])->json();

$porSeveridad = collect($sonar['facets'] ?? [])
    ->firstWhere('property', 'severities')['values'] ?? [];
```
Para Sonar 10.3+ usa `impactSoftwareQualities=SECURITY` en lugar de `types=VULNERABILITY`.
- **PHP (ZAP):**
```php
$zap = json_decode(file_get_contents(base_path('reports/zap-report.json')), true);
$alertas = collect($zap['site'][0]['alerts'] ?? []);

$porRiesgo = $alertas->groupBy(fn ($a) => match ((int) ($a['riskcode'] ?? 0)) {
    3 => 'Alta',
    2 => 'Media',
    1 => 'Baja',
    default => 'Info',
})->map->count();
```
- **Visualización:** barras apiladas por severidad + semáforo. Rojo: ≥ 1 Blocker/Critical o ZAP Alto; ámbar: ≥ 1 Major/Medio; verde: 0.
- **Frecuencia/rol:** por release/CI; Administrador.

### H4. Deuda técnica y code smells

- **Decisión:** planificar refactorización y evitar que la deuda crezca.
- **Fórmula:** `sqale_index` (minutos) → días de 8 h; `code_smells`; rating de mantenibilidad.
- **Fuente:** Sonar `/api/measures/component`.
- **PHP:**
```php
$medidas = Http::withToken(config('services.sonar.token'))
    ->baseUrl(config('services.sonar.url'))
    ->get('/api/measures/component', [
        'component' => config('services.sonar.key'),
        'metricKeys' => implode(',', [
            'sqale_index',
            'software_quality_maintainability_effort',
            'software_quality_maintainability_rating',
            'code_smells',
            'duplicated_lines_density',
            'complexity',
        ]),
    ])->json('component.measures', []);

$valores = collect($medidas)->pluck('value', 'metric');
$deudaMinutos = (int) ($valores['sqale_index'] ?? $valores['software_quality_maintainability_effort'] ?? 0);
$deudaDias = round($deudaMinutos / 60 / 8, 1);
```
- **Visualización:** tarjeta de días de deuda + tendencia. Semáforo: verde < 5 días, ámbar 5–15, rojo > 15 (ajustar al tamaño del proyecto).
- **Frecuencia/rol:** mensual; Administrador.

### H5. Quality gate

- **Decisión:** bloquear el merge/deploy si el quality gate falla.
- **Fórmula:** `status = OK | ERROR`.
- **Fuente:** Sonar `/api/qualitygates/project_status?projectKey=proyecto1`.
- **PHP:**
```php
$gate = Http::withToken(config('services.sonar.token'))
    ->baseUrl(config('services.sonar.url'))
    ->get('/api/qualitygates/project_status', [
        'projectKey' => config('services.sonar.key'),
    ])->json('projectStatus', []);

$estado = $gate['status'] ?? 'UNKNOWN'; // OK, ERROR, UNKNOWN
$condiciones = collect($gate['conditions'] ?? [])
    ->where('status', 'ERROR')
    ->pluck('metricKey');
```
- **Visualización:** semáforo grande + lista de condiciones en rojo. Verde `OK`, rojo `ERROR`, gris `UNKNOWN` (sin análisis).
- **Frecuencia/rol:** por release/CI; Administrador.

### H6. Tabla de fuentes y persistencia

| KPI | Fuente primaria | Archivo / endpoint | Se persiste en |
|---|---|---|---|
| H1 cobertura | PHPUnit Clover | `coverage.xml` | `metricas_calidad` (`fuente=phpunit`, `metrica=cobertura_lineas_pct`) |
| H2 tests | PHPUnit JUnit | `build/logs/junit.xml` | `metricas_calidad` (`fuente=phpunit`, `metrica=tests_*`) |
| H3 seguridad | Sonar SAST + OWASP ZAP | `/api/issues/search`, `reports/zap-report.json` | `hallazgos_seguridad` |
| H4 deuda | Sonar | `/api/measures/component` | `metricas_calidad` (`fuente=sonar`) |
| H5 quality gate | Sonar | `/api/qualitygates/project_status` | `metricas_calidad` (`fuente=sonar`, `metrica=quality_gate`) |

**Snapshots SQL (después de la migración de §11):**
```sql
-- Último quality gate
SELECT texto AS quality_gate, capturado_en
FROM metricas_calidad
WHERE fuente = 'sonar' AND metrica = 'quality_gate'
ORDER BY capturado_en DESC
LIMIT 1;

-- Hallazgos abiertos por severidad
SELECT severidad, COUNT(*) AS hallazgos
FROM hallazgos_seguridad
WHERE estado = 'abierto'
GROUP BY severidad
ORDER BY FIELD(severidad, 'BLOCKER', 'CRITICAL', 'MAJOR', 'MINOR', 'INFO', 'Alta', 'Media', 'Baja', 'Info');

-- Tendencia de cobertura (12 últimos snapshots)
SELECT valor AS cobertura_pct, capturado_en
FROM metricas_calidad
WHERE fuente = 'phpunit' AND metrica = 'cobertura_lineas_pct'
ORDER BY capturado_en DESC
LIMIT 12;
```

> **Recomendación:** crear un comando `php artisan calidad:snapshot` que llame a `CalidadService::resumen()` y guarde una fila por métrica. Programarlo después de cada `composer sonar` o en CI. Sin snapshots no hay tendencia; solo foto actual.

---

## 11. Índices recomendados

### 11.1 Índices prioritarios

| Tabla | Índice | Consultas que mejora |
|---|---|---|
| `miembros` | `(estado, fecha_ingreso)` | A1, A2 |
| `miembros` | `(f_nacimiento)` | A3 |
| `miembros` | `(sexo)` | A3 |
| `ingresos` | `(fecha)` | D1, D3, D5, D6 |
| `ingresos` | `(id_culto, fecha)` | D2, D7 |
| `ingresos` | `(id_actividad)` | B6 |
| `ingresos` | `(tipo)` | D2, D3 |
| `egresos` | `(fecha)` | D1, D3, D4, F3 |
| `egresos` | `(id_contrato)` | F3 |
| `egresos` | `(id_lider_iglesia)` | F3 |
| `egresos` | `(tipo_egreso)` | D3, D4 |
| `asistencias_sesion` | **UNIQUE** `(sesion_id, miembro_id)` | C2, C3, C6 y deduplicación |
| `asistencias_sesion` | `(miembro_id)` | C6, reporte por alumno |
| `asistencias_sesion` | `(sesion_id, estado)` | C2, C3 |
| `sesiones` | `(id_curso, fecha)` | C2, C6 |
| `sesiones` | `(id_docente, fecha)` | C5 |
| `inscripciones` | `(curso_id, estado)` | C1, C4, E1–E5 |
| `inscripciones` | `(fecha_vencimiento)` | E4 (después de agregar la columna) |
| `pago_cursos` | `(inscripcion_id, fecha_pago)` | E1–E5 |
| `pago_cursos` | `(fecha_pago)` | D1, E5 |
| `actividades` | `(fecha, estado)` | B3, B5 |
| `actividades` | `(id_ministerio, fecha)` | B1, B6 |
| `seguimiento_actividades` | `(id_actividad, fecha_registro)` | B4, B5 |
| `contratos` | `(id_miembro, fecha_inicio, fecha_fin)` | F1, F2, F5 |
| `contratos` | `(fecha_fin, activo)` | F1, F2 |
| `contratos` | `(id_cargo)` | F4 |
| `miembro_ministerio` | `(ministerio_id, deleted_at)` | B1 |
| `miembro_ministerio` | `(miembro_id, fecha_fin)` | A4, B1 |
| `role_user` | **UNIQUE** `(user_id, role_id)` | G1, G2 (ya existe) |
| `roles` | `(nombre)` | G3, G5 |
| `users` | `(last_login_at)` | G4 (después de agregar la columna) |
| `audit_logs` | `(modulo, created_at)` | G4 |
| `audit_logs` | `(user_id, created_at)` | G4 |
| `metricas_calidad` | `(fuente, metrica, capturado_en)` | H1–H5 |
| `hallazgos_seguridad` | `(severidad, estado)` | H3 |

### 11.2 SQL de índices

```sql
-- Miembros
CREATE INDEX idx_miembros_estado_ingreso ON miembros (estado, fecha_ingreso);
CREATE INDEX idx_miembros_f_nacimiento ON miembros (f_nacimiento);
CREATE INDEX idx_miembros_sexo ON miembros (sexo);

-- Ingresos
CREATE INDEX idx_ingresos_fecha ON ingresos (fecha);
CREATE INDEX idx_ingresos_culto_fecha ON ingresos (id_culto, fecha);
CREATE INDEX idx_ingresos_actividad ON ingresos (id_actividad);
CREATE INDEX idx_ingresos_tipo ON ingresos (tipo);

-- Egresos
CREATE INDEX idx_egresos_fecha ON egresos (fecha);
CREATE INDEX idx_egresos_contrato ON egresos (id_contrato);
CREATE INDEX idx_egresos_lider ON egresos (id_lider_iglesia);
CREATE INDEX idx_egresos_tipo ON egresos (tipo_egreso);

-- Asistencia (crear el UNIQUE después de deduplicar)
CREATE UNIQUE INDEX uq_asistencias_sesion_miembro ON asistencias_sesion (sesion_id, miembro_id);
CREATE INDEX idx_asistencias_miembro ON asistencias_sesion (miembro_id);
CREATE INDEX idx_asistencias_sesion_estado ON asistencias_sesion (sesion_id, estado);

-- Academia
CREATE INDEX idx_sesiones_curso_fecha ON sesiones (id_curso, fecha);
CREATE INDEX idx_sesiones_docente_fecha ON sesiones (id_docente, fecha);
CREATE INDEX idx_inscripciones_curso_estado ON inscripciones (curso_id, estado);
CREATE INDEX idx_pagos_inscripcion_fecha ON pago_cursos (inscripcion_id, fecha_pago);
CREATE INDEX idx_pagos_fecha ON pago_cursos (fecha_pago);

-- Actividades
CREATE INDEX idx_actividades_fecha_estado ON actividades (fecha, estado);
CREATE INDEX idx_actividades_ministerio_fecha ON actividades (id_ministerio, fecha);
CREATE INDEX idx_seguimiento_actividad_fecha ON seguimiento_actividades (id_actividad, fecha_registro);

-- Personal
CREATE INDEX idx_contratos_miembro_fechas ON contratos (id_miembro, fecha_inicio, fecha_fin);
CREATE INDEX idx_contratos_fin_activo ON contratos (fecha_fin, activo);
CREATE INDEX idx_contratos_cargo ON contratos (id_cargo);

-- Ministerios
CREATE INDEX idx_miembro_ministerio_ministerio ON miembro_ministerio (ministerio_id, deleted_at);
CREATE INDEX idx_miembro_ministerio_miembro ON miembro_ministerio (miembro_id, fecha_fin);
```

> **Advertencia:** no ejecutes el `CREATE UNIQUE INDEX` de asistencias si hay duplicados. Primero:
```sql
SELECT sesion_id, miembro_id, COUNT(*) AS repeticiones
FROM asistencias_sesion
WHERE deleted_at IS NULL
GROUP BY sesion_id, miembro_id
HAVING COUNT(*) > 1;
```
> Si hay duplicados, **respalda** y corrige en una transacción conservando el registro más reciente (`MAX(id)`). No borres a ciegas: puede haber estados distintos (`Presente` y `Ausente`) para la misma sesión.

### 11.3 Índices que NO conviene crear

- `deleted_at` solo: baja selectividad; úsalo en índices compuestos.
- `(tipo)` en `ingresos` si el catálogo se migra a `categoria_id`: el índice debe ir en `categoria_id`.
- Índices en `nombre` de miembros para búsquedas `LIKE '%texto%'`: no se usan; para búsqueda real, evaluar `FULLTEXT` o un motor externo.
- Índices en columnas de baja cardinalidad como `sexo` o `estado` por separado: solo tienen sentido combinados con fecha o en consultas de agregación específicas.

---

## 12. Datos y columnas faltantes

| # | Falta | KPI que desbloquea | Propuesta |
|---|---|---|---|
| 1 | `miembros.fecha_baja`, `motivo_baja` | A6 retención/bajas, crecimiento neto | Agregar columnas; backfill manual de inactivos |
| 2 | `miembros.fecha_conversion`, `fecha_bautismo` | Crecimiento espiritual, bautismos por año | Opcional; útil para pastor |
| 3 | `inscripciones.fecha_vencimiento` | E4 mora real, recordatorios | Agregar; default = `fecha_inscripcion + 30 días` o por curso |
| 4 | `categorias_financieras` + `ingresos.categoria_id`, `egresos.categoria_id` | D2, D3, D4 consistentes | Catálogo con naturaleza ingreso/egreso; backfill desde `tipo` |
| 5 | `presupuestos` (año, mes, categoría, monto) | D9 ejecución presupuestaria | Tabla nueva; comparar real vs. planificado |
| 6 | `asistencias_culto` | Asistencia a cultos, promedio por servicio | Tabla nueva con `(miembro_id, culto_id, fecha)` único |
| 7 | `actividades.fecha_fin`, `presupuesto`, `costo_real` | B5 atraso real, B8 ROI | Agregar columnas |
| 8 | `audit_logs`, `users.last_login_at` | G4 módulos activos, último acceso | Tabla de auditoría + middleware/observer |
| 9 | `metricas_calidad`, `hallazgos_seguridad` | H1–H5 tendencia | Tablas de snapshots |
| 10 | `contratos.id_cargo`, `egresos.id_lider_iglesia` | F4, F3 | Reconciliar migraciones con la BD viva (sin perder datos) |
| 11 | UNIQUE `asistencias_sesion(sesion_id, miembro_id)` | C2, C3, C6 confiables | Deduplicar y crear el UNIQUE |
| 12 | Catálogo de estados (`miembros.estado`, `actividades.estado`, `inscripciones.estado`, `asistencias_sesion.estado`) | Normalización estable | Validación en FormRequest + `CHECK` o tabla de catálogo |

### 12.1 Qué KPIs tendrán sentido solo con más datos

| KPI | Mínimo razonable | Por qué |
|---|---|---|
| A1 crecimiento mensual | 6–12 meses | Con 2–3 meses no hay tendencia |
| A3 edad/sexo | ≥ 50 miembros | Con 7 personas los porcentajes no son representativos |
| A4 sin ministerio | Pivote poblado | Hoy `miembro_ministerio` está vacía: daría 100 % |
| A6 retención | ≥ 1 año y `fecha_baja` | Sin fecha de baja no existe |
| B1 miembros por ministerio | Pivote poblado | Hoy todos en 0 |
| B4/B5 avance | ≥ 10 actividades con seguimiento | Con 6 actividades el promedio es volátil |
| C1/C4 ocupación | ≥ 3 cursos y ≥ 30 inscripciones | Con 2 cursos es anecdótico |
| C2/C3/C6 asistencia | ≥ 8 sesiones por curso | Con 11 sesiones totales y 5 registros, el % es inestable |
| C5 carga docente | ≥ 3 docentes y `docente_curso` poblado | Hoy se puede usar `sesiones.id_docente`, pero es parcial |
| D2/D3/D7 finanzas | ≥ 6 meses | La composición mensual varía mucho |
| D6 variación MoM | ≥ 3 meses | El primer mes no tiene comparación |
| E1–E5 cobranza | ≥ 20 inscripciones pagadas | Con 6 inscripciones pagas el % salta |
| E4 mora | `fecha_vencimiento` + 60 días | Hoy es proxy |
| F1–F3 personal | ≥ 3 contratos | Con 5 contratos ya es útil, pero validar si el salario es mensual |
| G4 módulos activos | `audit_logs` con ≥ 1 mes | Sin auditoría no existe |
| H1–H5 calidad | Inmediato | No dependen del volumen de negocio |

---

## 13. Migración Laravel para los datos faltantes

Archivo real: `database/migrations/2026_10_28_000001_add_kpi_support_fields.php`.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Membresía: bajas y fechas de crecimiento espiritual.
        $this->addColumn('miembros', 'fecha_baja', fn (Blueprint $t) => $t->date('fecha_baja')->nullable()->after('fecha_ingreso'));
        $this->addColumn('miembros', 'motivo_baja', fn (Blueprint $t) => $t->string('motivo_baja')->nullable()->after('fecha_baja'));
        $this->addColumn('miembros', 'fecha_conversion', fn (Blueprint $t) => $t->date('fecha_conversion')->nullable()->after('fecha_ingreso'));
        $this->addColumn('miembros', 'fecha_bautismo', fn (Blueprint $t) => $t->date('fecha_bautismo')->nullable()->after('fecha_conversion'));

        // 2. Deriva de esquema: columnas que existen en la BD viva pero no en migraciones.
        if (! Schema::hasColumn('contratos', 'id_cargo')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->unsignedBigInteger('id_cargo')->nullable()->after('id_miembro')->index();
                if (DB::getDriverName() !== 'sqlite') {
                    $table->foreign('id_cargo')->references('id')->on('cargos')->nullOnDelete();
                }
            });
        }

        if (! Schema::hasColumn('egresos', 'id_lider_iglesia')) {
            Schema::table('egresos', function (Blueprint $table) {
                $table->unsignedBigInteger('id_lider_iglesia')->nullable()->after('id_contrato')->index();
                if (DB::getDriverName() !== 'sqlite') {
                    $table->foreign('id_lider_iglesia')->references('id')->on('lideres_iglesia')->nullOnDelete();
                }
            });
        }

        // 3. Cobranza: fecha de vencimiento real.
        $this->addColumn('inscripciones', 'fecha_vencimiento', fn (Blueprint $t) => $t->date('fecha_vencimiento')->nullable()->after('fecha_inscripcion'));

        // 4. Actividades: fin, presupuesto y costo real.
        $this->addColumn('actividades', 'fecha_fin', fn (Blueprint $t) => $t->date('fecha_fin')->nullable()->after('fecha'));
        $this->addColumn('actividades', 'presupuesto', fn (Blueprint $t) => $t->decimal('presupuesto', 12, 2)->nullable()->after('fecha_fin'));
        $this->addColumn('actividades', 'costo_real', fn (Blueprint $t) => $t->decimal('costo_real', 12, 2)->nullable()->after('presupuesto'));

        // 5. Seguridad y uso.
        $this->addColumn('users', 'last_login_at', fn (Blueprint $t) => $t->timestamp('last_login_at')->nullable()->after('remember_token'));

        // 6. Catálogo financiero normalizado.
        if (! Schema::hasTable('categorias_financieras')) {
            Schema::create('categorias_financieras', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 100);
                $table->string('naturaleza', 10); // ingreso | egreso
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['nombre', 'naturaleza'], 'categorias_financieras_nombre_naturaleza_unique');
            });
        }

        $this->addForeignColumn('ingresos', 'categoria_id', 'categorias_financieras', 'tipo');
        $this->addForeignColumn('egresos', 'categoria_id', 'categorias_financieras', 'tipo_egreso');

        // 7. Presupuesto mensual por categoría.
        if (! Schema::hasTable('presupuestos')) {
            Schema::create('presupuestos', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('anio');
                $table->unsignedTinyInteger('mes');
                $table->unsignedBigInteger('categoria_id');
                $table->decimal('monto_planificado', 12, 2);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['anio', 'mes', 'categoria_id'], 'presupuestos_anio_mes_categoria_unique');
                $table->foreign('categoria_id')->references('id')->on('categorias_financieras')->cascadeOnDelete();
            });
        }

        // 8. Asistencia a cultos.
        if (! Schema::hasTable('asistencias_culto')) {
            Schema::create('asistencias_culto', function (Blueprint $table) {
                $table->id();
                $table->foreignId('miembro_id')->constrained('miembros')->cascadeOnDelete();
                $table->foreignId('culto_id')->constrained('cultos')->cascadeOnDelete();
                $table->date('fecha');
                $table->string('estado')->default('Presente');
                $table->text('observacion')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['miembro_id', 'culto_id', 'fecha'], 'asistencias_culto_miembro_culto_fecha_unique');
                $table->index(['culto_id', 'fecha'], 'asistencias_culto_culto_fecha_index');
            });
        }

        // 9. Auditoría de uso por módulo.
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('modulo', 100);
                $table->string('accion', 50);
                $table->string('entidad_type')->nullable();
                $table->unsignedBigInteger('entidad_id')->nullable();
                $table->json('metadata')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();
                $table->index(['modulo', 'created_at'], 'audit_logs_modulo_created_at_index');
                $table->index(['user_id', 'created_at'], 'audit_logs_user_id_created_at_index');
                if (DB::getDriverName() !== 'sqlite') {
                    $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                }
            });
        }

        // 10. Snapshots de calidad y seguridad.
        if (! Schema::hasTable('metricas_calidad')) {
            Schema::create('metricas_calidad', function (Blueprint $table) {
                $table->id();
                $table->string('fuente', 50);
                $table->string('metrica', 100);
                $table->decimal('valor', 14, 4)->nullable();
                $table->string('texto', 255)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('capturado_en');
                $table->timestamps();
                $table->index(['fuente', 'metrica', 'capturado_en'], 'metricas_calidad_fuente_metrica_fecha_index');
            });
        }

        if (! Schema::hasTable('hallazgos_seguridad')) {
            Schema::create('hallazgos_seguridad', function (Blueprint $table) {
                $table->id();
                $table->string('fuente', 50);
                $table->string('severidad', 20);
                $table->string('estado', 30)->default('abierto');
                $table->string('regla', 255)->nullable();
                $table->string('archivo', 500)->nullable();
                $table->unsignedInteger('linea')->nullable();
                $table->string('fingerprint', 64)->nullable()->unique();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['severidad', 'estado'], 'hallazgos_seguridad_severidad_estado_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hallazgos_seguridad');
        Schema::dropIfExists('metricas_calidad');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('asistencias_culto');
        Schema::dropIfExists('presupuestos');

        // SQLite no elimina el índice al quitar la columna: hay que quitarlo antes.
        $this->dropIndexIfExists('egresos', 'egresos_categoria_id_index');
        $this->dropIndexIfExists('ingresos', 'ingresos_categoria_id_index');
        $this->dropForeignIfExists('egresos', 'categoria_id');
        $this->dropForeignIfExists('ingresos', 'categoria_id');
        $this->dropColumnIfExists('egresos', 'categoria_id');
        $this->dropColumnIfExists('ingresos', 'categoria_id');
        Schema::dropIfExists('categorias_financieras');

        $this->dropColumnIfExists('users', 'last_login_at');
        $this->dropColumnIfExists('actividades', 'costo_real');
        $this->dropColumnIfExists('actividades', 'presupuesto');
        $this->dropColumnIfExists('actividades', 'fecha_fin');
        $this->dropColumnIfExists('inscripciones', 'fecha_vencimiento');
        $this->dropColumnIfExists('miembros', 'fecha_bautismo');
        $this->dropColumnIfExists('miembros', 'fecha_conversion');
        $this->dropColumnIfExists('miembros', 'motivo_baja');
        $this->dropColumnIfExists('miembros', 'fecha_baja');

        // No se eliminan contratos.id_cargo ni egresos.id_lider_iglesia:
        // pueden existir en la BD viva antes de esta migración y contener datos.
    }

    private function addColumn(string $table, string $column, Closure $callback): void
    {
        if (! Schema::hasColumn($table, $column)) {
            Schema::table($table, $callback);
        }
    }

    private function addForeignColumn(string $table, string $column, string $references, ?string $after = null): void
    {
        if (Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($table, $column, $references, $after) {
            $definition = $t->unsignedBigInteger($column)->nullable();
            if ($after && DB::getDriverName() !== 'sqlite') {
                $definition->after($after);
            }
            $t->index($column, "{$table}_{$column}_index");
            if (DB::getDriverName() !== 'sqlite') {
                $t->foreign($column)->references('id')->on($references)->nullOnDelete();
            }
        });
    }

    private function dropForeignIfExists(string $table, string $column): void
    {
        if (DB::getDriverName() === 'sqlite' || ! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign([$column]));
        } catch (\Throwable) {
            // La FK pudo no haber sido creada por esta migración.
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (Schema::hasTable($table) && Schema::hasIndex($table, $index)) {
            Schema::table($table, fn (Blueprint $t) => $t->dropIndex($index));
        }
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        if (Schema::hasColumn($table, $column)) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
        }
    }
};
```

### 13.1 Migración de índices

Archivo real: `database/migrations/2026_10_28_000002_add_kpi_indexes.php`.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->verificarAsistenciasDuplicadas();

        $this->index('miembros', ['estado', 'fecha_ingreso'], 'idx_miembros_estado_ingreso');
        $this->index('miembros', ['f_nacimiento'], 'idx_miembros_f_nacimiento');
        $this->index('miembros', ['sexo'], 'idx_miembros_sexo');

        $this->index('ingresos', ['fecha'], 'idx_ingresos_fecha');
        $this->index('ingresos', ['id_culto', 'fecha'], 'idx_ingresos_culto_fecha');
        $this->index('ingresos', ['id_actividad'], 'idx_ingresos_actividad');
        $this->index('ingresos', ['tipo'], 'idx_ingresos_tipo');
        $this->index('ingresos', ['categoria_id'], 'idx_ingresos_categoria');

        $this->index('egresos', ['fecha'], 'idx_egresos_fecha');
        $this->index('egresos', ['id_contrato'], 'idx_egresos_contrato');
        $this->index('egresos', ['id_lider_iglesia'], 'idx_egresos_lider');
        $this->index('egresos', ['tipo_egreso'], 'idx_egresos_tipo');
        $this->index('egresos', ['categoria_id'], 'idx_egresos_categoria');

        $this->unique('asistencias_sesion', ['sesion_id', 'miembro_id'], 'uq_asistencias_sesion_miembro');
        $this->index('asistencias_sesion', ['miembro_id'], 'idx_asistencias_miembro');
        $this->index('asistencias_sesion', ['sesion_id', 'estado'], 'idx_asistencias_sesion_estado');

        $this->index('sesiones', ['id_curso', 'fecha'], 'idx_sesiones_curso_fecha');
        $this->index('sesiones', ['id_docente', 'fecha'], 'idx_sesiones_docente_fecha');
        $this->index('inscripciones', ['curso_id', 'estado'], 'idx_inscripciones_curso_estado');
        $this->index('inscripciones', ['fecha_vencimiento'], 'idx_inscripciones_vencimiento');
        $this->index('pago_cursos', ['inscripcion_id', 'fecha_pago'], 'idx_pagos_inscripcion_fecha');
        $this->index('pago_cursos', ['fecha_pago'], 'idx_pagos_fecha');

        $this->index('actividades', ['fecha', 'estado'], 'idx_actividades_fecha_estado');
        $this->index('actividades', ['id_ministerio', 'fecha'], 'idx_actividades_ministerio_fecha');
        $this->index('seguimiento_actividades', ['id_actividad', 'fecha_registro'], 'idx_seguimiento_actividad_fecha');

        $this->index('contratos', ['id_miembro', 'fecha_inicio', 'fecha_fin'], 'idx_contratos_miembro_fechas');
        $this->index('contratos', ['fecha_fin', 'activo'], 'idx_contratos_fin_activo');
        $this->index('contratos', ['id_cargo'], 'idx_contratos_cargo');

        $this->index('miembro_ministerio', ['ministerio_id', 'deleted_at'], 'idx_miembro_ministerio_ministerio');
        $this->index('miembro_ministerio', ['miembro_id', 'fecha_fin'], 'idx_miembro_ministerio_miembro');

        $this->index('roles', ['nombre'], 'idx_roles_nombre');
        $this->index('users', ['last_login_at'], 'idx_users_last_login');
        $this->index('audit_logs', ['modulo', 'created_at'], 'idx_audit_logs_modulo_fecha');
        $this->index('audit_logs', ['user_id', 'created_at'], 'idx_audit_logs_user_fecha');
        $this->index('metricas_calidad', ['fuente', 'metrica', 'capturado_en'], 'idx_metricas_calidad_fuente_fecha');
        $this->index('hallazgos_seguridad', ['severidad', 'estado'], 'idx_hallazgos_severidad_estado');
    }

    public function down(): void
    {
        // Los índices se eliminan solo si existen; no se tocan datos.
        foreach ([
            ['miembros', 'idx_miembros_estado_ingreso'],
            ['miembros', 'idx_miembros_f_nacimiento'],
            ['miembros', 'idx_miembros_sexo'],
            ['ingresos', 'idx_ingresos_fecha'],
            ['ingresos', 'idx_ingresos_culto_fecha'],
            ['ingresos', 'idx_ingresos_actividad'],
            ['ingresos', 'idx_ingresos_tipo'],
            ['ingresos', 'idx_ingresos_categoria'],
            ['egresos', 'idx_egresos_fecha'],
            ['egresos', 'idx_egresos_contrato'],
            ['egresos', 'idx_egresos_lider'],
            ['egresos', 'idx_egresos_tipo'],
            ['egresos', 'idx_egresos_categoria'],
            ['asistencias_sesion', 'uq_asistencias_sesion_miembro'],
            ['asistencias_sesion', 'idx_asistencias_miembro'],
            ['asistencias_sesion', 'idx_asistencias_sesion_estado'],
            ['sesiones', 'idx_sesiones_curso_fecha'],
            ['sesiones', 'idx_sesiones_docente_fecha'],
            ['inscripciones', 'idx_inscripciones_curso_estado'],
            ['inscripciones', 'idx_inscripciones_vencimiento'],
            ['pago_cursos', 'idx_pagos_inscripcion_fecha'],
            ['pago_cursos', 'idx_pagos_fecha'],
            ['actividades', 'idx_actividades_fecha_estado'],
            ['actividades', 'idx_actividades_ministerio_fecha'],
            ['seguimiento_actividades', 'idx_seguimiento_actividad_fecha'],
            ['contratos', 'idx_contratos_miembro_fechas'],
            ['contratos', 'idx_contratos_fin_activo'],
            ['contratos', 'idx_contratos_cargo'],
            ['miembro_ministerio', 'idx_miembro_ministerio_ministerio'],
            ['miembro_ministerio', 'idx_miembro_ministerio_miembro'],
            ['roles', 'idx_roles_nombre'],
            ['users', 'idx_users_last_login'],
            ['audit_logs', 'idx_audit_logs_modulo_fecha'],
            ['audit_logs', 'idx_audit_logs_user_fecha'],
            ['metricas_calidad', 'idx_metricas_calidad_fuente_fecha'],
            ['hallazgos_seguridad', 'idx_hallazgos_severidad_estado'],
        ] as [$tabla, $indice]) {
            if (Schema::hasTable($tabla) && Schema::hasIndex($tabla, $indice)) {
                Schema::table($tabla, fn (Blueprint $t) => $t->dropIndex($indice));
            }
        }
    }

    private function index(string $table, array $columns, string $name): void
    {
        if (! $this->todasLasColumnas($table, $columns)) {
            return;
        }
        if (! Schema::hasIndex($table, $columns)) {
            Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
        }
    }

    private function unique(string $table, array $columns, string $name): void
    {
        if (! $this->todasLasColumnas($table, $columns)) {
            return;
        }
        if (! Schema::hasIndex($table, $columns, 'unique')) {
            Schema::table($table, fn (Blueprint $t) => $t->unique($columns, $name));
        }
    }

    private function todasLasColumnas(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    private function verificarAsistenciasDuplicadas(): void
    {
        $duplicados = DB::table('asistencias_sesion')
            ->select('sesion_id', 'miembro_id')
            ->whereNull('deleted_at')
            ->groupBy('sesion_id', 'miembro_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(3)
            ->get();

        if ($duplicados->isNotEmpty()) {
            throw new RuntimeException(
                'Hay asistencias duplicadas. Deduplica antes de crear el UNIQUE: '
                .$duplicados->toJson()
            );
        }
    }
};
```

> **Orden de ejecución:** primero `2026_10_28_000001` (columnas/tablas), luego `2026_10_28_000002` (índices). La segunda migración verifica duplicados y **aborta** si encuentra asistencias repetidas; es intencional para no perder información.

### 13.2 Backfill sugerido del catálogo financiero

Después de crear `categorias_financieras`, sembrar las categorías y hacer backfill. Ejemplo (revisar antes de ejecutar; hacer respaldo):

```sql
INSERT INTO categorias_financieras (nombre, naturaleza, activo, created_at, updated_at)
VALUES
 ('Diezmo', 'ingreso', 1, NOW(), NOW()),
 ('Ofrenda', 'ingreso', 1, NOW(), NOW()),
 ('Donación', 'ingreso', 1, NOW(), NOW()),
 ('Curso', 'ingreso', 1, NOW(), NOW()),
 ('Actividad', 'ingreso', 1, NOW(), NOW()),
 ('Otros ingresos', 'ingreso', 1, NOW(), NOW()),
 ('Personal', 'egreso', 1, NOW(), NOW()),
 ('Mantenimiento', 'egreso', 1, NOW(), NOW()),
 ('Servicios', 'egreso', 1, NOW(), NOW()),
 ('Ayuda Social', 'egreso', 1, NOW(), NOW()),
 ('Otros egresos', 'egreso', 1, NOW(), NOW());

UPDATE ingresos i
JOIN categorias_financieras cf
  ON cf.naturaleza = 'ingreso'
 AND cf.nombre = CASE
       WHEN i.tipo LIKE 'Diezmo%' THEN 'Diezmo'
       WHEN i.tipo LIKE 'Ofrenda%' THEN 'Ofrenda'
       WHEN i.tipo LIKE 'Donaci%' THEN 'Donación'
       WHEN i.tipo LIKE 'Inscripci%' THEN 'Curso'
       WHEN i.tipo LIKE '%Actividad%' THEN 'Actividad'
       ELSE 'Otros ingresos'
     END
SET i.categoria_id = cf.id
WHERE i.deleted_at IS NULL
  AND i.categoria_id IS NULL;

UPDATE egresos e
JOIN categorias_financieras cf
  ON cf.naturaleza = 'egreso'
 AND cf.nombre = CASE
       WHEN e.id_contrato IS NOT NULL OR e.tipo_egreso LIKE 'Salario%' OR e.tipo_egreso LIKE 'Sueldo%' THEN 'Personal'
       WHEN e.tipo_egreso LIKE 'Mantenimiento%' THEN 'Mantenimiento'
       WHEN e.tipo_egreso LIKE 'Servicio%' THEN 'Servicios'
       WHEN e.tipo_egreso LIKE 'Ayuda Social%' THEN 'Ayuda Social'
       ELSE 'Otros egresos'
     END
SET e.categoria_id = cf.id
WHERE e.deleted_at IS NULL
  AND e.categoria_id IS NULL;
```

---

## 14. Cómo implementarlo en Laravel sin duplicar lógica

### 14.1 Servicio único de KPIs

Crear `app/Services/KpiService.php` con un método por bloque, no un método por tarjeta. Ejemplo de firma:

```php
final class KpiService
{
    /** @return array<string, array<string, mixed>> */
    public function dashboard(User $user, string $desde, string $hasta, string $hoy): array;
    public function membresia(string $desde, string $hasta): array;
    public function ministerios(string $desde, string $hasta, string $hoy): array;
    public function academia(string $desde, string $hasta): array;
    public function finanzas(string $desde, string $hasta): array;
    public function cobranza(): array;
    public function personal(string $hoy): array;
    public function seguridad(): array;
}
```

Reglas:
- El servicio recibe `CarbonImmutable` o strings `Y-m-d`, nunca usa `now()` internamente para los rangos.
- Todos los métodos filtran `deleted_at` explícitamente en cada tabla unida.
- Los porcentajes se devuelven como `?float` (`null` cuando el denominador es 0) y la vista decide mostrar “—”.
- Los montos se devuelven redondeados a 2 decimales, pero se conserva el `DECIMAL` en la consulta.
- No hacer `SELECT *` en las consultas de KPI.

### 14.2 Cache

Los KPIs mensuales cambian poco. Cachear 5–15 minutos con una clave que incluya el período y el rol:

```php
$kpis = Cache::remember("kpi:finanzas:{$desde}:{$hasta}", now()->addMinutes(10), fn () => $kpi->finanzas($desde, $hasta));
```

Invalidar al crear/editar `ingresos`, `egresos`, `pago_cursos`, `inscripciones`, `asistencias_sesion`. Para el dashboard en tiempo real, no cachear las tarjetas de alerta (D5, E6, G2, F2).

### 14.3 Rutas y permisos

Agregar a `config/permisos.php`:
```php
'reportes.academia' => ['label' => 'Reporte de Academia', 'acciones' => ['ver']],
'reportes.ministerios' => ['label' => 'Reporte de Ministerios', 'acciones' => ['ver']],
'reportes.seguridad' => ['label' => 'Reporte de Seguridad', 'acciones' => ['ver']],
'reportes.calidad' => ['label' => 'Reporte de Calidad', 'acciones' => ['ver']],
```

Y en `PermisosPorRolSeeder::DEFINICIONES`:
- Secretario: `reportes.academia`, `reportes.ministerios`.
- Tesorero: `reportes.financiero` (ya lo tiene por `array_is_list`), `reportes.academia` solo si se decide.
- Administrador: todo por código.
- Docente: solo `reportes.asistencia` (ya lo tiene).

### 14.4 Orden de implementación recomendado

1. **Semana 1:** corregir `Ingreso::total()` (filtrar `pago_cursos.deleted_at`) y deduplicar asistencias.
2. **Semana 2:** implementar A1, A2, B2, B3, B4, B5, C1, C2, C3, D1, D2, D3, E1, E2, E3, F1, F2, G1, G2 (los imprescindibles).
3. **Semana 3:** migración de datos faltantes + índices + backfill financiero.
4. **Semana 4:** KPIs avanzados (A6, D6, D7, E4, E6, F4, G4, H1–H5) y reportes nuevos.
5. **Continuo:** snapshots de calidad y revisión mensual de umbrales con el pastor/tesorero.

### 14.5 Estado actual de la implementación

Ya implementado en el repositorio:

- `app/Services/KpiService.php` con `dashboard()`, `membresia()`, `ministerios()`, `academia()`, `finanzas()`, `cobranza()`, `personal()` y `seguridad()`.
- `DashboardController::index()` inyecta el servicio y entrega `$kpis` a la vista, filtrado por rol (Administrador/Pastor, Secretario y Tesorero).
- `resources/views/dashboard.blade.php` muestra el bloque **Indicadores clave** (semáforos de A2, B2, B5, C1, C2, D1, D6, E1, F2 y G2) y **Alertas accionables** (contratos por vencer, actividades atrasadas, cursos con saldo y usuarios sin rol).
- `Ingreso::total()` y `scopeConPago()` filtran `pago_cursos.deleted_at` dentro del `LEFT JOIN`, sin perder ingresos que conservan su propio monto.
- `tests/Feature/KpiServiceTest.php` cubre los cálculos y el filtrado por rol.
- Las asistencias se deduplican por `(sesion_id, miembro_id)` tomando el `MAX(id)` vigente.

Pendiente para las siguientes iteraciones: cache por período/rol, reportes de academia/ministerios/seguridad, snapshots de calidad (área H) y backfill de `categorias_financieras`.

---

## 15. Apéndice — normalización de estados

Usar estas expresiones en SQL y replicarlas en PHP con un `match` para no divergir.

```sql
-- Miembros
CASE
  WHEN m.estado IS NULL OR TRIM(m.estado) = '' THEN 'Sin estado'
  WHEN LOWER(m.estado) LIKE 'activ%' THEN 'Activo'
  WHEN LOWER(m.estado) LIKE 'inactiv%' THEN 'Inactivo'
  WHEN LOWER(m.estado) LIKE 'fallec%' THEN 'Fallecido'
  WHEN LOWER(m.estado) LIKE 'traslad%' THEN 'Trasladado'
  ELSE m.estado
END

-- Actividades
CASE
  WHEN a.estado IS NULL OR TRIM(a.estado) = '' THEN 'Sin estado'
  WHEN LOWER(a.estado) LIKE 'program%' THEN 'Programado'
  WHEN LOWER(a.estado) LIKE 'en progreso%' OR LOWER(a.estado) LIKE 'en curso%' THEN 'En Progreso'
  WHEN LOWER(a.estado) LIKE 'complet%' OR LOWER(a.estado) LIKE 'finaliz%' THEN 'Completado'
  WHEN LOWER(a.estado) LIKE 'cancel%' THEN 'Cancelado'
  ELSE a.estado
END

-- Asistencia
CASE
  WHEN LOWER(a.estado) LIKE 'present%' THEN 'Presente'
  WHEN LOWER(a.estado) LIKE 'tard%' THEN 'Tardanza'
  WHEN LOWER(a.estado) LIKE 'ausent%' THEN 'Ausente'
  WHEN LOWER(a.estado) LIKE 'justific%' THEN 'Justificado'
  ELSE COALESCE(NULLIF(TRIM(a.estado), ''), 'Sin estado')
END

-- Ingresos
CASE
  WHEN i.tipo LIKE 'Diezmo%' THEN 'Diezmo'
  WHEN i.tipo LIKE 'Ofrenda%' THEN 'Ofrenda'
  WHEN i.tipo LIKE 'Donaci%' THEN 'Donación'
  WHEN i.tipo LIKE 'Inscripci%' THEN 'Curso'
  WHEN i.tipo LIKE '%Actividad%' THEN 'Actividad'
  ELSE COALESCE(NULLIF(TRIM(i.tipo), ''), 'Sin tipo')
END

-- Egresos
CASE
  WHEN e.id_contrato IS NOT NULL OR e.tipo_egreso LIKE 'Salario%' OR e.tipo_egreso LIKE 'Sueldo%' THEN 'Personal'
  WHEN e.tipo_egreso LIKE 'Mantenimiento%' THEN 'Mantenimiento'
  WHEN e.tipo_egreso LIKE 'Servicio%' THEN 'Servicios'
  WHEN e.tipo_egreso LIKE 'Ayuda Social%' THEN 'Ayuda Social'
  ELSE COALESCE(NULLIF(TRIM(e.tipo_egreso), ''), 'Sin tipo')
END
```

**Validación previa** (ejecutar una vez y guardar el resultado):
```sql
SELECT 'miembros.estado' AS campo, estado AS valor, COUNT(*) AS filas FROM miembros WHERE deleted_at IS NULL GROUP BY estado
UNION ALL SELECT 'actividades.estado', estado, COUNT(*) FROM actividades WHERE deleted_at IS NULL GROUP BY estado
UNION ALL SELECT 'asistencias.estado', estado, COUNT(*) FROM asistencias_sesion WHERE deleted_at IS NULL GROUP BY estado
UNION ALL SELECT 'ingresos.tipo', tipo, COUNT(*) FROM ingresos WHERE deleted_at IS NULL GROUP BY tipo
UNION ALL SELECT 'egresos.tipo_egreso', tipo_egreso, COUNT(*) FROM egresos WHERE deleted_at IS NULL GROUP BY tipo_egreso
ORDER BY campo, filas DESC;
```

---

## 16. Checklist de aceptación

- [ ] Cada consulta filtra `deleted_at IS NULL` en la tabla base y en **cada** tabla unida (excepto `permisos`, que no tiene la columna).
- [ ] Los pivotes (`role_user`, `docente_curso`, `miembro_ministerio`) filtran `deleted_at` explícitamente.
- [ ] `asistencias_sesion` se deduplica por `(sesion_id, miembro_id)` antes de contar.
- [ ] Los ingresos totales salen solo de `ingresos`; `pago_cursos` se usa para detalle o `COALESCE` cuando `monto_total` es `NULL`.
- [ ] Los estados se normalizan con `CASE`/`LIKE`; no se asume un catálogo cerrado.
- [ ] Los denominadores usan `NULLIF(..., 0)` y la vista muestra “—” cuando el KPI es `null`.
- [ ] Los montos se redondean con `ROUND(x, 2)`.
- [ ] Las fechas se pasan desde PHP con la zona `America/La_Paz`; no se depende de `CURDATE()`.
- [ ] Los KPIs con pocos datos muestran una advertencia de “muestra insuficiente”.
- [ ] El docente solo ve sus cursos y su asistencia; nunca finanzas globales.
- [ ] El quality gate bloquea el release si está en `ERROR`.

