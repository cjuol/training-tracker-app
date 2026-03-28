import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
  static targets = ["button", "label", "spinner", "message"]
  static values  = { url: String, csrf: String }

  async sync() {
    this.buttonTarget.disabled = true
    this.labelTarget.textContent = "Sincronizando..."
    this.spinnerTarget.classList.remove("hidden")
    this.messageTarget.classList.add("hidden")

    try {
      const body = new URLSearchParams({ _token: this.csrfValue })
      const res  = await fetch(this.urlValue, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body,
      })
      const json = await res.json()

      this.messageTarget.classList.remove("hidden")
      if (json.synced) {
        this.messageTarget.textContent = json.message ?? "Sincronización completada."
        this.messageTarget.className = "mt-2 text-sm text-green-600 dark:text-green-400"
        // Reload after short delay so circles update
        setTimeout(() => window.location.reload(), 1500)
      } else {
        this.messageTarget.textContent = json.error ?? "Error desconocido."
        this.messageTarget.className = "mt-2 text-sm text-red-600 dark:text-red-400"
        this.buttonTarget.disabled = false
      }
    } catch (e) {
      this.messageTarget.classList.remove("hidden")
      this.messageTarget.textContent = "Error de red. Inténtalo de nuevo."
      this.messageTarget.className = "mt-2 text-sm text-red-600 dark:text-red-400"
      this.buttonTarget.disabled = false
    } finally {
      this.spinnerTarget.classList.add("hidden")
      this.labelTarget.textContent = "Sincronizar ahora"
    }
  }
}
