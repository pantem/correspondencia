# Sistema de Correspondencia — Comisión para la Reconstrucción

Aplicación web para registrar, digitalizar, turnar, dar seguimiento y atender la correspondencia institucional, con folios consecutivos por año, trazabilidad de movimientos y evidencia documental.

## Stack

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.3 + Laravel 13 (API REST + SPA fallback) |
| Frontend | Vue 3 + Vite + Pinia + Vue Router + Tailwind CSS 4 |
| Auth | Laravel Sanctum (token Bearer) + roles/permisos (Gate `can:`) |
| Base de datos | PostgreSQL (servidor corporativo; local vía Docker) |
| Almacenamiento | Disco privado (`storage/app/private`), driver intercambiable vía `FILESYSTEM_DISK` (local \| s3) |
| Correo (dev) | Mailpit (`localhost:8025`) |
| Deploy | Docker (Dockerfile multi-stage) + `render.yaml` (Render) |
| Testing | PHPUnit (72 tests / 344 aserciones) + Laravel Pint |

## Fases del proyecto

| Fase | Contenido | RFs | Estado |
|---|---|---|---|
| **F0 — Infra** | Scaffold Laravel+Vue, Dockerfile, docker-compose, render.yaml, health `/up` | — | ✅ Completada |
| **F1 — Núcleo** | Auth/Sanctum, roles/permisos, catálogos admin, registro + folios atómicos, upload PDF, búsqueda, bitácora básica | RF-01…10, 55…60 | ✅ Completada |
| **F2 — Turnados** | Turnado multi-área e interno, tipos de asignación (atención/conocimiento), estados y transiciones, reasignación con historial, resolución por responsable vigente, bandejas | RF-11…29 | ✅ Completada |
| **F3 - Volantes** | Volantes PDF + QR, versionado, impresion, marca de turnado interno | RF-30.37 | Completada |
| **F4 - Evidencias** | Acuses (3 tipos de archivo), entrega antes de acuse, "acuse pendiente", correccion de turnos pendientes, bitacora completa | RF-38.54, 63 | Completada |
| **F5 - Reportes + pulido** | Dashboards, exportaciones CSV/PDF, validacion con usuarios finales | RF-61.64 | Completada |

> F1+F2 primero porque todo lo demás cuelga del ciclo documento → turno → volante → acuse.

---

## F1 — Núcleo (completada)

### Requerimientos funcionales atacados

| RF | Descripción | Implementación |
|---|---|---|
| RF-01 | Registrar correspondencia recibida y enviada | `POST /api/documents` con campo `sentido` (`recibida`\|`enviada`); enum `SentidoCorrespondencia` |
| RF-02 | Capturar datos generales (folio, tipo, fechas, oficio, remitente, asunto, canal, prioridad, plazo…) | `StoreDocumentRequest` valida 16 campos; `datos_extra` JSON para vivienda (RF-05) |
| RF-03 | Asociar documentos digitalizados | `POST /api/documents/{folio}/archivos` — PDF, `mimes:pdf max:20480`, SHA-256, disco privado |
| RF-04 | Campos obligatorios/condicionales por diseño | Obligatorios: `sentido`, `tipo_correspondencia`, `fecha_documento`, `asunto`; resto opcional (pendiente afinar por tipo) |
| RF-05 | Datos especializados solo cuando correspondan | Columna `datos_extra` JSON en `documents` |
| RF-06 | Folio único, consecutivo, por año | `FolioService::siguiente()` — `DB::transaction` + `SELECT … FOR UPDATE` + retry por unicidad |
| RF-07 | Formato `0001-2027` | `sprintf('%04d-%d', secuencia, anio)` → `0001-2026` |
| RF-08 | Reinicio anual sin reutilizar folios | Contador en tabla `folios_contador` (PK `anio`); constraint unique en `documents.folio` |
| RF-09 | Folio conservado en todo el ciclo | `documents.folio` inmutable (sin setter); `#[RouteKey('folio')]` |
| RF-10 | Independencia folio / oficio_original / id | Tres columnas distintas + test `folio_no_depende_de_oficio` |
| RF-55…57 | Módulo admin catálogos + auditoría de cambios | CRUD `/api/admin/{areas,cargos,titulares}` tras `can:admin.catalogs`; `AuditService` guarda old/new values |
| RF-58 | No alterar históricos por cambios organizacionales | Solo `activo` booleano; nunca se borran registros (soft-estado) |
| RF-59 | Usuarios y permisos | `/api/admin/users` con `role_ids`, password hasheado, activo; tras `can:admin.users` |
| RF-60 | Búsqueda multi-criterio | `GET /api/documents?folio=&asunto=&remitente=&oficio=&estado=&fecha_desde=&fecha_hasta=` paginado |

