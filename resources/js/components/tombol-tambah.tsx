import type * as React from "react"
import { Plus } from "lucide-react"

import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

type Props = Omit<React.ComponentProps<typeof Button>, "children" | "size" | "variant"> & {
  label: string
  /** Ikon pengganti `Plus` (mis. `<UserPlus />`). */
  icon?: React.ReactNode
}

/**
 * Tombol tambah utama satu halaman (Tambah Guru, Postingan baru, Ajukan SPPD, ...).
 * Layar lebar: tombol biasa berikon + label di header halaman.
 * Ponsel (di bawah 768px): FAB bulat di pojok kanan bawah, label hanya untuk pembaca layar. Konten halaman
 * diberi ruang bawah lewat `main:has([data-fab])` di index.css supaya baris terakhir tidak tertutup.
 * Pakai satu per halaman; tombol tambah di dalam formulir (tambah opsi, tambah lampiran) tetap Button biasa.
 */
export function TombolTambah({ label, icon = <Plus />, className, ...props }: Props) {
  return (
    <Button
      data-fab=""
      title={label}
      className={cn(
        "max-md:fixed max-md:right-4 max-md:bottom-[calc(1rem+env(safe-area-inset-bottom))] max-md:z-40 max-md:size-14 max-md:rounded-full max-md:p-0 max-md:shadow-lg max-md:[&_svg:not([class*='size-'])]:size-6",
        className
      )}
      {...props}
    >
      {icon}
      <span className="max-md:sr-only">{label}</span>
    </Button>
  )
}
