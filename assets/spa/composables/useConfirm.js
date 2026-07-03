import { reactive } from 'vue';
import { useToastStore } from '../stores/toasts';

const state = reactive({
  open: false,
  title: '',
  message: '',
  confirmLabel: 'Confirm',
  danger: false,
  busy: false,
});

let pending = null;

export function confirm({ title, message = '', confirmLabel = 'Confirm', danger = false, action }) {
  return new Promise((resolve) => {
    pending = { action, resolve };
    Object.assign(state, { open: true, title, message, confirmLabel, danger, busy: false });
  });
}

async function onConfirm() {
  const current = pending;
  if (!current || state.busy) return;
  state.busy = true;
  try {
    await current.action();
  } catch (e) {
    useToastStore().error(e.message);
  } finally {
    state.busy = false;
    state.open = false;
    pending = null;
    current.resolve();
  }
}

function onCancel() {
  if (state.busy) return;
  const current = pending;
  pending = null;
  state.open = false;
  current?.resolve();
}

export function useConfirm() {
  return { state, onConfirm, onCancel };
}
