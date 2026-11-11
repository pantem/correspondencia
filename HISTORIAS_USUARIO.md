# Historias de usuario — Sistema de Correspondencia

Comisión para la Reconstrucción · Sistema de Correspondencia Institucional.
Fuente: Documento de Requerimientos de Software v1.0 (borrador) — RF-01…RF-64, RN-01…RN-35, criterios de aceptación §12.

Roles: **Administrador**, **Oficialía de Partes**, **Personal de Áreas**, **Responsable de atención**.

---

## Épica 1 — Registro y digitalización de correspondencia

**HU-01 · Registrar correspondencia recibida y enviada (RF-01)**
Como Oficialía de Partes, quiero registrar la correspondencia recibida y enviada, para centralizar el control documental.
Criterios de aceptación:
- Se puede crear un registro con sentido `recibida` o `enviada`.
- El documento queda en estado inicial `registrado`.
- Se genera automáticamente el folio principal (HU-04).

**HU-02 · Capturar datos generales (RF-02, RF-04, RF-05)**
Como Oficialía de Partes, quiero capturar folio, tipo de correspondencia, fechas, número de oficio original, remitente, dependencia, cargo, asunto, resumen, observaciones, anexos, canal de recepción, prioridad y plazo, para documentar el asunto.
Criterios de aceptación:
- Los campos obligatorios, opcionales y condicionales se definen en el diseño funcional (RF-04).
- Los datos especializados de vivienda u otros procesos solo se incorporan cuando correspondan al tipo de documento (RF-05).
- Los campos se validan antes de guardar.

**HU-03 · Digitalizar documento original (RF-03, RF-45)**
Como Oficialía de Partes o Personal de Áreas, quiero asociar documentos digitalizados al registro, para conservar la evidencia.
Criterios de aceptación:
- Se distingue documento original digitalizado, volante generado y acuse físico digitalizado (RF-45).
- El archivo queda vinculado al documento con hash SHA-256, tamaño y tipo MIME.
- Solo se permiten archivos PDF (límite institucional por definir, RF pendiente 11).

---

## Épica 2 — Administración de folios

**HU-04 · Generar folio principal (RF-06, RF-07, RF-08, RF-11, RN-04, RN-11)**
Como sistema, asigno a cada documento un folio principal único, consecutivo y asociado al año, para identificarlo durante todo su ciclo de vida.
Criterios de aceptación:
- Formato de referencia `0001-2027` (RF-07).
- El consecutivo se reinicia con el nuevo año sin reutilizar folios del periodo anterior (RF-08, RN-11).
- El folio se conserva en todo el ciclo de vida del documento (RF-09, RN-04).

**HU-05 · Independencia de identificadores (RF-10)**
Como sistema, mantengo independientes el número de oficio original, el folio principal y el identificador interno de base de datos.
Criterios de aceptación:
- Los tres valores son columnas distintas.
- El folio no depende del oficio original ni del id interno.

---

## Épica 3 — Turnado de correspondencia

**HU-06 · Turnar a una o varias áreas (RF-11, RF-14, RF-17, RF-20, RN-02, RN-17)**
Como Oficialía de Partes, quiero turnar correspondencia a una o varias áreas, para distribuir el trabajo.
Criterios de aceptación:
- Se puede turnar a múltiples áreas en un mismo acto (RF-11).
- Cada turno registra área destinataria, instrucciones, fecha, responsable y tipo de asignación (RF-14).
- Cada turno tiene estado y responsable vigente independientes (RF-17, RN-06).
- Se pueden asignar distintos tipos de asignación a las áreas de un mismo documento (RF-20, RN-17).

**HU-07 · Turnado interno (RF-12, RF-13, RN-03, RN-32)**
Como Personal de Áreas, quiero turnar internamente sin que el documento regrese a Oficialía, para canalizar directamente.
Criterios de aceptación:
- El turnado interno conserva el folio principal y registra destino, instrucciones y responsable (§6.2).
- No se impone jerarquía obligatoria entre áreas (RF-13, RN-32).
- El usuario debe contar con los permisos correspondientes.

