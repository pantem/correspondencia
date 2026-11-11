<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import client from '../api/client';

const router = useRouter();

const form = reactive({
    sentido: 'recibida',
    tipo_correspondencia: 'oficio',
    fecha_documento: new Date().toISOString().slice(0, 10),
    fecha_recepcion: new Date().toISOString().slice(0, 10),
    oficio_original: '',
    remitente: '',
    dependencia: '',
    cargo_remitente: '',
    asunto: '',
    resumen: '',
    observaciones: '',
    anexos: '',
    canal_recepcion: 'presencial',
    prioridad: 'normal',
    plazo: '',
    area_id: '',
    datos_extra: '',
});

const areas = ref([]);
const errores = ref({});
const error = ref('');
const cargando = ref(false);

const sentidos = ['recibida', 'enviada'];
const tipos = ['oficio', 'carta', 'memorando', 'solicitud', 'otro'];
const canales = ['presencial', 'correo', 'correo_electronico', 'mensajero', 'otro'];
const prioridades = ['normal', 'urgente'];

async function cargarAreas() {
    const { data } = await client.get('/admin/areas');
    areas.value = data.data;
}

onMounted(cargarAreas);

async function submit() {
    error.value = '';
    errores.value = {};
    cargando.value = true;

    const payload = { ...form };
    delete payload.area_id;
    if (form.area_id) payload.area_id = Number(form.area_id);
    if (!payload.fecha_recepcion) delete payload.fecha_recepcion;
    if (!payload.plazo) delete payload.plazo;
    if (!payload.oficio_original) delete payload.oficio_original;
    if (!payload.remitente) delete payload.remitente;
    if (!payload.dependencia) delete payload.dependencia;
    if (!payload.cargo_remitente) delete payload.cargo_remitente;
    if (!payload.resumen) delete payload.resumen;
    if (!payload.observaciones) delete payload.observaciones;
    if (!payload.anexos) delete payload.anexos;

    if (payload.datos_extra?.trim()) {
        try {
            payload.datos_extra = JSON.parse(payload.datos_extra);
        } catch {
            errores.value = { datos_extra: ['Debe ser JSON válido.'] };
            cargando.value = false;
            return;
        }
    } else {
        delete payload.datos_extra;
    }

    try {
        const { data } = await client.post('/documents', payload);
        router.push({ name: 'documents.show', params: { folio: data.data.folio } });
    } catch (err) {
        if (err.response?.status === 422) {
            errores.value = err.response.data.errors || {};
        } else {
            error.value = err.response?.data?.message || 'No se pudo registrar el documento.';
        }
    } finally {
        cargando.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-3xl">
        <h1 class="text-xl font-semibold text-ink">Registrar correspondencia</h1>

        <div v-if="error" class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ error }}
        </div>

        <form class="mt-4 rounded-lg border bg-white p-6" @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="text-sm font-medium">
                    Sentido *
                    <select v-model="form.sentido" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                        <option v-for="s in sentidos" :key="s" :value="s">{{ s }}</option>
                    </select>
                </label>
                <label class="text-sm font-medium">
                    Tipo de correspondencia *
                    <select v-model="form.tipo_correspondencia" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                        <option v-for="t in tipos" :key="t" :value="t">{{ t }}</option>
                    </select>
                </label>
                <label class="text-sm font-medium">
                    Fecha del documento *
                    <input v-model="form.fecha_documento" type="date" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                </label>
                <label class="text-sm font-medium">
                    Fecha de recepción
                    <input v-model="form.fecha_recepcion" type="date" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                </label>
                <label class="text-sm font-medium">
                    Oficio original
                    <input v-model="form.oficio_original" type="text" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" placeholder="DO-001/2027" />
                </label>
                <label class="text-sm font-medium">
                    Canal de recepción
                    <select v-model="form.canal_recepcion" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                        <option v-for="c in canales" :key="c" :value="c">{{ c.replace('_', ' ') }}</option>
                    </select>
                </label>
                <label class="text-sm font-medium">
                    Remitente
                    <input v-model="form.remitente" type="text" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                </label>
                <label class="text-sm font-medium">
                    Dependencia
                    <input v-model="form.dependencia" type="text" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                </label>
                <label class="text-sm font-medium">
                    Cargo del remitente
                    <input v-model="form.cargo_remitente" type="text" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                </label>
                <label class="text-sm font-medium">
                    Área relacionada
                    <select v-model="form.area_id" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                        <option value="">— Sin área —</option>
                        <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.nombre }}</option>
                    </select>
                </label>
                <label class="text-sm font-medium sm:col-span-2">
                    Asunto *
                    <input v-model="form.asunto" type="text" required maxlength="500" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                    <span v-if="errores.asunto" class="mt-1 block text-xs text-red-600">{{ errores.asunto[0] }}</span>
                </label>
                <label class="text-sm font-medium sm:col-span-2">
                    Resumen
                    <textarea v-model="form.resumen" rows="3" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"></textarea>
                </label>
                <label class="text-sm font-medium">
                    Observaciones
                    <textarea v-model="form.observaciones" rows="2" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"></textarea>
                </label>
                <label class="text-sm font-medium">
                    Anexos
                    <textarea v-model="form.anexos" rows="2" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" placeholder="Descripción de anexos físicos"></textarea>
                </label>
                <label class="text-sm font-medium">
                    Prioridad
                    <select v-model="form.prioridad" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                        <option v-for="p in prioridades" :key="p" :value="p">{{ p }}</option>
                    </select>
                </label>
                <label class="text-sm font-medium">
                    Plazo
                    <input v-model="form.plazo" type="date" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm" />
                </label>
                <label class="text-sm font-medium sm:col-span-2">
                    Datos especializados (JSON, opcional)
                    <textarea
                        v-model="form.datos_extra"
                        rows="2"
                        class="mt-1 w-full rounded border border-gray-300 px-3 py-2 font-mono text-sm"
                        placeholder='{"clave": "valor"}'
                    ></textarea>
                    <span v-if="errores.datos_extra" class="mt-1 block text-xs text-red-600">{{ errores.datos_extra[0] }}</span>
                </label>
            </div>

            <div class="mt-6 flex gap-3">
                <button
                    type="submit"
                    :disabled="cargando"
                    class="rounded bg-primary-600 px-5 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
                >
                    {{ cargando ? 'Registrando…' : 'Registrar y asignar folio' }}
                </button>
                <button
                    type="button"
                    class="rounded border px-5 py-2 text-sm text-gray-600 hover:bg-gray-100"
                    @click="router.back()"
                >
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</template>
