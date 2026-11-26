import * as React from "react"
import { router } from "@inertiajs/react"
import { toast } from "sonner"

import type { KelasTujuan } from "@/lib/classroom/types"
import { IKON_TIPE } from "@/components/classroom/post-card"
import { bentukPayload, formKosong, PostFormFields, type PostFormState } from "@/components/classroom/post-form"
import { TargetKelas } from "@/components/classroom/target-kelas"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"

interface Props {
  kelasId: string
  /** Ruang kelas lain milik guru yang sama yang bisa ikut menerima ujian. */
  tujuan: KelasTujuan[]
}

/** Tombol pembuat ujian di atas daftar ujian kelas. Hanya untuk guru pengajar dan admin. */
export function PostComposer({ kelasId, tujuan }: Props) {
  const [buka, setBuka] = React.useState(false)
  const [form, setForm] = React.useState<PostFormState>(formKosong())
  const [kelasTujuan, setKelasTujuan] = React.useState<string[]>([kelasId])
  const [errors, setErrors] = React.useState<Record<string, string>>({})
  const [memproses, setMemproses] = React.useState(false)

  function bukaDialog() {
    setForm(formKosong())
    setKelasTujuan([kelasId])
    setErrors({})
    setBuka(true)
  }

  function kirim(event: React.FormEvent) {
    event.preventDefault()
    router.post(`/kelas/${kelasId}/post`, { tipe: form.tipe, ...bentukPayload(form), kelasTujuan } as never, {
      forceFormData: true,
      preserveScroll: true,
      onStart: () => {
        setMemproses(true)
        setErrors({})
      },
      onSuccess: () => setBuka(false),
      onError: (e) => {
        setErrors(e)
        if (!Object.keys(e).some((k) => k !== "message")) return
        toast.error("Periksa isian yang berwarna merah")
      },
      onFinish: () => setMemproses(false),
    })
  }

  const Ikon = IKON_TIPE.UJIAN

  return (
    <>
      <div className="flex">
        <Button variant="outline" onClick={bukaDialog}>
          <Ikon /> Ujian baru
        </Button>
      </div>

      {/* Klik di luar dialog tidak menutupnya, supaya isian panjang tidak hilang karena klik meleset. */}
      <Dialog open={buka} onOpenChange={setBuka} disablePointerDismissal>
        <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-xl">
          <form onSubmit={kirim} className="flex flex-col gap-4">
            <DialogHeader>
              <DialogTitle>Buat ujian</DialogTitle>
              <DialogDescription>
                {kelasTujuan.length > 1
                  ? `Terlihat oleh siswa di ${kelasTujuan.length} ruang kelas yang dipilih.`
                  : "Terlihat oleh semua siswa di ruang kelas ini."}
              </DialogDescription>
            </DialogHeader>

            <PostFormFields form={form} onChange={setForm} errors={errors} onErrorsChange={setErrors} />

            <TargetKelas tujuan={tujuan} asalId={kelasId} value={kelasTujuan} onChange={setKelasTujuan} />
            {errors.kelasTujuan && <p className="text-sm text-destructive">{errors.kelasTujuan}</p>}

            <p className="text-sm text-muted-foreground">
              Soal ditambahkan setelah ujian dibuat
              {kelasTujuan.length > 1 ? " dan otomatis disalin ke semua ruang kelas yang dipilih." : "."}
            </p>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setBuka(false)}>
                Batal
              </Button>
              <Button type="submit" disabled={memproses}>
                {memproses ? "Memproses..." : "Buat & tambah soal"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  )
}