### Roles y permisos seed

| Rol | Permisos |
|---|---|
| `admin` | todos (7) |
| `oficialia` | documents.view/create/update/upload, audit.view |
| `area` | documents.view, documents.upload |
| `responsable` | documents.view, documents.update |

Permisos: `documents.view`, `documents.create`, `documents.update`, `documents.upload`, `admin.catalogs`, `admin.users`, `audit.view`.

### Bitácora (F1 parcial — RF-63 completo en F4)

`audit_logs` + `AuditService::registrar()` con acción, entidad morph, old/new values, IP. Registrado en: login, creación/edición de documentos, altas/bajas de catálogos, alta de usuarios.

### Verificación F1

- **36 tests / 132 aserciones** (`php artisan test --compact`): auth (login/logout/me/rate-limit/422), registro+folios (consecutivos, independencia, 403, validaciones), búsqueda, upload PDF (acepta/rechaza), update+audit, CRUD catálogos, CRUD usuarios, `FolioService` (formato, incremento, reinicio anual).
- **E2E curl** contra `localhost:8080`: login → token → crear `0001-2026`/`0002-2026` → buscar → detalle → subir PDF (sha256) → descarga `application/pdf` → 401 sin token → logout.
- Pint limpio, build Vite OK, contenedores Docker healthy.

---

## F2 — Turnados (completada)

### Requerimientos funcionales atacados

| RF | Descripción | Implementación |
|---|---|---|
| RF-11 | Oficialía turna a una o varias áreas | `POST /api/documents/{folio}/turnos` con array `turnos` en un mismo acto (transacción) |
| RF-12 | Turnados internos sin regresar a Oficialía | Usuario con turno activo como responsable puede turnar; `turno_padre_id` marca el origen |
| RF-13 | Sin jerarquía obligatoria | Turnado libre entre áreas con permiso `documents.turn`; sin validación de jerarquía |
| RF-14 | Área destino, instrucciones, fecha, responsable y tipo por turno | Columnas en `turnos` + edición de instrucciones/plazo solo en estado `pendiente` |
| RF-15 | Movimiento independiente por turno | Tabla `movimientos` (turnado/reasignación/seguimiento/resolución/cancelación) con `turno_id` |
| RF-16 | Reasignaciones con historial | `POST /api/turnos/{id}/reasignar` — movimiento con `responsable_anterior_id` |
| RF-17 | Estado independiente por turno | `turnos.estado` propio; resolver uno no toca a los demás (RN-09) |
| RF-18 | Consultar todos los turnos de un documento | `GET /api/documents/{folio}/turnos` |
| RF-19…22 | Atención vs conocimiento | Enum `TipoAsignacion`; `vencido()` solo aplica a atención (RF-21); indicadores diferenciados |
| RF-23…24 | Estados y transiciones | `pendiente → en_tramite → resuelto` (+ `cancelado`); transiciones validadas en actions |
| RF-25 | Solo responsable vigente resuelve | `ResolverTurno` aborta 403 si `responsable_id !== usuario` (RN-08) |
| RF-26 | Acciones de seguimiento y evidencias | `POST /api/turnos/{id}/seguimiento` (notas + movimiento; avanza a `en_tramite`); evidencias vía `archivos.movimiento_id` |
| RF-27 | No cerrar otros turnos al resolver | Modelo por turno; test `resolver_un_turno_no_cierra_los_demas` |
| RF-28 | Identificar pendientes/en trámite/resueltos/vencidos | `GET /api/turnos?estado=&vencido=1&responsable=me` + badge `vencido` |
| RF-29 | Resolución en historial, sin ediciones libres | Movimiento `resolucion` + `resuelto_por/at`; PUT/cancelar/resolver rechazados con 409 si no está abierto |

### Reglas de negocio verificadas

- **RN-02/03**: turnado libre; interno sin pasar por Oficialía.
- **RN-05/06**: seguimiento independiente por área + responsable vigente por turno.
- **RN-08/09**: solo el responsable vigente cierra; cerrar uno no cierra los demás.
- **RN-13…17**: tipos atención/conocimiento; conocimiento nunca vence (RN-16).
- **RN-32**: sin jerarquía entre áreas.

### Permisos nuevos (F2)

`documents.turn` (turnar), `turns.update` (reasignar/editar/cancelar), `turns.respond` (seguimiento/resolver).

| Rol | Nuevos permisos |
|---|---|
| admin | todos |
| oficialia | documents.turn, turns.update, turns.respond |
| area | documents.turn, turns.update, turns.respond (+ upload) |
| responsable | turns.respond (+ upload) |

### Verificación F2

