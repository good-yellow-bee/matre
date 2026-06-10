export function debounce(fn, ms) {
  let timer = null;
  function debounced(...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), ms);
  }
  debounced.cancel = () => clearTimeout(timer);
  return debounced;
}
