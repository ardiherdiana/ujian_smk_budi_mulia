import { Head, Link } from "@inertiajs/react"
import { ArrowLeft, FileQuestion, House, ServerCrash, ShieldX } from "lucide-react"

import { ThemeToggle } from "@/components/theme-toggle"
import { Button } from "@/components/ui/button"

interface Props {
  status: number
  /** Pesan dari server (mis. "Anda tidak memiliki akses ke ruang kelas ini"); null = pakai keterangan bawaan. */
  pesan: string | null
}

const ISI: Record<number, { judul: string; keterangan: string; ikon: typeof ShieldX }> = {
  403: {
    judul: "Akses ditolak",
    keterangan: "Anda tidak memiliki akses ke halaman ini.",
    ikon: ShieldX,
  },
  404: {
    judul: "Halaman tidak ditemukan",
    keterangan: "Alamat yang dibuka tidak ada, atau datanya sudah dihapus.",
    ikon: FileQuestion,
  },
  500: {
    judul: "Terjadi kesalahan",
    keterangan: "Ada masalah di server. Coba lagi beberapa saat lagi.",
    ikon: ServerCrash,
  },
  503: {
    judul: "Sedang dalam perawatan",
    keterangan: "Aplikasi sedang diperbarui. Coba lagi beberapa saat lagi.",
    ikon: ServerCrash,
  },
}

/**
 * Halaman error (403, 404, 500, 503) bergaya aplikasi, dirender dari bootstrap/app.php. Tanpa sidebar karena 404 untuk
 * alamat yang tidak dikenal dirender di luar middleware sesi, jadi data akun dan daftar kelas tidak tersedia.
 */
export function ErrorPage({ status, pesan }: Props) {
  const isi = ISI[status] ?? ISI[500]
  const Ikon = isi.ikon

  function kembali() {
    // Halaman yang dibuka langsung (tab baru) tidak punya riwayat untuk dituju.
    if (window.history.length > 1) window.history.back()
    else window.location.href = "/kelas"
  }

  return (
    <div className="flex min-h-svh flex-col">
      <Head title={isi.judul} />
      <header className="flex h-14 shrink-0 items-center justify-between border-b px-4 md:px-6">
        <div className="flex items-center gap-2">
          <img src="/logo_smk.png" alt="Logo SMK Budi Mulia" className="size-7 shrink-0 object-contain" />
          <div className="flex flex-col leading-none">
            <span className="font-medium">Ujian</span>
            <span className="text-xs text-muted-foreground">SMK Budi Mulia</span>
          </div>
        </div>
        <ThemeToggle />
      </header>

      <main className="flex flex-1 items-center justify-center p-6">
        <div className="flex max-w-md flex-col items-center gap-4 text-center">
          <span className="flex size-16 items-center justify-center rounded-full bg-muted text-muted-foreground">
            <Ikon className="size-8" />
          </span>
          <div className="flex flex-col gap-1">
            <p className="text-sm font-medium text-muted-foreground">Error {status}</p>
            <h1 className="text-2xl font-semibold">{isi.judul}</h1>
            <p className="text-sm text-balance text-muted-foreground">{pesan || isi.keterangan}</p>
          </div>
          <div className="flex flex-wrap justify-center gap-2">
            <Button variant="outline" onClick={kembali}>
              <ArrowLeft /> Kembali
            </Button>
            <Button render={<Link href="/kelas" />}>
              <House /> Ke Beranda
            </Button>
          </div>
        </div>
      </main>
    </div>
  )
}
