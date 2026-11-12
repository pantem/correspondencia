<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import client from '../api/client';
import { abrirEnNuevaVentana, descargar } from '../api/descargar';
import { useAuthStore } from '../stores/auth';

const props = defineProps({ folio: { type: String, required: true } });

const auth = useAuthStore();
const router = useRouter();

const doc = ref(null);
const turnos = ref([]);
const areas = ref([]);
const usuarios = ref([]);
const error = ref('');
const editando = ref(false);
const subiendo = ref(false);
const archivoInput = ref(null);
const mostrarFormTurno = ref(false);
const turnando = ref(false);
const errorTurno = ref('');

const volantes = ref([]);
const volanteGenerando = ref(false);
const historial = ref([]);
const cargandoHistorial = ref(false);
const acuseInputs = ref({});
const subiendoAcuse = ref({});
const correccion = reactive({
    abierta: false,
    area_destino_id: '',
    responsable_id: '',
    tipo_asignacion: '',
    instrucciones: '',
    plazo: '',
    guardando: false,
});

const formTurno = ref([]);

const accion = ref({ turnoId: null, tipo: '', notas: '', responsable_id: '' });

const form = reactive({
    asunto: '',
    estado: 'registrado',
    prioridad: 'normal',
    resumen: '',
    observaciones: '',
    plazo: '',
});

const estados = ['registrado', 'en_tramite', 'resuelto', 'cancelado'];

async function cargar() {
    error.value = '';
    try {
        const { data } = await client.get(`/documents/${props.folio}`);
        doc.value = data.data;
        Object.assign(form, {
            asunto: data.data.asunto,
            estado: data.data.estado,
            prioridad: data.data.prioridad,
            resumen: data.data.resumen || '',
            observaciones: data.data.observaciones || '',
            plazo: data.data.plazo || '',
        });
    } catch (err) {
        error.value = err.response?.status === 404
            ? 'Documento no encontrado.'
            : 'No se pudo cargar el documento.';
    }
}

async function cargarTurnos() {
    try {
        const { data } = await client.get(`/documents/${props.folio}/turnos`);
        turnos.value = data.data;
    } catch {
        // El detalle del documento es independiente de los turnos.
    }
}

async function cargarCatalogos() {
    try {
        const [areaRes, userRes] = await Promise.all([
            client.get('/admin/areas'),
            client.get('/catalogos/usuarios'),
        ]);
        areas.value = areaRes.data.data;
        usuarios.value = userRes.data.data;
    } catch {
        // Los selects se poblarán en el próximo intento.
    }
}

async function cargarVolantes() {
    try {
        const { data } = await client.get(`/documents/${props.folio}/volantes`);
        volantes.value = data.data;
    } catch {
        // Los volantes se cargan de forma independiente del documento.
    }
}

async function cargarHistorial() {
    cargandoHistorial.value = true;
    try {
        const { data } = await client.get(`/documents/${props.folio}/historial`);
        historial.value = data.data;
    } catch {
        // El historial no bloquea el detalle.
    } finally {
        cargandoHistorial.value = false;
    }
}

onMounted(() => {
    cargar();
    cargarTurnos();
    cargarVolantes();
    cargarHistorial();
    if (auth.has('documents.turn')) {
        cargarCatalogos();
    }
});

function abrirFormTurno() {
    formTurno.value = [{ area_id: '', tipo_asignacion: 'atencion', instrucciones: '', plazo: '', responsable_id: '' }];
    errorTurno.value = '';
    mostrarFormTurno.value = true;
}

function agregarFilaTurno() {
    formTurno.value.push({ area_id: '', tipo_asignacion: 'atencion', instrucciones: '', plazo: '', responsable_id: '' });
}

