import { ref, computed } from 'vue';

const TOAST_STORAGE_KEY = 'matre_pending_toast';
const toast = ref({ show: false, message: '', type: 'success' });

export function useToast() {
  const toastIcon = computed(() => {
    const icons = {
      success: 'bi-check-circle-fill',
      error: 'bi-exclamation-circle-fill',
      warning: 'bi-exclamation-triangle-fill',
      info: 'bi-info-circle-fill',
    };
    return icons[toast.value.type] || icons.info;
  });

  const showToast = (message, type = 'success', persistForRedirect = false) => {
    if (persistForRedirect) {
      sessionStorage.setItem(TOAST_STORAGE_KEY, JSON.stringify({ message, type }));
    } else {
      toast.value = { show: true, message, type };
      const duration = type === 'error' ? 5000 : 3000;
      setTimeout(() => { toast.value.show = false; }, duration);
    }
  };

  const checkPendingToast = () => {
    const pending = sessionStorage.getItem(TOAST_STORAGE_KEY);
    if (pending) {
      sessionStorage.removeItem(TOAST_STORAGE_KEY);
      try {
        const data = JSON.parse(pending);
        if (typeof data?.message !== 'string') return;
        const type = ['success', 'error', 'warning', 'info'].includes(data.type) ? data.type : 'info';
        toast.value = { show: true, message: data.message, type };
        const duration = type === 'error' ? 5000 : 3000;
        setTimeout(() => { toast.value.show = false; }, duration);
      } catch (e) {
        console.error('Toast parse error:', e);
      }
    }
  };

  return { toast, toastIcon, showToast, checkPendingToast };
}
