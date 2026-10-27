import { ref } from 'vue'

// A per-view snackbar: `notify(text)` for success, `notify(text, 'error')` for failures.
export function useSnack() {
  const snack = ref({ show: false, text: '', color: 'success' })
  const notify = (text, color = 'success') => {
    snack.value = { show: true, text, color }
  }
  return { snack, notify }
}