async function turnar() {
    errorTurno.value = '';
    const turnosPayload = formTurno.value
        .filter((fila) => fila.area_id)
        .map((fila) => {
            const payload = {
                area_id: Number(fila.area_id),
                tipo_asignacion: fila.tipo_asignacion,
            };
            if (fila.instrucciones) payload.instrucciones = fila.instrucciones;
            if (fila.plazo) payload.plazo = fila.plazo;
            if (fila.responsable_id) payload.responsable_id = Number(fila.responsable_id);
            return payload;
        });

    if (!turnosPayload.length) {
        errorTurno.value = 'Seleccione al menos un área destinataria.';
        return;
    }

    turnando.value = true;
    try {
        await client.post(`/documents/${props.folio}/turnos`, { turnos: turnosPayload });
        mostrarFormTurno.value = false;
        await Promise.all([cargarTurnos(), cargarVolantes(), cargarHistorial()]);
    } catch (err) {
        if (err.response?.status === 422) {
            errorTurno.value = Object.values(err.response.data.errors || {}).flat().join(' ');
        } else {
            errorTurno.value = err.response?.data?.message || 'No se pudo turnar el documento.';
        }
    } finally {
        turnando.value = false;
    }
}

function iniciarAccion(turno, tipo) {
    accion.value = { turnoId: turno.id, tipo, notas: '', responsable_id: '' };
    errorTurno.value = '';
}

async function ejecutarAccion() {
    const { turnoId, tipo } = accion.value;
    errorTurno.value = '';
    try {
        if (tipo === 'seguimiento') {
            await client.post(`/turnos/${turnoId}/seguimiento`, { notas: accion.value.notas });
        } else if (tipo === 'cancelar') {
            await client.post(`/turnos/${turnoId}/cancelar`, { motivo: accion.value.notas });
        } else if (tipo === 'reasignar') {
            await client.post(`/turnos/${turnoId}/reasignar`, {
                responsable_id: Number(accion.value.responsable_id),
                notas: accion.value.notas || null,
            });
        } else if (tipo === 'resolver') {
            await client.post(`/turnos/${turnoId}/resolver`);
        }
        accion.value = { turnoId: null, tipo: '', notas: '', responsable_id: '' };
        await Promise.all([cargarTurnos(), cargarHistorial()]);
    } catch (err) {
        if (err.response?.status === 422) {
            errorTurno.value = Object.values(err.response.data.errors || {}).flat().join(' ');
        } else {
            errorTurno.value = err.response?.data?.message || 'No se pudo ejecutar la acción.';
        }
    }
}

function esResponsable(turno) {
    return turno.responsable?.id === auth.user?.id;
}

function turnoAbierto(turno) {
    return turno.estado === 'pendiente' || turno.estado === 'en_tramite';
}

function claseEstado(estado) {
    return {
        registrado: 'bg-primary-100 text-primary-700',
        pendiente: 'bg-primary-100 text-primary-700',
        en_tramite: 'bg-amber-100 text-amber-800',
        resuelto: 'bg-green-100 text-green-700',
        cancelado: 'bg-gray-100 text-gray-500',
    }[estado] || 'bg-gray-100 text-gray-600';
}

function puedeReasignar(turno) {
    return turnoAbierto(turno) && (auth.has('turns.update') || esResponsable(turno));
}

function iniciarEdicion() {
    editando.value = true;
}

async function guardar() {
    error.value = '';
    try {
        const payload = { ...form };
        if (!payload.plazo) delete payload.plazo;
        const { data } = await client.put(`/documents/${props.folio}`, payload);
        doc.value = data.data;
        editando.value = false;
    } catch (err) {
        if (err.response?.status === 422) {
            error.value = Object.values(err.response.data.errors || {}).flat().join(' ');
        } else {
            error.value = err.response?.data?.message || 'No se pudo actualizar el documento.';
        }
    }
}

