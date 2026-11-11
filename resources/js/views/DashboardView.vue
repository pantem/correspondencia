<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import client from '../api/client';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();

const kpis = ref(null);
const pendientesPorArea = ref([]);
const misTurnos = ref([]);
const cargando = ref(true);
const error = ref('');

const tarjetas = computed(() => {
    if (!kpis.value) return [];
    return [
        { etiqueta: 'Documentos registrados', valor: kpis.value.documentos, color: 'text-primary-700' },
        { etiqueta: 'Turnos pendientes', valor: kpis.value.turnos_pendientes, color: 'text-primary-700' },
        { etiqueta: 'Turnos en trámite', valor: kpis.value.turnos_en_tramite, color: 'text-amber-700' },
        { etiqueta: 'Turnos resueltos', valor: kpis.value.turnos_resueltos, color: 'text-green-700' },
        { etiqueta: 'Acuses por digitalizar', valor: kpis.value.acuses_pendientes, color: 'text-orange-700' },
        { etiqueta: 'Turnos vencidos', valor: kpis.value.turnos_vencidos, color: 'text-red-700' },
    ];
});

const maximoArea = computed(() =>
    Math.max(1, ...pendientesPorArea.value.map((fila) => fila.total)),
);

onMounted(async () => {
    try {
        const { data } = await client.get('/dashboard');
        kpis.value = data.data.kpis;
        pendientesPorArea.value = data.data.pendientes_por_area;
        misTurnos.value = data.data.mis_turnos;
    } catch (err) {
        error.value = err.response?.data?.message || 'No se pudo cargar la portada.';
    } finally {
        cargando.value = false;
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
            <div>
                <h1 class="text-xl font-semibold text-ink">Inicio</h1>
                <p class="mt-1 text-sm text-gray-500">Estado general de la correspondencia y de tus turnos.</p>
            </div>
            <RouterLink
                v-if="auth.has('documents.create')"
                to="/documentos/nuevo"
                class="rounded bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
            >
                Registrar documento
            </RouterLink>
        </div>

        <div v-if="error" class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ error }}
        </div>

        <p v-if="cargando && !error" class="mt-6 text-sm text-gray-500">Cargando…</p>

        <template v-if="kpis">
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                <div v-for="tarjeta in tarjetas" :key="tarjeta.etiqueta" class="rounded-xl border bg-white p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        {{ tarjeta.etiqueta }}
                    </p>
                    <p class="mt-1 text-3xl font-bold" :class="tarjeta.color">{{ tarjeta.valor }}</p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <section class="rounded-lg border bg-white p-5 lg:col-span-2">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold">Mis turnos pendientes</h2>
                        <RouterLink to="/turnos" class="text-sm text-primary-700 hover:underline">Ver bandeja →</RouterLink>
                    </div>

                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b bg-primary-700 text-xs font-medium uppercase text-white">
                                <tr>
                                    <th class="px-3 py-2">Folio</th>
                                    <th class="px-3 py-2">Asunto</th>
                                    <th class="px-3 py-2">Área</th>
                                    <th class="px-3 py-2">Estado</th>
                                    <th class="px-3 py-2">Plazo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!misTurnos.length">
                                    <td colspan="5" class="px-3 py-5 text-center text-gray-500">
                                        No tienes turnos pendientes. ¡Buen trabajo!
                                    </td>
                                </tr>
                                <tr v-for="turno in misTurnos" :key="turno.id" class="border-b last:border-0 hover:bg-gray-50">
                                    <td class="px-3 py-3 font-mono">
                                        <RouterLink :to="`/documentos/${turno.folio}`" class="text-primary-700 hover:underline">
                                            {{ turno.folio }}
                                        </RouterLink>
                                    </td>
                                    <td class="max-w-56 truncate px-3 py-3" :title="turno.asunto">
                                        {{ turno.asunto || '—' }}
                                    </td>
                                    <td class="px-3 py-3">{{ turno.area_destino?.nombre || '—' }}</td>
                                    <td class="px-3 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-xs" :class="claseEstado(turno.estado)">
                                            {{ turno.estado.replace('_', ' ') }}
                                        </span>
                                        <span
                                            v-if="turno.acuse_pendiente"
                                            class="ml-1 rounded-full bg-orange-100 px-2 py-0.5 text-xs text-orange-800"
                                        >
                                            acuse pendiente
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-xs">
                                        <span v-if="turno.vencido" class="font-semibold text-red-600">
                                            {{ turno.plazo }} · vencido
                                        </span>
                                        <span v-else>{{ turno.plazo || '—' }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <div class="space-y-4">
                    <section class="rounded-lg border bg-white p-5">
                        <h2 class="font-semibold">Turnos pendientes por área</h2>
                        <ul v-if="pendientesPorArea.length" class="mt-3 space-y-3">
                            <li v-for="fila in pendientesPorArea" :key="fila.area">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="truncate pr-2">{{ fila.area }}</span>
                                    <span class="font-semibold text-primary-700">{{ fila.total }}</span>
                                </div>
                                <div class="mt-1 h-2 w-full rounded-full bg-primary-50">
                                    <div
                                        class="h-2 rounded-full bg-primary-600"
                                        :style="{ width: `${(fila.total / maximoArea) * 100}%` }"
                                    />
                                </div>
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-sm text-gray-500">Sin turnos abiertos.</p>
                    </section>

                    <section class="rounded-lg border bg-white p-5">
                        <h2 class="font-semibold">Accesos rápidos</h2>
                        <div class="mt-3 flex flex-col gap-2 text-sm">
                            <RouterLink
                                v-if="auth.has('documents.create')"
                                to="/documentos/nuevo"
                                class="rounded border px-3 py-2 text-center text-primary-700 hover:bg-primary-50"
                            >
                                Registrar documento
                            </RouterLink>
                            <RouterLink
                                to="/documentos"
                                class="rounded border px-3 py-2 text-center text-primary-700 hover:bg-primary-50"
                            >
                                Buscar correspondencia
                            </RouterLink>
                            <RouterLink
                                v-if="auth.has('documents.view')"
                                to="/turnos"
                                class="rounded border px-3 py-2 text-center text-primary-700 hover:bg-primary-50"
                            >
                                Bandeja de turnos
                            </RouterLink>
                            <RouterLink
                                v-if="auth.has('audit.view')"
                                to="/bitacora"
                                class="rounded border px-3 py-2 text-center text-primary-700 hover:bg-primary-50"
                            >
                                Bitácora de auditoría
                            </RouterLink>
                        </div>
                    </section>
                </div>
            </div>
        </template>
    </div>
</template>
