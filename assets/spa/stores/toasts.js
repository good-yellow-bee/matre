import { defineStore } from 'pinia';

let nextId = 1;

export const useToastStore = defineStore('toasts', {
  state: () => ({ items: [] }),

  actions: {
    push(type, message, timeout = null) {
      const id = nextId++;
      this.items.push({ id, type, message });
      const ms = timeout ?? (type === 'error' ? 6000 : 3500);
      setTimeout(() => this.dismiss(id), ms);
      return id;
    },

    success(message) { return this.push('success', message); },
    error(message) { return this.push('error', message); },
    info(message) { return this.push('info', message); },

    dismiss(id) {
      this.items = this.items.filter((toast) => toast.id !== id);
    },
  },
});