- **72 tests / 344 aserciones** (16 nuevos en `TurnadoTest`): multi-turno en un acto, turnado interno con `turno_padre_id`, 403 sin turno activo o sin permiso, validaciones, listado RF-18, seguimiento→`en_trámite`, solo responsable vigente resuelve, resolver no cierra otros, reasignación con `responsable_anterior`, conocimiento nunca vencido/atención vencida, bandejas con filtros, turno resuelto inmutable (409×4), edición solo en pendiente, cancelación con motivo + bitácora.
- **E2E curl**: crear doc → turnar a 2 áreas (tipos distintos, `es_turnado_inicial=true`) → listado → seguimiento (`en_tramite`) → resolver como responsable (`resuelto` + 409 al repetir) → reasignar → filtros de bandeja (pendiente/conocimiento/vencidos) → cancelar → bitácora `turno.*`.
- Pint limpio, build Vite OK, Docker reconstruido + seed de permisos F2.

### API nueva (F2)

```
GET    /api/documents/{folio}/turnos        # RF-18 (documents.view)
POST   /api/documents/{folio}/turnos        # multi-área en un acto (documents.turn)
GET    /api/turnos                          # bandeja: estado, area_id, tipo_asignacion, responsable=me|id, vencido=1, sin_responsable
GET    /api/turnos/{id}                     # detalle + movimientos + evidencias
PUT    /api/turnos/{id}                     # instrucciones/plazo, solo pendiente (turns.update)
POST   /api/turnos/{id}/reasignar           # {responsable_id, notas} (turns.update | responsable vigente)
POST   /api/turnos/{id}/seguimiento         # {notas} → en_tramite (turns.respond)
POST   /api/turnos/{id}/resolver            # solo responsable vigente (turns.respond)
POST   /api/turnos/{id}/cancelar            # {motivo} (turns.update)
GET    /api/catalogos/usuarios              # select de usuarios activos (reasignación)
```

---

## Desarrollo local

```bash
docker compose up -d --build          # app :8080, mysql :3306, mailpit :8025
docker compose exec app php artisan migrate --seed
npm run build                          # o npm run dev en desarrollo
php artisan test --compact             # tests
vendor/bin/pint                        # formato
```

> El `Dockerfile` hornea el código (sin mount de source): tras cada cambio, `docker compose up -d --build`.

### Credenciales seed

| Perfil | Email | Password |
|---|---|---|
| Administrador | `admin@correspondencia.test` | `password` |
| Oficialía | `oficialia@correspondencia.test` | `password` |

## API (F1)

```
POST   /api/auth/login          # throttle 10/min → {data:{token,user}}
POST   /api/auth/logout
GET    /api/auth/me             # user + permissions[]
GET    /api/documents           # filtros RF-60, paginado {data,meta}
POST   /api/documents           # crea + folio atómico (documents.create)
GET    /api/documents/{folio}   # detalle + archivos + área + usuario
PUT    /api/documents/{folio}   # actualiza + audit (documents.update)
POST   /api/documents/{folio}/archivos   # upload PDF (documents.upload)
GET    /api/documents/{folio}/archivos/{id}  # descarga autenticada
GET    /api/admin/areas|cargos|titulares  # lectura (autenticado)
POST/PUT /api/admin/areas|cargos|titulares  # (admin.catalogs)
GET    /api/admin/users         # paginado (admin.users)
POST/PUT /api/admin/users       # (admin.users)
GET    /api/catalogos/roles
GET    /api/audit-logs          # paginado (audit.view)
```

Convención: respuestas `{data}` (+ `meta` en paginación); errores 422 con `errors`; auth por `Authorization: Bearer`.

## Estructura

```
app/
  Actions/                        # CrearDocumento, TurnarDocumento, ResolverTurno,
                                  # RegistrarSeguimiento, ReasignarTurno
  Enums/                          # Sentido, Tipo, Prioridad, Estado, Canal, TipoArchivo,
                                  # TipoAsignacion, EstadoTurno, TipoMovimiento
  Http/Controllers/Api/           # Auth, Document, Turno, Admin/*, AuditLog
  Http/Requests/                  # Form Requests con authorize() por permiso
  Http/Resources/                 # Document, Turno, Movimiento, User, Area, Cargo, Titular, Archivo, AuditLog
  Models/                         # User, Document, Turno, Movimiento, Area, Cargo, Titular,
                                  # FolioContador, Role, Permission, Archivo, AuditLog
  Services/FolioService.php       # folios atómicos SELECT FOR UPDATE
  Services/AuditService.php       # bitácora manual
database/migrations/              # 16 migraciones (F1 + F2)
database/seeders/                 # RolePermission, User, Catalog
resources/js/                     # SPA Vue: api/, stores/, router/, views/ (+ TurnosView)
routes/api.php                    # rutas + middleware can: + throttle
tests/Feature/                    # 6 archivos, 52 tests
```

