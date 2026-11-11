<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import client from '../api/client';
import { descargar } from '../api/descargar';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const filtros = reactive({
    folio: route.query.folio || '',
    asunto: route.query.asunto || '',
    remitente: route.query.remitente || '',
    oficio: route.query.oficio || '',
    estado: route.query.estado || '',
    fecha_desde: route.query.fecha_desde || '',
    fecha_hasta: route.query.fecha_hasta || '',
});

const areas = ref([]);
const documentos = ref([]);
const meta = ref(null);
const cargando = ref(false);
const error = ref('');
const exportando = ref(false);

const estados = ['registrado', 'en_tramite', 'resuelto', 'cancelado'];

async function buscar(page = 1) {
    cargando.value = true;
    error.value = '';
    try {
        const params = { page };
        Object.entries(filtros).forEach(([key, value]) => {
            if (value) params[key] = value;
        });
        const { data } = await client.get('/documents', { params });
        documentos.value = data.data;
        meta.value = data.meta;
        router.replace({ query: { ...filtros, page: page > 1 ? page : undefined } });
    } catch (err) {
        error.value = err.response?.data?.message || 'Error al consultar documentos.';
    } finally {
        cargando.value = false;
    }
}

async function cargarAreas() {
    const { data } = await client.get('/admin/areas');
    areas.value = data.data;
}

onMounted(() => {
    buscar(Number(route.query.page) || 1);
    cargarAreas();
});

watch(() => route.query.page, (page) => {
    if (page) buscar(Number(page));
});

async function exportar() {
    exportando.value = true;
    error.value = '';
    try {
        const params = new URLSearchParams();
        Object.entries(filtros).forEach(([key, value]) => {
            if (value) params.append(key, value);
        });
        const query = params.toString();
        await descargar(`/documents/export${query ? `?${query}` : ''}`, 'documentos.csv');
    } catch (err) {
        error.value = err.response?.data?.message || 'No se pudo exportar la correspondencia.';
    } finally {
        exportando.value = false;
    }
}

function limpiar() {
    Object.keys(filtros).forEach((key) => (filtros[key] = ''));
    buscar(1);
}

function claseEstado(estado) {
    return {
        registrado: 'bg-primary-100 text-primary-700',
        en_tramite: 'bg-amber-100 text-amber-800',
        resuelto: 'bg-green-100 text-green-700',
        cancelado: 'bg-gray-100 text-gray-500',
    }[estado] || 'bg-gray-100 text-gray-600';
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-ink">Correspondencia</h1>
            <RouterLink
                v-if="auth.has('documents.create')"
                to="/documentos/nuevo"
                class="rounded bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
            >
                Registrar documento
            </RouterLink>
        </div>

        <form
            class="mt-4 grid grid-cols-1 gap-3 rounded-lg border bg-white p-4 sm:grid-cols-2 lg:grid-cols-4"
            @submit.prevent="buscar(1)"
        >
            <label class="text-sm">
                Folio
                <input
                    v-model="filtros.folio"
                    type="text"
                    placeholder="0001-2027"
                    class="mt-1 w-full rounded border border-gray-300 px-3 py-1.5 text-sm"
                />
            </label>
            <label class="text-sm">
                Asunto
                <input v-model="filtros.asunto" type="text" class="mt-1 w-full rounded border border-gray-300 px-3 py-1.5 text-sm" />
            </label>
            <label class="text-sm">
                Remitente
                <input v-model="filtros.remitente" type="text" class="mt-1 w-full rounded border border-gray-300 px-3 py-1.5 text-sm" />
            </label>
            <label class="text-sm">
                Oficio original
                <input v-model="filtros.oficio" type="text" class="mt-1 w-full rounded border border-gray-300 px-3 py-1.5 text-sm" />
            </label>
            <label class="text-sm">
                Estado
                <select v-model="filtros.estado" class="mt-1 w-full rounded border border-gray-300 px-3 py-1.5 text-sm">
                    <option value="">Todos</option>
                    <option v-for="estado in estados" :key="estado" :value="estado">{{ estado.replace('_', ' ') }}</option>
                </select>
            </label>
            <label class="text-sm">
                Desde
                <input v-model="filtros.fecha_desde" type="date" class="mt-1 w-full rounded border border-gray-300 px-3 py-1.5 text-sm" />
            </label>
            <label class="text-sm">
                Hasta
                <input v-model="filtros.fecha_hasta" type="date" class="mt-1 w-full rounded border border-gray-300 px-3 py-1.5 text-sm" />
            </label>
            <div class="flex items-end gap-2">
            <button type="submit" class="rounded bg-primary-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-primary-700">
                Buscar
            </button>
            <button
                type="button"
                class="rounded border px-4 py-1.5 text-sm text-gray-600 hover:bg-gray-100"
                @click="limpiar"
            >
                Limpiar
            </button>
            <button
                type="button"
                class="rounded border px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-50"
                :disabled="exportando"
                @click="exportar"
            >
                {{ exportando ? 'Exportando…' : 'Exportar CSV' }}
            </button>
            </div>
        </form>

        <div v-if="error" class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ error }}
        </div>

        <div class="mt-4 overflow-x-auto rounded-lg border bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b bg-primary-700 text-xs font-medium uppercase text-white">
                    <tr>
                        <th class="px-4 py-3">Folio</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Sentido</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Remitente</th>
                        <th class="px-4 py-3">Asunto</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Área</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="cargando">
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">Cargando…</td>
                    </tr>
                    <tr v-else-if="!documentos.length">
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">
                            No se encontraron documentos.
                        </td>
                    </tr>
                    <tr
                        v-for="doc in documentos"
                        :key="doc.folio"
                        class="border-b last:border-0 hover:bg-gray-50"
                    >
                        <td class="px-4 py-3 font-mono">
                            <RouterLink :to="`/documentos/${doc.folio}`" class="text-primary-700 hover:underline">
                                {{ doc.folio }}
                            </RouterLink>
                        </td>
                        <td class="px-4 py-3">{{ doc.fecha_documento }}</td>
                        <td class="px-4 py-3">{{ doc.sentido }}</td>
                        <td class="px-4 py-3">{{ doc.tipo_correspondencia }}</td>
                        <td class="px-4 py-3">{{ doc.remitente || '—' }}</td>
                        <td class="max-w-xs truncate px-4 py-3">{{ doc.asunto }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="claseEstado(doc.estado)">
                                {{ doc.estado.replace('_', ' ') }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ doc.area?.nombre || '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="meta" class="mt-3 flex items-center justify-between text-sm text-gray-600">
            <span>
                Página {{ meta.current_page }} de {{ meta.last_page }} — {{ meta.total }} documentos
            </span>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded border px-3 py-1.5 disabled:opacity-40"
                    :disabled="meta.current_page <= 1"
                    @click="buscar(meta.current_page - 1)"
                >
                    Anterior
                </button>
                <button
                    type="button"
                    class="rounded border px-3 py-1.5 disabled:opacity-40"
                    :disabled="meta.current_page >= meta.last_page"
                    @click="buscar(meta.current_page + 1)"
                >
                    Siguiente
                </button>
            </div>
        </div>
    </div>
</template>
