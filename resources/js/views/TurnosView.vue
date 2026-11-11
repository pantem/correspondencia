<script setup>
import { onMounted, reactive, ref } from 'vue';
import { RouterLink } from 'vue-router';
import client from '../api/client';
import { descargar } from '../api/descargar';

const items = ref([]);
const meta = ref(null);
const areas = ref([]);
const error = ref('');
const cargando = ref(false);
const exportando = ref(false);

const filtros = reactive({
    estado: '',
    tipo_asignacion: '',
    area_id: '',
    vencido: false,
    acuse_pendiente: false,
    mis_turnos: false,
    fecha_desde: '',
    fecha_hasta: '',
});

const estados = ['pendiente', 'en_tramite', 'resuelto', 'cancelado'];
const tipos = ['atencion', 'conocimiento'];

function paramsDeFiltros() {
    const params = {};
    if (filtros.estado) params.estado = filtros.estado;
    if (filtros.tipo_asignacion) params.tipo_asignacion = filtros.tipo_asignacion;
    if (filtros.area_id) params.area_id = filtros.area_id;
    if (filtros.vencido) params.vencido = 1;
    if (filtros.acuse_pendiente) params.acuse_pendiente = 1;
    if (filtros.mis_turnos) params.responsable = 'me';
    if (filtros.fecha_desde) params.fecha_desde = filtros.fecha_desde;
    if (filtros.fecha_hasta) params.fecha_hasta = filtros.fecha_hasta;
    return params;
}

async function cargar(page = 1) {
    cargando.value = true;
    error.value = '';
    try {
        const { data } = await client.get('/turnos', { params: { page, ...paramsDeFiltros() } });
        items.value = data.data;
        meta.value = data.meta;
    } catch (err) {
        error.value = err.response?.data?.message || 'No se pudo cargar la bandeja de turnos.';
    } finally {
        cargando.value = false;
    }
}

async function exportar() {
    exportando.value = true;
    error.value = '';
    try {
        await descargar('/turnos/export', 'turnos.csv');
    } catch (err) {
        error.value = err.response?.data?.message || 'No se pudo exportar la bandeja.';
    } finally {
        exportando.value = false;
    }
}

onMounted(async () => {
    await cargar();
    try {
        const { data } = await client.get('/admin/areas');
        areas.value = data.data;
    } catch {
        // El listado de turnos ya está disponible aunque falle el catálogo.
    }
});

