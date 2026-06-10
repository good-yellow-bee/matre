import { defineStore } from 'pinia';
import { api } from '../api/client';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    me: null,
    bootstrapped: false,
    bootstrapping: null,
  }),

  getters: {
    isAuthenticated: (state) => state.me?.authenticated === true,
    twoFactorPending: (state) => state.me?.twoFactorRequired === true && !state.me?.authenticated,
    requires2faSetup: (state) => state.me?.requires2faSetup === true,
    user: (state) => state.me?.user ?? null,
    isAdmin: (state) => state.me?.user?.roles?.includes('ROLE_ADMIN') ?? false,
    settings: (state) => state.me?.settings ?? {},
    urls: (state) => state.me?.urls ?? {},
  },

  actions: {
    async bootstrap(force = false) {
      if (this.bootstrapped && !force) return;
      if (!this.bootstrapping) {
        this.bootstrapping = api.get('/api/me')
          .then((me) => { this.me = me; this.bootstrapped = true; })
          .finally(() => { this.bootstrapping = null; });
      }
      await this.bootstrapping;
    },

    async login(username, password, rememberMe = false) {
      const result = await api.post('/api/login', { username, password, _remember_me: rememberMe });
      await this.bootstrap(true);
      return result;
    },

    async verify2fa(code) {
      const result = await api.post('/2fa_check', { _auth_code: code });
      await this.bootstrap(true);
      return result;
    },

    async logout() {
      try {
        await api.post('/logout');
      } finally {
        this.reset();
      }
    },

    reset() {
      this.me = null;
      this.bootstrapped = false;
    },
  },
});
