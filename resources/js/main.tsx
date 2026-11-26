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
// Halaman error juga tanpa sidebar: 404 untuk alamat yang tidak dikenal dirender tanpa data sesi dan menu.
const BARE_PAGES = new Set(["login-page", "profil-page", "error-page", "classroom/ujian/kerjakan-page"])

const NAMA_APLIKASI = "Ujian SMK Budi Mulia"

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

// Penolakan dari server untuk kiriman formulir (403/409 dari abort(), lihat bootstrap/app.php) tiba sebagai
// `errors.message`. Formulir hanya menampilkan galat per kolom, jadi pesan umum ini ditampilkan di sini.
router.on("error", (event) => {
  const pesan = (event.detail.errors as Record<string, string | undefined>).message
  if (pesan) toast.error(pesan)
})

type PageModule = Record<string, React.ComponentType>

// Semua halaman dimuat lazy per halaman.
const pages = import.meta.glob("./pages/**/*.tsx") as Record<string, () => Promise<PageModule>>

createInertiaApp({
  // `<Head title="Daftar ujian" />` menjadi "Daftar ujian - Ujian SMK Budi Mulia" di tab peramban.
  title: (judul) => (judul ? `${judul} - ${NAMA_APLIKASI}` : NAMA_APLIKASI),
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
