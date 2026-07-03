import { ref } from 'vue';

const STORAGE_KEY = 'matre_theme';

const isDark = ref(document.documentElement.classList.contains('dark'));

function apply(dark) {
  isDark.value = dark;
  document.documentElement.classList.toggle('dark', dark);
  localStorage.setItem(STORAGE_KEY, dark ? 'dark' : 'light');
}

export function useTheme() {
  return {
    isDark,
    toggle: () => apply(!isDark.value),
  };
}
