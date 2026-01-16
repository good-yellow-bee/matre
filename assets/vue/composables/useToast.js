import { ref, computed } from 'vue';

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

  const showToast = (message, type = 'success') => {
    toast.value = { show: true, message, type };
    const duration = type === 'error' ? 5000 : 3000;
    setTimeout(() => { toast.value.show = false; }, duration);
  };

  return { toast, toastIcon, showToast };
}
