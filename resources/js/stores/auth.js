import { defineStore } from 'pinia';
import client from '../api/client';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: localStorage.getItem('correspondencia_token'),
        user: null,
    }),

    getters: {
        isAuthenticated: (state) => !!state.token,
        permissions: (state) => state.user?.permissions ?? [],
    },

    actions: {
        has(permission) {
            return this.permissions.includes(permission);
        },

        async login(email, password) {
            const { data } = await client.post('/auth/login', { email, password });
            this.token = data.data.token;
            this.user = data.data.user;
            localStorage.setItem('correspondencia_token', this.token);
        },

        async fetchMe() {
            const { data } = await client.get('/auth/me');
            this.user = data.data;
        },

        async logout() {
            try {
                await client.post('/auth/logout');
            } catch {
                // El token puede haber expirado; se limpia el estado de todos modos.
            }
            this.token = null;
            this.user = null;
            localStorage.removeItem('correspondencia_token');
        },
    },
});