async function subirArchivo(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    subiendo.value = true;
    error.value = '';
    try {
        const data = new FormData();
        data.append('archivo', file);
        await client.post(`/documents/${props.folio}/archivos`, data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        await cargar();
        if (archivoInput.value) archivoInput.value.value = '';
    } catch (err) {
        if (err.response?.status === 422) {
            error.value = Object.values(err.response.data.errors || {}).flat().join(' ');
        } else {
            error.value = 'No se pudo cargar el archivo.';
        }
    } finally {
        subiendo.value = false;
    }
}

async function generarVolante(turnoId) {
    volanteGenerando.value = true;
    errorTurno.value = '';
    try {
        await client.post(`/turnos/${turnoId}/volantes`);
        await Promise.all([cargarVolantes(), cargarHistorial()]);
    } catch (err) {
        errorTurno.value = err.response?.data?.message || 'No se pudo generar el volante.';
    } finally {
        volanteGenerando.value = false;
    }
}

async function abrirVolantePdf(volanteId) {
    try {
        await abrirEnNuevaVentana(`/volantes/${volanteId}/pdf`);
    } catch (err) {
        error.value = err.response?.data?.message || 'No se pudo abrir el volante.';
    }
}

async function descargarVolante(volanteId) {
    try {
        await descargar(`/volantes/${volanteId}/pdf`, 'volante.pdf');
    } catch (err) {
        error.value = err.response?.data?.message || 'No se pudo descargar el volante.';
    }
}

async function subirAcuse(movimientoId, event) {
    const file = event.target.files?.[0];
    if (!file) return;

    subiendoAcuse.value[movimientoId] = true;
    error.value = '';
    try {
        const data = new FormData();
        data.append('archivo', file);
        await client.post(`/movimientos/${movimientoId}/acuse`, data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        await Promise.all([cargarTurnos(), cargarHistorial()]);
    } catch (err) {
        if (err.response?.status === 422) {
            error.value = Object.values(err.response.data.errors || {}).flat().join(' ');
        } else {
            error.value = err.response?.data?.message || 'No se pudo cargar el acuse.';
        }
    } finally {
        subiendoAcuse.value[movimientoId] = false;
        if (acuseInputs.value[movimientoId]) acuseInputs.value[movimientoId].value = '';
    }
}

function abrirCorreccion(turno) {
    correccion.abierta = true;
    correccion.area_destino_id = turno.area_destino?.id || '';
    correccion.responsable_id = turno.responsable?.id || '';
    correccion.tipo_asignacion = turno.tipo_asignacion;
    correccion.instrucciones = turno.instrucciones || '';
    correccion.plazo = turno.plazo || '';
    accion.value = { turnoId: turno.id, tipo: 'corregir', notas: '', responsable_id: '' };
    errorTurno.value = '';
}

async function guardarCorreccion() {
    correccion.guardando = true;
    errorTurno.value = '';
    try {
        const payload = {
            area_destino_id: Number(correccion.area_destino_id),
            tipo_asignacion: correccion.tipo_asignacion,
            instrucciones: correccion.instrucciones || null,
            plazo: correccion.plazo || null,
        };
        if (correccion.responsable_id) {
            payload.responsable_id = Number(correccion.responsable_id);
        } else {
            payload.responsable_id = null;
        }
        await client.put(`/turnos/${accion.value.turnoId}`, payload);
        correccion.abierta = false;
        accion.value = { turnoId: null, tipo: '', notas: '', responsable_id: '' };
        await Promise.all([cargarTurnos(), cargarVolantes(), cargarHistorial()]);
    } catch (err) {
        if (err.response?.status === 422) {
            errorTurno.value = Object.values(err.response.data.errors || {}).flat().join(' ');
        } else {
            errorTurno.value = err.response?.data?.message || 'No se pudo corregir el turno.';
        }
    } finally {
        correccion.guardando = false;
    }
}

function cancelarCorreccion() {
    correccion.abierta = false;
    accion.value = { turnoId: null, tipo: '', notas: '', responsable_id: '' };
}

function tipoMovimientoLegible(tipo) {
    return {
        turnado: 'Turnado',
        seguimiento: 'Seguimiento',
        resolucion: 'Resolución',
        cancelacion: 'Cancelación',
        reasignacion: 'Reasignación',
        correccion: 'Corrección de destinatario',
    }[tipo] || tipo;
}

function claseMovimiento(tipo) {
    return {
        turnado: 'bg-primary-100 text-primary-700',
        seguimiento: 'bg-blue-100 text-blue-700',
        resolucion: 'bg-green-100 text-green-700',
        cancelacion: 'bg-red-100 text-red-700',
        reasignacion: 'bg-orange-100 text-orange-800',
        correccion: 'bg-purple-100 text-purple-700',
    }[tipo] || 'bg-gray-100 text-gray-600';
}
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <RouterLink to="/documentos" class="text-sm text-primary-700 hover:underline">← Volver al listado</RouterLink>
                <h1 class="mt-1 flex items-center gap-3 text-xl font-semibold">
                    <span class="font-mono">{{ folio }}</span>
                    <span
                        v-if="doc"
                        class="rounded-full px-2 py-0.5 text-xs font-normal"
                        :class="claseEstado(doc.estado)"
                    >
                        {{ doc.estado.replace('_', ' ') }}
                    </span>
                </h1>
            </div>
            <button
                v-if="doc && auth.has('documents.update') && !editando"
                type="button"
                class="rounded border px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                @click="iniciarEdicion"
            >
                Editar
            </button>
        </div>

        <div v-if="error" class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ error }}
        </div>

        <p v-if="!doc && !error" class="mt-6 text-sm text-gray-500">Cargando…</p>

        <div v-if="doc" class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
            <section class="rounded-lg border bg-white p-5 lg:col-span-2">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">Datos del documento</h2>
                    <span class="text-xs text-gray-500">Folio {{ doc.folio }} · {{ doc.anio }}</span>
                </div>

                <form v-if="editando" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2" @submit.prevent="guardar">
                    <label class="text-sm font-medium sm:col-span-2">
                        Asunto
                        <input v-model="form.asunto" type="text" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </label>
                    <label class="text-sm font-medium">
                        Estado
                        <select v-model="form.estado" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option v-for="e in estados" :key="e" :value="e">{{ e.replace('_', ' ') }}</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium">
                        Prioridad
                        <select v-model="form.prioridad" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option value="normal">normal</option>
                            <option value="urgente">urgente</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium">
                        Plazo
                        <input v-model="form.plazo" type="date" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </label>
                    <label class="text-sm font-medium sm:col-span-2">
                        Resumen
                        <textarea v-model="form.resumen" rows="3" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"></textarea>
                    </label>
                    <label class="text-sm font-medium sm:col-span-2">
                        Observaciones
                        <textarea v-model="form.observaciones" rows="2" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"></textarea>
                    </label>
                    <div class="flex gap-3 sm:col-span-2">
                        <button type="submit" class="rounded bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                            Guardar
                        </button>
                        <button type="button" class="rounded border px-4 py-2 text-sm text-gray-600 hover:bg-gray-100" @click="editando = false">
                            Cancelar
                        </button>
                    </div>
                </form>

                <dl v-else class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-gray-500">Sentido</dt>
                        <dd>{{ doc.sentido }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Tipo</dt>
                        <dd>{{ doc.tipo_correspondencia }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Fecha del documento</dt>
                        <dd>{{ doc.fecha_documento }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Fecha de recepción</dt>
                        <dd>{{ doc.fecha_recepcion || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Oficio original</dt>
                        <dd>{{ doc.oficio_original || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Canal</dt>
                        <dd>{{ doc.canal_recepcion?.replace('_', ' ') || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Remitente</dt>
                        <dd>{{ doc.remitente || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Dependencia</dt>
                        <dd>{{ doc.dependencia || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Cargo del remitente</dt>
                        <dd>{{ doc.cargo_remitente || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Área relacionada</dt>
                        <dd>{{ doc.area?.nombre || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Prioridad</dt>
                        <dd>{{ doc.prioridad }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Plazo</dt>
                        <dd>{{ doc.plazo || '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Asunto</dt>
                        <dd>{{ doc.asunto }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Resumen</dt>
                        <dd class="whitespace-pre-wrap">{{ doc.resumen || '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Observaciones</dt>
                        <dd class="whitespace-pre-wrap">{{ doc.observaciones || '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-gray-500">Anexos</dt>
                        <dd class="whitespace-pre-wrap">{{ doc.anexos || '—' }}</dd>
                    </div>
                    <div v-if="doc.datos_extra" class="sm:col-span-2">
                        <dt class="text-gray-500">Datos especializados</dt>
                        <dd class="font-mono text-xs">{{ JSON.stringify(doc.datos_extra, null, 2) }}</dd>
                    </div>
                    <div class="sm:col-span-2 text-xs text-gray-400">
                        Registrado por {{ doc.user?.name || '—' }} · ID interno {{ doc.id }}
                    </div>
                </dl>
            </section>

            <section class="h-fit rounded-lg border bg-white p-5">
                <h2 class="font-semibold">Archivos digitalizados</h2>

                <ul v-if="doc.archivos?.length" class="mt-3 space-y-2 text-sm">
                    <li
                        v-for="archivo in doc.archivos"
                        :key="archivo.id"
                        class="flex items-center justify-between gap-2 rounded border px-3 py-2"
                    >
                        <div class="min-w-0">
                            <a :href="archivo.url" target="_blank" class="block truncate text-primary-700 hover:underline">
                                {{ archivo.original_name }}
                            </a>
                            <span class="text-xs text-gray-500">
                                {{ archivo.tipo }} · {{ (archivo.size / 1024).toFixed(1) }} KB
                            </span>
                        </div>
                        <span class="shrink-0 font-mono text-[10px] text-gray-400" :title="archivo.sha256">
                            {{ archivo.sha256.slice(0, 8) }}
                        </span>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-gray-500">
                    Sin archivos. Adjunte el PDF original escaneado.
                </p>

                <div v-if="auth.has('documents.upload')" class="mt-4">
                    <label class="block text-sm font-medium">Cargar PDF</label>
                    <label
                        class="mt-2 inline-flex cursor-pointer items-center gap-2 rounded bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                        :class="{ 'opacity-50 pointer-events-none': subiendo }"
                    >
                        <span>{{ subiendo ? 'Subiendo…' : 'Seleccionar PDF' }}</span>
                        <input
                            ref="archivoInput"
                            type="file"
                            accept="application/pdf"
                            class="hidden"
                            :disabled="subiendo"
                            @change="subirArchivo"
                        />
                    </label>
                    <p class="mt-2 text-xs text-gray-400">Máximo 20 MB. Solo archivos PDF.</p>
                </div>
            </section>
        </div>

        <section v-if="doc" class="mt-4 rounded-lg border bg-white p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold">
                    Volantes de turnado
                    <span class="text-sm font-normal text-gray-500">({{ volantes.length }})</span>
                </h2>
                <p class="text-xs text-gray-400">El volante se genera automáticamente al turnar.</p>
            </div>

            <ul v-if="volantes.length" class="mt-3 divide-y text-sm">
                <li v-for="volante in volantes" :key="volante.id" class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <p class="font-medium">
                            Turno #{{ volante.turno_id }}
                            <span class="ml-2 rounded-full bg-primary-100 px-2 py-0.5 text-xs text-primary-700">
                                v{{ volante.version }}
                            </span>
                            <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                                {{ volante.origen === 'recepcion_inicial' ? 'recepción inicial' : 'turnado interno' }}
                            </span>
                        </p>
                        <p class="text-xs text-gray-500">
                            Emitido por {{ volante.emitido_por?.name || '—' }} ·
                            {{ volante.emitido_at ? new Date(volante.emitido_at).toLocaleString() : '' }}
                            · {{ volante.turno?.area_destino?.nombre || '' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <button
                            type="button"
                            class="rounded border px-3 py-1.5 text-primary-700 hover:bg-primary-50"
                            @click="abrirVolantePdf(volante.id)"
                        >
                            Ver PDF
                        </button>
                        <button
                            type="button"
                            class="rounded border px-3 py-1.5 text-gray-700 hover:bg-gray-100"
                            @click="descargarVolante(volante.id)"
                        >
                            Descargar
                        </button>
                        <button
                            v-if="auth.has('turns.update')"
                            type="button"
                            class="rounded border px-3 py-1.5 text-gray-700 hover:bg-gray-100 disabled:opacity-50"
                            :disabled="volanteGenerando"
                            @click="generarVolante(volante.turno_id)"
                        >
                            Nueva versión
                        </button>
                    </div>
                </li>
            </ul>
            <p v-else class="mt-3 text-sm text-gray-500">
                Sin volantes. Se generan al realizar el turnado.
            </p>
        </section>

        <section v-if="doc" class="mt-4 rounded-lg border bg-white p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold">Turnos <span class="text-sm font-normal text-gray-500">({{ turnos.length }})</span></h2>
                <button
                    v-if="auth.has('documents.turn') && !mostrarFormTurno"
                    type="button"
                    class="rounded bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    @click="abrirFormTurno"
                >
                    Turnar documento
                </button>
            </div>

            <div v-if="mostrarFormTurno" class="mt-4 rounded border border-primary-100 bg-primary-50/50 p-4">
                <h3 class="text-sm font-semibold">Nuevo turnado (una o varias áreas)</h3>

                <div
                    v-for="(fila, index) in formTurno"
                    :key="index"
                    class="mt-3 grid grid-cols-1 gap-3 rounded border bg-white p-3 sm:grid-cols-2 lg:grid-cols-5"
                >
                    <label class="text-sm font-medium">
                        Área *
                        <select v-model="fila.area_id" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option value="" disabled>Seleccione…</option>
                            <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.nombre }}</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium">
                        Tipo *
                        <select v-model="fila.tipo_asignacion" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option value="atencion">atención</option>
                            <option value="conocimiento">conocimiento</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium">
                        Responsable
                        <select v-model="fila.responsable_id" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option value="">Sin asignar</option>
                            <option v-for="u in usuarios" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium">
                        Plazo
                        <input v-model="fila.plazo" type="date" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </label>
                    <label class="text-sm font-medium">
                        Instrucciones
                        <input v-model="fila.instrucciones" type="text" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </label>
                </div>

                <div class="mt-3 flex flex-wrap gap-3">
                    <button type="button" class="rounded border px-3 py-2 text-sm text-gray-700 hover:bg-gray-100" @click="agregarFilaTurno">
                        + Agregar área
                    </button>
                    <button
                        type="button"
                        class="rounded bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        :disabled="turnando"
                        @click="turnar"
                    >
                        {{ turnando ? 'Turnando…' : 'Confirmar turnado' }}
                    </button>
                    <button type="button" class="rounded border px-4 py-2 text-sm text-gray-600 hover:bg-gray-100" @click="mostrarFormTurno = false">
                        Cancelar
                    </button>
                </div>
            </div>

            <div v-if="errorTurno" class="mt-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ errorTurno }}
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b bg-primary-700 text-xs font-medium uppercase text-white">
                        <tr>
                            <th class="px-3 py-2">Área destino</th>
                            <th class="px-3 py-2">Tipo</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2">Responsable</th>
                            <th class="px-3 py-2">Plazo</th>
                            <th class="px-3 py-2">Acuse</th>
                            <th class="px-3 py-2">Instrucciones</th>
                            <th class="px-3 py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!turnos.length">
                            <td colspan="8" class="px-3 py-5 text-center text-gray-500">Sin turnos. El documento aún no se ha turnado.</td>
                        </tr>
                        <tr v-for="turno in turnos" :key="turno.id" class="border-b last:border-0 align-top">
                            <td class="px-3 py-3">
                                {{ turno.area_destino?.nombre || '—' }}
                                <span v-if="turno.es_turnado_inicial" class="ml-1 text-xs text-gray-400">(inicial)</span>
                                <span v-else class="ml-1 text-xs text-gray-400">(interno)</span>
                            </td>
                            <td class="px-3 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="turno.tipo_asignacion === 'atencion' ? 'bg-primary-100 text-primary-700' : 'bg-green-100 text-green-700'"
                                >
                                    {{ turno.tipo_asignacion }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="{
                                        'bg-primary-100 text-primary-700': turno.estado === 'pendiente',
                                        'bg-amber-100 text-amber-800': turno.estado === 'en_tramite',
                                        'bg-green-100 text-green-700': turno.estado === 'resuelto',
                                        'bg-gray-100 text-gray-500': turno.estado === 'cancelado',
                                    }"
                                >
                                    {{ turno.estado.replace('_', ' ') }}
                                </span>
                            </td>
                            <td class="px-3 py-3">{{ turno.responsable?.name || 'Sin asignar' }}</td>
                            <td class="px-3 py-3 text-xs">
                                <span v-if="turno.vencido" class="font-semibold text-red-600">{{ turno.plazo }} · vencido</span>
                                <span v-else>{{ turno.plazo || '—' }}</span>
                            </td>
                            <td class="px-3 py-3 text-xs">
                                <span
                                    v-if="turno.acuse_pendiente"
                                    class="rounded-full bg-orange-100 px-2 py-0.5 font-medium text-orange-800"
                                >
                                    pendiente
                                </span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="max-w-48 px-3 py-3 text-xs text-gray-600">{{ turno.instrucciones || '—' }}</td>
                            <td class="px-3 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2 text-xs">
                                    <button
                                        v-if="auth.has('turns.respond') && turnoAbierto(turno)"
                                        type="button"
                                        class="text-primary-700 hover:underline"
                                        @click="iniciarAccion(turno, 'seguimiento')"
                                    >
                                        Seguimiento
                                    </button>
                                    <button
                                        v-if="auth.has('turns.respond') && turnoAbierto(turno) && esResponsable(turno)"
                                        type="button"
                                        class="text-green-700 hover:underline"
                                        @click="iniciarAccion(turno, 'resolver')"
                                    >
                                        Resolver
                                    </button>
                                    <button
                                        v-if="auth.has('turns.update') && turnoAbierto(turno)"
                                        type="button"
                                        class="text-orange-700 hover:underline"
                                        @click="abrirCorreccion(turno)"
                                    >
                                        Corregir destinatario
                                    </button>
                                    <button
                                        v-if="puedeReasignar(turno)"
                                        type="button"
                                        class="text-orange-700 hover:underline"
                                        @click="iniciarAccion(turno, 'reasignar')"
                                    >
                                        Reasignar
                                    </button>
                                    <button
                                        v-if="auth.has('turns.update') && turnoAbierto(turno)"
                                        type="button"
                                        class="text-red-700 hover:underline"
                                        @click="iniciarAccion(turno, 'cancelar')"
                                    >
                                        Cancelar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="correccion.abierta" class="mt-4 rounded border border-purple-200 bg-purple-50/50 p-4">
                <h3 class="text-sm font-semibold text-purple-800">
                    Corregir destinatario · turno #{{ accion.turnoId }}
                </h3>
                <p class="mt-1 text-xs text-gray-600">
                    Solo se permite en turnos pendientes. Se registrará un movimiento de corrección, auditoría y una nueva versión del volante.
                </p>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <label class="text-sm font-medium">
                        Área *
                        <select v-model="correccion.area_destino_id" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option value="" disabled>Seleccione…</option>
                            <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.nombre }}</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium">
                        Tipo *
                        <select v-model="correccion.tipo_asignacion" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option value="atencion">atención</option>
                            <option value="conocimiento">conocimiento</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium">
                        Responsable
                        <select v-model="correccion.responsable_id" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                            <option value="">Sin asignar</option>
                            <option v-for="u in usuarios" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </label>
                    <label class="text-sm font-medium">
                        Plazo
                        <input v-model="correccion.plazo" type="date" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </label>
                    <label class="text-sm font-medium">
                        Instrucciones
                        <input v-model="correccion.instrucciones" type="text" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    </label>
                </div>
                <div class="mt-3 flex flex-wrap gap-3">
                    <button
                        type="button"
                        class="rounded bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                        :disabled="correccion.guardando"
                        @click="guardarCorreccion"
                    >
                        {{ correccion.guardando ? 'Guardando…' : 'Guardar corrección' }}
                    </button>
                    <button type="button" class="rounded border px-4 py-2 text-sm text-gray-600 hover:bg-gray-100" @click="cancelarCorreccion">
                        Cancelar
                    </button>
                </div>
            </div>

            <div v-if="accion.turnoId && accion.tipo !== 'corregir'" class="mt-4 rounded border border-gray-200 bg-gray-50 p-4">
                <h3 class="text-sm font-semibold capitalize">
                    {{ accion.tipo }} · turno #{{ accion.turnoId }}
                </h3>

                <div class="mt-3 flex flex-wrap items-end gap-3">
                    <label v-if="accion.tipo === 'reasignar'" class="text-sm font-medium">
                        Nuevo responsable *
                        <select v-model="accion.responsable_id" class="mt-1 block w-56 rounded border border-gray-300 px-3 py-2 text-sm">
                            <option value="" disabled>Seleccione…</option>
                            <option v-for="u in usuarios" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </label>
                    <label v-if="accion.tipo !== 'resolver'" class="min-w-64 flex-1 text-sm font-medium">
                        {{ accion.tipo === 'cancelar' ? 'Motivo *' : 'Notas' }}
                        <textarea
                            v-model="accion.notas"
                            rows="2"
                            :required="accion.tipo === 'cancelar'"
                            class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        ></textarea>
                    </label>
                    <button
                        type="button"
                        class="rounded bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                        @click="ejecutarAccion"
                    >
                        Confirmar
                    </button>
                    <button
                        type="button"
                        class="rounded border px-4 py-2 text-sm text-gray-600 hover:bg-gray-100"
                        @click="accion = { turnoId: null, tipo: '', notas: '', responsable_id: '' }"
                    >
                        Descartar
                    </button>
                </div>
            </div>
        </section>

        <section v-if="doc" class="mt-4 rounded-lg border bg-white p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold">
                    Historial de movimientos
                    <span class="text-sm font-normal text-gray-500">({{ historial.length }})</span>
                </h2>
                <button type="button" class="rounded border px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100" @click="cargarHistorial">
                    Actualizar
                </button>
            </div>

            <p v-if="cargandoHistorial" class="mt-3 text-sm text-gray-500">Cargando…</p>

            <ol v-else-if="historial.length" class="mt-3 space-y-3">
                <li
                    v-for="mov in historial"
                    :key="mov.id"
                    class="rounded border p-3 text-sm"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="claseMovimiento(mov.tipo)">
                                {{ tipoMovimientoLegible(mov.tipo) }}
                            </span>
                            <span class="text-xs text-gray-500">
                                {{ mov.fecha ? new Date(mov.fecha).toLocaleString() : '' }}
                            </span>
                            <span v-if="mov.usuario_origen?.name" class="text-xs text-gray-500">
                                por {{ mov.usuario_origen.name }}
                            </span>
                        </div>
                        <span
                            v-if="mov.acuse_pendiente"
                            class="rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-800"
                        >
                            acuse pendiente de digitalizar
                        </span>
                    </div>

                    <p v-if="mov.notas" class="mt-2 whitespace-pre-wrap text-gray-700">
                        {{ mov.notas }}
                    </p>

                    <div v-if="mov.tipo === 'turnado'" class="mt-3 flex flex-wrap items-center gap-2">
                        <label
                            v-if="auth.has('documents.upload')"
                            class="inline-flex cursor-pointer items-center gap-2 rounded border px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100"
                        >
                            <span>{{ subiendoAcuse[mov.id] ? 'Subiendo…' : 'Digitalizar acuse' }}</span>
                            <input
                                :ref="(el) => (acuseInputs[mov.id] = el)"
                                type="file"
                                accept="application/pdf"
                                class="hidden"
                                :disabled="subiendoAcuse[mov.id]"
                                @change="subirAcuse(mov.id, $event)"
                            />
                        </label>
                        <ul v-if="mov.archivos?.length" class="flex flex-wrap gap-2">
                            <li v-for="archivo in mov.archivos" :key="archivo.id" class="text-xs">
                                <a
                                    :href="archivo.url"
                                    target="_blank"
                                    class="text-primary-700 hover:underline"
                                >
                                    {{ archivo.original_name }} (v{{ archivo.version }})
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
            </ol>

            <p v-else class="mt-3 text-sm text-gray-500">
                Sin movimientos registrados.
            </p>
        </section>
    </div>
</template>