**HU-08 · Reasignación con historial (RF-16, RN-07)**
Como gestión de turnos, quiero reasignar el responsable conservando el historial de responsables anteriores.
Criterios de aceptación:
- El movimiento registra `responsable_anterior_id`.
- La reasignación queda en la bitácora.

**HU-09 · Estados independientes por turno (RF-17, RF-27, RN-05, RN-09)**
Como sistema, cada área destinataria tiene seguimiento independiente y resolver un turno no cierra los demás.
Criterios de aceptación:
- Cada turno tiene su propio estado.
- Resolver un turno no modifica el estado de otros turnos del mismo documento (RF-27, RN-09).

**HU-10 · Consultar turnos del documento (RF-18)**
Como usuario autorizado, quiero consultar todos los turnos asociados a un documento, para ver su circulación.

**HU-11 · Identificador de movimiento (RF-15)**
Como sistema, cada turno tiene un identificador de movimiento independiente vinculado al folio principal.

---

## Épica 4 — Tipos de asignación

**HU-12 · Clasificar atención vs conocimiento (RF-19, RF-21, RF-22, RN-13, RN-14, RN-15, RN-16)**
Como usuario que turna, quiero clasificar cada turno como Atención o Conocimiento.
Criterios de aceptación:
- Las asignaciones de atención permiten registrar seguimiento y resolución (RN-14).
- Las asignaciones de conocimiento no exigen respuesta ni se consideran vencidas (RF-21, RN-15).
- Los indicadores distinguen asuntos pendientes de atención de los enviados para conocimiento (RF-22, RN-16).

---

## Épica 5 — Seguimiento y resolución

**HU-13 · Mostrar y definir estados (RF-23, RF-24)**
Como usuario, quiero ver el estado de cada turno (propuesta inicial: pendiente, en trámite, resuelto, cancelado).

**HU-14 · Resolver solo responsable vigente (RF-25, RN-08)**
Como Responsable de atención, solo la persona con responsabilidad vigente puede marcar un turno como atendido/resuelto.
Criterios de aceptación:
- Otros usuarios reciben 403 (prohibido) al intentar resolver.
- La resolución queda registrada en el historial y no se modifica libremente (RF-29).

**HU-15 · Registrar seguimiento y evidencias (RF-26)**
Como Responsable de atención, quiero registrar acciones de seguimiento y adjuntar evidencias.

**HU-16 · No cierre automático (RF-27, RN-09)**
Como sistema, al resolver un turno no se cierran automáticamente los demás turnos del mismo documento.

**HU-17 · Identificar pendientes/en trámite/resueltos/vencidos (RF-28, RN-12)**
Como usuario, quiero identificar turnos pendientes, en trámite, resueltos y, cuando aplique, vencidos por área.

**HU-18 · Conservar resolución (RF-29, RN-10)**
Como sistema, la resolución se conserva en el historial y no se modifica libremente; el historial de la correspondencia se conserva después de su atención.

---

## Épica 6 — Volantes

**HU-19 · Generar volante (RF-30, RF-31, §9)**
Como Oficialía de Partes, al turnar quiero que se genere un volante a partir de los datos registrados, conservando el folio principal.
Criterios de aceptación:
- Incluye identidad institucional, folio, código QR, procedencia, recepción, oficio original, asunto, observaciones, destinatario, responsable, tipo de asignación, instrucciones, fecha, plazo y datos del movimiento (RF-32).
- Se puede consultar en pantalla e imprimir/generar PDF (RF-33).
- Consistencia entre vista en pantalla y PDF (RF-34).

**HU-20 · Volante por turnado interno (RF-35, RF-37, §9.2)**
Como sistema, cada nuevo turnado interno genera el volante actualizado; el destino actual se identifica sin presentar un turnado interno como nueva recepción en Oficialía.
Criterios de aceptación:
- Se mantiene el folio principal.
- Las versiones anteriores y sus acuses permanecen disponibles (§9.2).

**HU-21 · Historial de volantes y versiones (RF-36)**
Como usuario autorizado, quiero consultar el historial de volantes y versiones emitidas por turno.

