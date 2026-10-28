# Prueba de caja blanca — `EgresoController::create()`

**Sistema:** Gestión de Egresos
**Método:** `create()` de `app/Http/Controllers/EgresoController.php`
**Técnica:** Cobertura de instrucciones
**Fecha:** 22/10/2026

---

## PARTE 1. Definición y análisis del método

### 1.1. Alcance

La prueba se limita al método `create()`, encargado de preparar los datos y mostrar el formulario de registro de un egreso. No se prueban los métodos `store()`, `update()` ni `destroy()`.

### 1.2. Código analizado

```php
public function create()
{
    $contratos = Contrato::with('miembro')->get();
    $lideres = LiderIglesia::with('cargo')->get()->sortBy('nombre')->values();
    $saldoDisponible = $this->saldoDisponible();

    return view('egresos.create', compact('contratos', 'lideres', 'saldoDisponible'));
}
```

### 1.3. Inventario de instrucciones

| ID | Instrucción | Línea |
|----|-------------|-------|
| C1 | `Contrato::with('miembro')->get()` | 40 |
| C2 | `LiderIglesia::with('cargo')->get()->sortBy('nombre')->values()` | 41 |
| C3 | `$saldoDisponible = $this->saldoDisponible()` | 42 |
| C4 | `return view('egresos.create', ...)` | 44 |

### 1.4. Decisiones

El método no contiene estructuras condicionales ni bucles: es un camino lineal. Por eso no tiene decisiones propias y un solo caso de prueba ya ejecuta todas sus instrucciones.

La única decisión relacionada está dentro de `saldoDisponible()` (`if ($ignorarId)`). Como `create()` lo invoca sin argumento, solo se ejecuta la rama falsa; la rama verdadera pertenece a `edit()` y `update()`.

---

## PARTE 2. Diseño de los casos de prueba

### 2.1. Estrategia

Se diseñaron cuatro casos para comprobar el método en situaciones distintas:

1. Con datos completos (ingresos, egresos, contrato y líderes).
2. Sin datos en la base.
3. Con registros eliminados lógicamente.
4. Con varios ingresos y egresos para validar el saldo.

### 2.2. Plantilla consolidada

| Campo | Descripción |
|---|---|
| ID de prueba | CB-EC |
| Sistema | Gestión de Egresos |
| Objetivo | Comprobar que se ejecutan las cuatro instrucciones de `create()` con datos válidos |
| Caso de prueba 1 | Ingreso de Bs 1.000, egreso de Bs 300, un contrato y dos líderes |
| Resultado esperado 1 | HTTP 200, vista `egresos.create`, saldo Bs 700,00, un contrato y líderes ordenados |
| Caso de prueba 2 | Acceso a `/egresos/create` con la base de datos vacía |
| Resultado esperado 2 | HTTP 200, colecciones vacías y saldo Bs 0,00 |
| Caso de prueba 3 | Contrato y líder eliminados lógicamente antes de abrir el formulario |
| Resultado esperado 3 | Las colecciones llegan vacías; no se muestran los eliminados |
| Caso de prueba 4 | Ingresos de Bs 1.500 y Bs 500; egresos de Bs 200 y Bs 300 |
| Resultado esperado 4 | Saldo Bs 1.500,00 |
| Instrucciones cubiertas | C1, C2, C3 y C4 |
| Resultado obtenido | 4 pruebas aprobadas, 19 aserciones |
| Estado | Aprobado |

### 2.3. Matriz de cobertura

| Instrucción | Caso 1 | Caso 2 | Caso 3 | Caso 4 |
|---|:--:|:--:|:--:|:--:|
| C1 | ✔ | ✔ | ✔ | ✔ |
| C2 | ✔ | ✔ | ✔ | ✔ |
| C3 | ✔ | ✔ | ✔ | ✔ |
| C4 | ✔ | ✔ | ✔ | ✔ |

**Cobertura de instrucciones: 4 de 4 = 100 %.**
**Cobertura de decisiones propia del método: no aplica (no tiene decisiones).**

---

## PARTE 3. Ejecución, resultados y conclusiones

### 3.1. Ejecución

```text
php artisan test --filter=EgresoCreateCajaBlancaTest

PASS  Tests\Feature\EgresoCreateCajaBlancaTest
✓ 4 pruebas aprobadas
✓ 19 aserciones
✓ 0 fallos
```

### 3.2. Resultados

| Métrica | Valor |
|---|---|
| Casos diseñados | 4 |
| Casos aprobados | 4 |
| Instrucciones cubiertas | 4/4 (100 %) |
| Decisiones propias | 0 |
| Estado final | Aprobado |

### 3.3. Observaciones

1. El método tiene un único camino de ejecución: no hay condiciones ni bucles.
2. `C2` carga todos los líderes y los ordena en memoria; para muchos registros conviene ordenar en la consulta con `orderBy('nombre')`.
3. `C1` no filtra contratos de miembros eliminados; la vista mostraría «N/A» como nombre.
4. `C3` puede devolver un saldo negativo si los egresos superan a los ingresos, y la vista lo muestra sin advertencia.
5. Los registros con borrado lógico se excluyen automáticamente por `SoftDeletes`, comportamiento verificado en el caso 3.

### 3.4. Conclusión

El método `create()` queda cubierto al **100 % en instrucciones** con los cuatro casos diseñados. Al no contener decisiones, la cobertura de instrucciones es suficiente para este método; la cobertura de decisiones deberá aplicarse a `store()`, `edit()` y `update()`, donde sí existen bifurcaciones.

**Archivo de pruebas:** `tests/Feature/EgresoCreateCajaBlancaTest.php`.
