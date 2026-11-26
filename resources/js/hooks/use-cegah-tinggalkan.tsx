import * as React from "react"
import { router } from "@inertiajs/react"

import { useConfirm } from "@/hooks/use-confirm"

interface Kunjungan {
  method: string
  url: URL
}

interface Opsi {
  /** Kunjungan yang boleh lewat tanpa ditanya, mis. permintaan simpan dari halaman itu sendiri. */
  abaikan?: (visit: Kunjungan) => boolean
}

/**
 * Tanya dulu sebelum meninggalkan halaman yang masih punya perubahan belum disimpan. Pindah halaman lewat Inertia
 * (tombol Batal, tautan kembali, sidebar, Keluar) ditahan dan diganti dialog "Buang perubahan?"; menutup atau memuat
 * ulang tab memakai peringatan bawaan peramban. Render `dialog` sekali di JSX.
 */
export function useCegahTinggalkan(berubah: boolean, { abaikan }: Opsi = {}) {
  const { confirm, confirmDialog } = useConfirm()
  const abaikanRef = React.useRef(abaikan)
  // Kunjungan yang sudah disetujui pengguna diteruskan tanpa ditanya lagi.
  const lolos = React.useRef(false)

  React.useEffect(() => {
    abaikanRef.current = abaikan
  }, [abaikan])

  React.useEffect(() => {
    if (!berubah) return

    const cegahTutup = (e: BeforeUnloadEvent) => e.preventDefault()
    window.addEventListener("beforeunload", cegahTutup)

    const lepas = router.on("before", (event) => {
      const visit = event.detail.visit
      if (lolos.current || visit.prefetch || abaikanRef.current?.(visit)) return

      void confirm({
        title: "Buang perubahan?",
        description: "Ada perubahan yang belum disimpan. Perubahan itu akan hilang bila kamu meninggalkan halaman ini.",
        confirmLabel: "Buang perubahan",
        cancelLabel: "Tetap di sini",
        destructive: true,
      }).then((ok) => {
        if (!ok) return
        lolos.current = true
        router.visit(visit.url.href, {
          method: visit.method,
          data: visit.method === "get" ? {} : visit.data,
          replace: visit.replace,
          preserveScroll: visit.preserveScroll,
          preserveState: visit.preserveState,
          onFinish: () => {
            lolos.current = false
          },
        })
      })

      return false
    })

    return () => {
      window.removeEventListener("beforeunload", cegahTutup)
      lepas()
    }
  }, [berubah, confirm])

  return { dialog: confirmDialog }
}