**HU-22 · Localizar volante por folio (RF-38)**
Como usuario, quiero localizar un volante mediante su folio principal.

---

## Épica 7 — Corrección de turnados y volantes

**HU-23 · Corregir destinatario de turno pendiente (RF-39, RF-41, RN-33, RN-34)**
Como gestión de turnos, quiero corregir el destinatario de un turno pendiente sin recapturar los datos generales.
Criterios de aceptación:
- Solo se permite en turnos pendientes (RF-43, RN-35).
- No se modifican automáticamente otros turnos independientes del mismo documento (RF-41, RN-34).
- Se conserva el folio principal y el historial (RN-33).

**HU-24 · Registrar corrección (RF-40)**
Como sistema, al corregir un turno registro quién realizó la corrección, cuándo y qué información cambió.

**HU-25 · Conservar evidencia previa (RF-42)**
Como sistema, las entregas físicas realizadas antes de una corrección se conservan.

**HU-26 · Validar reglas de edición (RF-44)**
Como equipo, antes de implementar se validan las reglas de edición, sustitución de destinatario, cancelación y nuevas versiones (pendiente de validación institucional).

---

## Épica 8 — Digitalización y acuses

**HU-27 · Distinguir tipos de archivo (RF-45, RF-25)**
Como sistema, se distingue documento original digitalizado, volante generado y acuse físico digitalizado.

**HU-28 · Sello de recepción (RF-46, RN-26)**
Como sistema, toda entrega física debe contar con el sello del destinatario.

**HU-29 · Registrar entrega antes del acuse (RF-47, RN-19)**
Como Oficialía/Área que entrega, puedo registrar la entrega física antes de digitalizar el acuse.
Criterios de aceptación:
- El movimiento queda con `acuse_pendiente=true` (RF-48, RN-20, RN-28).
- La falta de carga digital no bloquea movimientos posteriores (RF-49, RN-24).
- Cada acuse se vincula al movimiento específico (RF-50, RN-21).

**HU-30 · Digitalizar acuse (RF-51, RF-52, RN-22, RN-23, RN-27)**
Como área/usuario que realizó la entrega, digitalizo y adjunto mi copia sellada.
Criterios de aceptación:
- Se registra fecha, hora y usuario al cargar el acuse (RF-52, RN-23).
- El responsable de digitalizar es quien efectuó la entrega, conforme a permisos (RF-51, RN-22).
- El área conserva su copia sellada para digitalizarla (RN-27).

**HU-31 · Filtrar acuses pendientes (RF-53)**
Como usuario autorizado, quiero filtrar acuses pendientes por área, responsable y fecha.

**HU-32 · No sustituir evidencias (RF-54, RN-07)**
Como sistema, no se eliminan ni sustituyen silenciosamente evidencias anteriores al cargar un acuse; los acuses se versionan.

---

## Épica 9 — Administración institucional

**HU-33 · Módulo de administración (RF-55, RF-56, RN-29)**
Como Administrador, quiero administrar el catálogo institucional (áreas, cargos, titulares).
Criterios de aceptación:
- Solo el administrador puede registrar, modificar, activar o desactivar (RF-56, RN-29).

**HU-34 · Bitácora de cambios de catálogo (RF-57, RF-58, RN-30, RN-31)**
Como sistema, registro información anterior/nueva, fecha, hora y usuario de cada cambio al catálogo.
Criterios de aceptación:
- Los cambios al catálogo quedan en la bitácora (RN-30).
- Los cambios organizacionales no alteran turnados históricos, volantes ni responsabilidades anteriores (RF-58, RN-31).

**HU-35 · Gestionar usuarios y permisos (RF-59, §5.4)**
Como Administrador, quiero gestionar usuarios y permisos conforme a los perfiles autorizados.
Criterios de aceptación:
- Los permisos específicos para corregir volantes y administrar documentos históricos se definen en la matriz de permisos (pendiente de validación).

---

## Épica 10 — Consultas, reportes y bitácora

