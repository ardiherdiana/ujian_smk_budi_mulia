import { StrictMode } from "react"
import { createRoot } from "react-dom/client"
import { createInertiaApp, router } from "@inertiajs/react"
import { toast } from "sonner"

import "./index.css"
import { ThemeProvider } from "@/components/theme-provider.tsx"
import { Toaster } from "@/components/ui/sonner.tsx"
import { ClassroomLayout } from "@/layouts/classroom-layout"
import type { FlashToast } from "@/lib/types"

// Halaman full-bleed (tanpa sidebar). Halaman lain memakai ClassroomLayout (sidebar ujian), sama seperti Classroom.
const BARE_PAGES = new Set(["login-page", "profil-page", "classroom/ujian/kerjakan-page"])

window.addEventListener("pageshow", (event) => {
  if (event.persisted) {
    window.location.reload()
  }
})

// Toast dari flash session server (`->with('toast', [...])`).
router.on("success", (event) => {
  const flash = (event.detail.page.props as { flash?: { toast?: FlashToast | null } }).flash
  const message = flash?.toast
  if (!message) return
  const show = toast[message.type] ?? toast.info
  show(message.message)
})

type PageModule = Record<string, React.ComponentType>

// Semua halaman dimuat lazy per halaman.
const pages = import.meta.glob("./pages/**/*.tsx") as Record<string, () => Promise<PageModule>>

createInertiaApp({
  resolve: async (name) => {
    const page = await pages[`./pages/${name}.tsx`]?.()
    if (!page) throw new Error(`Page not found: ${name}`)

    const component = Object.values(page)[0]

    if (!BARE_PAGES.has(name)) {
      // @ts-expect-error - Inertia's persistent-layout convention
      component.layout = (page: React.ReactNode) => <ClassroomLayout>{page}</ClassroomLayout>
    }

    return component
  },
  setup({ el, App, props }) {
    createRoot(el).render(
      <StrictMode>
        <ThemeProvider>
          <App {...props} />
          <Toaster />
        </ThemeProvider>
      </StrictMode>
    )
  },
})
