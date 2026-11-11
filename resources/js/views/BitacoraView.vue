<script setup>
import { onMounted, reactive, ref } from 'vue';
import client from '../api/client';

const items = ref([]);
const meta = ref(null);
const error = ref('');
const cargando = ref(false);

const filtros = reactive({ accion: '', fecha_desde: '', fecha_hasta: '' });

async function cargar(page = 1) {
    cargando.value = true;
    error.value = '';
    try {
        const params = { page };
        Object.entries(filtros).forEach(([key, value]) => {
            if (value) params[key] = value;
        });
        const { data } = await client.get('/audit-logs', { params });
        items.value = data.data;
        meta.value = data.meta;
    } catch (err) {
        error.value = err.response?.data?.message || 'Error al cargar la bitácora.';
    } finally {
        cargando.value = false;
    }
}

onMounted(() => cargar());

function formatear(valores) {
    if (!valores || !Object.keys(valores).length) return '—';
    return Object.entries(valores)
        .map(([k, v]) => `${k}: ${v}`)
        .join(' | ');
}
</script>

<template>
    <div>
        <h1 class="text-xl font-semibold text-ink">Bitácora</h1>

        <form
            class="mt-4 flex flex-wrap items-end gap-3 rounded-lg border bg-white p-4"
            @submit.prevent="cargar(1)"
        >
            <label class="text-sm font-medium">
                Acción
                <input v-model="filtros.accion" type="text" placeholder="documento.creado" class="mt-1 w-56 rounded border border-gray-300 px-3 py-1.5 text-sm" />
            </label>
            <label class="text-sm font-medium">
                Desde
                <input v-model="filtros.fecha_desde" type="date" class="mt-1 w-40 rounded border border-gray-300 px-3 py-1.5 text-sm" />
            </label>
            <label class="text-sm font-medium">
                Hasta
                <input v-model="filtros.fecha_hasta" type="date" class="mt-1 w-40 rounded border border-gray-300 px-3 py-1.5 text-sm" />
            </label>
            <button type="submit" class="rounded bg-primary-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-primary-700">
                Filtrar
            </button>
        </form>

        <div v-if="error" class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ error }}
        </div>

        <div class="mt-4 overflow-x-auto rounded-lg border bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b bg-primary-700 text-xs font-medium uppercase text-white">
                    <tr>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Usuario</th>
                        <th class="px-4 py-3">Acción</th>
                        <th class="px-4 py-3">Entidad</th>
                        <th class="px-4 py-3">Cambios</th>
                        <th class="px-4 py-3">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="cargando">
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">Cargando…</td>
                    </tr>
                    <tr v-else-if="!items.length">
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">Sin registros.</td>
                    </tr>
                    <tr v-for="log in items" :key="log.id" class="border-b last:border-0 align-top hover:bg-gray-50">
                        <td class="whitespace-nowrap px-4 py-3 text-xs">{{ log.created_at }}</td>
                        <td class="px-4 py-3">{{ log.user?.name || '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ log.accion }}</td>
                        <td class="px-4 py-3 text-xs">
                            {{ log.auditable_type.split('\\').pop() }} #{{ log.auditable_id }}
                        </td>
                        <td class="max-w-md px-4 py-3 text-xs text-gray-600">
                            <div v-if="log.old_values" class="text-red-700/80">- {{ formatear(log.old_values) }}</div>
                            <div v-if="log.new_values" class="text-green-700/80">+ {{ formatear(log.new_values) }}</div>
                            <span v-if="!log.old_values && !log.new_values">—</span>
                        </td>
                        <td class="px-4 py-3 text-xs">{{ log.ip || '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="meta" class="mt-3 flex items-center justify-between text-sm text-gray-600">
            <span>Página {{ meta.current_page }} de {{ meta.last_page }} — {{ meta.total }} registros</span>
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