**HU-36 · Búsqueda multi-criterio (RF-60)**
Como usuario autorizado, quiero buscar por folio, asunto, remitente, oficio, área, responsable, fecha y estado, según permisos.

**HU-37 · Historial de movimientos (RF-61)**
Como usuario, quiero consultar el historial de movimientos de un documento.

**HU-38 · Identificar pendientes (RF-62)**
Como usuario, quiero identificar turnos pendientes de atención y acuses pendientes de digitalizar.

**HU-39 · Bitácora de operaciones (RF-63)**
Como sistema, mantengo bitácora de registros, turnados, reasignaciones, correcciones, cargas y cambios de estado.

**HU-40 · Permisos en reportes y exportaciones (RF-64, RN-F)**
Como sistema, respeto los permisos de acceso en reportes y exportaciones.

---

## Épica 11 — Requerimientos no funcionales

**HU-41 · Seguridad (§10.1)**
Como sistema, controlo el acceso mediante autenticación y permisos por perfil; solo se realizan operaciones autorizadas; las acciones administrativas y modificaciones relevantes quedan registradas.

**HU-42 · Integridad y trazabilidad (§10.2, RN-04, RN-07)**
Como sistema, preservo la relación entre folio principal, turnos, responsables, volantes, archivos y acuses; no se modifican silenciosamente las evidencias históricas ni se eliminan registros de auditoría.

**HU-43 · Disponibilidad y almacenamiento (§10.3)**
Como equipo, la aplicación contará con mecanismos de respaldo y recuperación; infraestructura, capacidad y periodicidad se definen en el diseño técnico (pendiente).

**HU-44 · Usabilidad (§10.4)**
Como usuario, la interfaz facilita captura, búsqueda y consulta, evitando duplicidad e identificando claramente el estado de cada turno.

**HU-45 · Mantenibilidad (§10.5)**
Como equipo, los catálogos y asignaciones se administran desde el sistema, no codificados de forma fija.

**HU-46 · Conservación documental (§10.6)**
Como institución, se definen políticas de conservación, consulta, respaldo y eliminación autorizada.

---

## Épica 12 — Criterios generales de aceptación (§12)

- Se registra correspondencia y se genera un folio principal consecutivo.
- Se puede adjuntar el documento original digitalizado.
- Se puede turnar un documento a varias áreas.
- Se puede efectuar un turnado interno conservando el folio principal.
- Cada área tiene un estado de seguimiento independiente.
- Se distingue la asignación de atención de la asignación de conocimiento.
- Se generan volantes consultables e imprimibles.
- Cada entrega física puede vincularse a su acuse.
- Se identifican los acuses pendientes de digitalizar sin bloquear movimientos posteriores.
- Se conserva el historial de responsables, turnos y correcciones.
- Solo el administrador puede modificar el catálogo institucional.
- Los turnos resueltos y los turnos pendientes de un mismo documento se gestionan independientemente.
- Se puede consultar la trazabilidad de un documento desde su registro hasta sus movimientos posteriores.

---

## Pendientes de definición y validación (§13)

1. Perfiles y permisos — definir permisos de Oficialía, titulares, personal operativo y administrador.
2. Catálogo institucional — validar nombres, estructura y vigencia.
3. Turnado — definir quién puede corregir o cancelar un turno confirmado.
4. Volantes — precisar reglas de edición después de la impresión y entrega física.
5. Turnos resueltos — definir excepciones autorizadas para corregirlos.
6. Plazos — establecer términos de atención y reglas de vencimiento.
7. Correspondencia enviada — definir registro, folio, destinatarios, envío y evidencia.
8. Notificaciones — determinar si se requieren alertas.
9. Reportes — definir indicadores, formatos de exportación y consultas.
10. Documentación física — validar formato definitivo del volante y copias.
11. Archivos — definir formatos permitidos, límites de tamaño y políticas de almacenamiento.
12. Infraestructura — determinar servidor, BD, respaldos, disponibilidad y recuperación.
13. Seguridad documental — validar políticas de conservación, acceso y eliminación autorizada.
