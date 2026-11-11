<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const email = ref('');
const password = ref('');
const error = ref('');
const loading = ref(false);

async function submit() {
    error.value = '';
    loading.value = true;
    try {
        await auth.login(email.value, password.value);
        router.push(route.query.redirect || { name: 'dashboard' });
    } catch (err) {
        error.value =
            err.response?.data?.errors?.email?.[0] ||
            err.response?.data?.message ||
            'No se pudo iniciar sesión.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-sm">
        <form
            class="rounded-lg border bg-white p-6 shadow-sm"
            @submit.prevent="submit"
        >
            <h1 class="text-xl font-semibold text-ink">Iniciar sesión</h1>
            <p class="mt-1 text-sm text-gray-500">
                Sistema de Correspondencia — Comisión para la Reconstrucción
            </p>

            <div v-if="error" class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ error }}
            </div>

            <label class="mt-4 block text-sm font-medium">
                Correo electrónico
                <input
                    v-model="email"
                    type="email"
                    required
                    autocomplete="username"
                    class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none"
                />
            </label>

            <label class="mt-3 block text-sm font-medium">
                Contraseña
                <input
                    v-model="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none"
                />
            </label>

            <button
                type="submit"
                :disabled="loading"
                class="mt-5 w-full rounded bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50"
            >
                {{ loading ? 'Entrando…' : 'Entrar' }}
            </button>
        </form>
    </div>
</template>