function claseEstado(estado) {
    return {
        pendiente: 'bg-primary-100 text-primary-700',
        en_tramite: 'bg-amber-100 text-amber-800',
        resuelto: 'bg-green-100 text-green-700',
        cancelado: 'bg-gray-100 text-gray-500',
    }[estado] || 'bg-gray-100 text-gray-600';
}
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-semibold text-ink">Bandeja de turnos</h1>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded border px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-50"
                    :disabled="exportando"
                    @click="exportar"
                >
                    {{ exportando ? 'Exportando…' : 'Exportar CSV' }}
                </button>
                <button type="button" class="rounded border px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100" @click="cargar()">
                    Actualizar
                </button>
            </div>
        </div>

        <div v-if="error" class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ error }}
        </div>

        <div class="mt-4 flex flex-wrap items-end gap-3 rounded-lg border bg-white p-4 text-sm">
            <label class="font-medium">
                Estado
                <select v-model="filtros.estado" class="mt-1 block w-40 rounded border border-gray-300 px-3 py-2 text-sm" @change="cargar()">
                    <option value="">Todos</option>
                    <option v-for="e in estados" :key="e" :value="e">{{ e.replace('_', ' ') }}</option>
                </select>
            </label>
            <label class="font-medium">
                Tipo de asignación
                <select v-model="filtros.tipo_asignacion" class="mt-1 block w-44 rounded border border-gray-300 px-3 py-2 text-sm" @change="cargar()">
                    <option value="">Todos</option>
                    <option v-for="t in tipos" :key="t" :value="t">{{ t }}</option>
                </select>
            </label>
            <label class="font-medium">
                Área
                <select v-model="filtros.area_id" class="mt-1 block w-52 rounded border border-gray-300 px-3 py-2 text-sm" @change="cargar()">
                    <option value="">Todas</option>
                    <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.nombre }}</option>
                </select>
            </label>
            <label class="flex items-center gap-2 pb-2">
                <input v-model="filtros.vencido" type="checkbox" @change="cargar()" />
                Solo vencidos (atención)
            </label>
            <label class="flex items-center gap-2 pb-2">
                <input v-model="filtros.acuse_pendiente" type="checkbox" @change="cargar()" />
                Con acuse pendiente
            </label>
            <label class="flex items-center gap-2 pb-2">
                <input v-model="filtros.mis_turnos" type="checkbox" @change="cargar()" />
                Mis turnos
            </label>
            <label class="font-medium">
                Entregado desde
                <input
                    v-model="filtros.fecha_desde"
                    type="date"
                    class="mt-1 block w-40 rounded border border-gray-300 px-3 py-2 text-sm"
                    @change="cargar()"
                />
            </label>
            <label class="font-medium">
                Entregado hasta
                <input
                    v-model="filtros.fecha_hasta"
                    type="date"
                    class="mt-1 block w-40 rounded border border-gray-300 px-3 py-2 text-sm"
                    @change="cargar()"
                />
            </label>
        </div>

        <div class="mt-4 overflow-x-auto rounded-lg border bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b bg-primary-700 text-xs font-medium uppercase text-white">
                    <tr>
                        <th class="px-4 py-3">Folio</th>
                        <th class="px-4 py-3">Asunto</th>
                        <th class="px-4 py-3">Área destino</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Responsable</th>
                        <th class="px-4 py-3">Plazo</th>
                        <th class="px-4 py-3">Acuse</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="cargando">
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">Cargando…</td>
                    </tr>
                    <tr v-else-if="!items.length">
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">Sin turnos que coincidan con los filtros.</td>
                    </tr>
                    <tr v-for="turno in items" :key="turno.id" class="border-b last:border-0 hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <RouterLink :to="`/documentos/${turno.folio}`" class="font-mono text-primary-700 hover:underline">
                                {{ turno.folio }}
                            </RouterLink>
                        </td>
                        <td class="max-w-56 truncate px-4 py-3" :title="turno.asunto">
                            {{ turno.asunto || '—' }}
                        </td>
                        <td class="px-4 py-3">{{ turno.area_destino?.nombre || '—' }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="turno.tipo_asignacion === 'atencion' ? 'bg-primary-100 text-primary-700' : 'bg-green-100 text-green-700'"
                            >
                                {{ turno.tipo_asignacion }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="claseEstado(turno.estado)">
                                {{ turno.estado.replace('_', ' ') }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ turno.responsable?.name || 'Sin asignar' }}</td>
                        <td class="px-4 py-3 text-xs">
                            <span v-if="turno.vencido" class="font-semibold text-red-600">
                                {{ turno.plazo }} · vencido
                            </span>
                            <span v-else>{{ turno.plazo || '—' }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <span
                                v-if="turno.acuse_pendiente"
                                class="rounded-full bg-orange-100 px-2 py-0.5 font-medium text-orange-800"
                                title="La entrega física quedó registrada; falta digitalizar el acuse sellado."
                            >
                                pendiente
                            </span>
                            <span v-else class="text-gray-400">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="meta" class="mt-3 flex items-center justify-between text-sm text-gray-600">
            <span>Página {{ meta.current_page }} de {{ meta.last_page }} · {{ meta.total }} turnos</span>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded border px-3 py-1.5 disabled:opacity-40"
                    :disabled="meta.current_page <= 1"
                    @click="cargar(meta.current_page - 1)"
                >
                    Anterior
                </button>
                <button
                    type="button"
                    class="rounded border px-3 py-1.5 disabled:opacity-40"
                    :disabled="meta.current_page >= meta.last_page"
                    @click="cargar(meta.current_page + 1)"
                >
                    Siguiente
                </button>
            </div>
        </div>
    </div>
</template>