---

## F3 - Volantes (completada)

### Requerimientos funcionales atacados

| RF | Descripción | Implementación |
|---|---|---|
| RF-30 | Volante de turnado por cada turno | `VolanteService::generar()` crea PDF + registro en `volantes`; se invoca automáticamente al turnar (recepción inicial o turnado interno) |
| RF-31 | Conserva folio principal | El volante muestra el `folio` del documento (`turno.document.folio`) |
| RF-32 | Datos del volante | Folio, sentido, tipo, fecha documento/recepción, oficio, remitente, dependencia, cargo, asunto, área destino, tipo de asignación, instrucciones, plazo, responsable, fecha/hora de turnado, emitido por, QR |
| RF-33 | Impresión | `GET /api/volantes/{id}/pdf` (Content-Type application/pdf, inline) |
| RF-34 | Consulta en pantalla | `GET /api/volantes/{id}` (VolanteResource con `url_pdf`) |
| RF-35 | Nueva versión | `POST /api/turnos/{turno}/volantes` (turns.update); corrección de destinatario regenera volante automáticamente |
| RF-36 | Versionado por turno | `volantes.version` único por turno; historial `GET /api/turnos/{turno}/volantes` |
| RF-37 | Marca recepción inicial vs turnado interno | `OrigenVolante` (recepcion_inicial/turnado_interno) según `turno.es_turnado_inicial` |

### Dependencias nuevas

- `barryvdh/laravel-dompdf ^3.1` (renderizado PDF desde Blade)
- `endroid/qr-code ^6.0` (QR a PNG data-uri, nivel M)

### Endpoints

```
GET  /api/documents/{folio}/volantes       # historial por documento
GET  /api/turnos/{id}/volantes             # historial por turno
POST /api/turnos/{id}/volantes            # nueva versión (turns.update)
GET  /api/volantes/{id}                    # datos del volante
GET  /api/volantes/{id}/pdf                # PDF inline
```

---

## F4 - Evidencias (completada)

### Requerimientos funcionales atacados

| RF | Descripción | Implementación |
|---|---|---|
| RF-38 | Listado de volantes por documento/turno | Endpoints anteriores (F3) |
| RF-39-41 | Corrección de destinatario solo en turno pendiente | `PUT /api/turnos/{id}` con area/responsable/tipo; movimiento `TipoMovimiento::Correccion` + audit `turno.corregido`; 409 si resuelto |
| RF-42-43 | Inmutabilidad de turnos resueltos | `abort_unless(estado === pendiente, 409)` en update/cancelar |
| RF-47-52 | Acuse digital (copia sellada) por movimiento | `POST /api/movimientos/{id}/acuse` (documents.upload), PDF max 20MB, versionado, `acuse_pendiente=false` |
| RF-53 | Filtrado de entregas con acuse pendiente | `GET /api/turnos?acuse_pendiente=1&fecha_desde=&fecha_hasta=&responsable=` |
| RF-54 | Los acuses nunca sustituyen versiones anteriores | Cada carga incrementa `archivos.version` por movimiento |
| RF-63 | Bitácora completa | `GET /api/documents/{folio}/historial` (MovimientoResource con turno/acuse/archivos) |

### Notas de diseño

- El movimiento de turnado queda con `acuse_pendiente=true` (la entrega física se registra antes del acuse digital).
- Los archivos de acuse quedan en `documentos/{folio}/acuses/` con SHA-256 y `version`.
- El panel "Corregir destinatario" en el detalle del documento solo se muestra para turnos abiertos y con `turns.update`.

---

## F5 - Reportes + pulido (completada)

### Requerimientos funcionales atacados

| RF | Descripción | Implementación |
|---|---|---|
| RF-61 | Dashboard con indicadores | `GET /api/dashboard` (KPIs, pendientes por área, mis turnos pendientes) |
| RF-62 | Bandejas con filtros | `GET /api/turnos` y `/api/documents` con filtros compartidos |
| RF-64 | Exportación CSV | `GET /api/documents/export`, `GET /api/turnos/export` (BOM UTF-8, mismos filtros) |

### Frontend

- Portada `/` (DashboardView) con KPIs, mis turnos pendientes, pendientes por área, accesos rápidos.
- Exportación CSV desde DocumentsListView y TurnosView.
- Descarga de archivos/volantes por axios blob (token Bearer) en `resources/js/api/descargar.js`.
- Historial de movimientos, volantes y corrección de destinatario en DocumentDetailView.

### Tests

- `VolanteTest` (9), `AcuseTest` (6), `ReporteTest` (5) — 72 tests / 344 aserciones en total.