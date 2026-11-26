import type { Post, TipePost } from "@/lib/classroom/types"
import { keInputWaktu } from "@/lib/classroom/format"
import { LAMPIRAN_KOSONG, LampiranInput, type LampiranBaru } from "@/components/classroom/lampiran-input"
import { Checkbox } from "@/components/ui/checkbox"
import { Field, FieldDescription, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"

export interface PostFormState {
  tipe: TipePost
  judul: string
  isi: string
  deadline: string
  poinMaks: string
  izinTerlambat: boolean
  durasiMenit: string
  mulaiAt: string
  selesaiAt: string
  acakSoal: boolean
  tampilkanNilai: boolean
  wajibToken: boolean
  lampiran: LampiranBaru
}

export function formKosong(tipe: TipePost = "UJIAN"): PostFormState {
  return {
    tipe,
    judul: "",
    isi: "",
    deadline: "",
    poinMaks: "100",
    izinTerlambat: true,
    durasiMenit: "60",
    mulaiAt: "",
    selesaiAt: "",
    acakSoal: false,
    tampilkanNilai: true,
    wajibToken: false,
    lampiran: LAMPIRAN_KOSONG,
  }
}

export function formDariPost(post: Post): PostFormState {
  return {
    tipe: post.tipe,
    judul: post.judul ?? "",
    isi: post.isi ?? "",
    deadline: keInputWaktu(post.deadline),
    poinMaks: String(post.poinMaks ?? 100),
    izinTerlambat: post.izinTerlambat,
    durasiMenit: String(post.durasiMenit ?? 60),
    mulaiAt: keInputWaktu(post.mulaiAt),
    selesaiAt: keInputWaktu(post.selesaiAt),
    acakSoal: post.acakSoal,
    tampilkanNilai: post.tampilkanNilai,
    wajibToken: post.wajibToken,
    lampiran: LAMPIRAN_KOSONG,
  }
}

/**
 * Kolom ujian; boolean dikirim 1/0 supaya lolos aturan `boolean` Laravel di multipart. Berbeda dengan Classroom (ujian
 * tanpa lampiran), aplikasi ujian mengizinkan lampiran berkas/tautan pada ujian, mis. kisi-kisi atau lembar rumus.
 */
export function bentukPayload(form: PostFormState): Record<string, unknown> {
  return {
    isi: form.isi,
    berkas: form.lampiran.berkas,
    tautan: form.lampiran.tautan,
    judul: form.judul,
    durasiMenit: form.durasiMenit,
    mulaiAt: form.mulaiAt,
    selesaiAt: form.selesaiAt,
    acakSoal: form.acakSoal ? 1 : 0,
    tampilkanNilai: form.tampilkanNilai ? 1 : 0,
    wajibToken: form.wajibToken ? 1 : 0,
  }
}

/** Galat server yang ikut hilang saat isian ini diubah (kunci galat berkas/tautan bisa bernomor, mis. `berkas.0`). */
const GALAT_KOLOM: Partial<Record<keyof PostFormState, string[]>> = {
  judul: ["judul"],
  isi: ["isi"],
  durasiMenit: ["durasiMenit"],
  mulaiAt: ["mulaiAt", "selesaiAt"],
  selesaiAt: ["selesaiAt"],
  lampiran: ["berkas", "tautan"],
}

/**
 * Galat setelah isian berubah dari `lama` ke `baru`: pesan kolom yang diubah dibuang, kecuali jadwal yang masih
 * salah (ditutup tidak setelah dibuka) supaya pesannya baru hilang saat tanggalnya benar.
 */
function galatSetelahUbah(errors: Record<string, string>, lama: PostFormState, baru: PostFormState): Record<string, string> {
  const buang = (Object.keys(GALAT_KOLOM) as (keyof PostFormState)[])
    .filter((k) => lama[k] !== baru[k])
    .flatMap((k) => GALAT_KOLOM[k] ?? [])
  if (buang.length === 0) return errors

  // Nilai datetime-local (YYYY-MM-DDTHH:mm) bisa dibandingkan sebagai teks.
  const jadwalSalah = baru.mulaiAt !== "" && baru.selesaiAt !== "" && baru.selesaiAt <= baru.mulaiAt
  return Object.fromEntries(
    Object.entries(errors).filter(([kunci]) => {
      const kolom = kunci.split(".")[0]
      return !buang.includes(kolom) || (kolom === "selesaiAt" && jadwalSalah)
    })
  )
}

interface PostFormFieldsProps {
  form: PostFormState
  onChange: (form: PostFormState) => void
  errors: Record<string, string>
  /** Dipanggil dengan galat yang sudah dibersihkan dari kolom yang baru diubah. */
  onErrorsChange: (errors: Record<string, string>) => void
}

export function PostFormFields({ form, onChange, errors, onErrorsChange }: PostFormFieldsProps) {
  const set = <K extends keyof PostFormState>(key: K, value: PostFormState[K]) => {
    const baru = { ...form, [key]: value }
    const galat = galatSetelahUbah(errors, form, baru)
    if (galat !== errors) onErrorsChange(galat)
    onChange(baru)
  }

  return (
    <FieldGroup>
      <Field data-invalid={!!errors.judul}>
        <FieldLabel htmlFor="post-judul">Judul</FieldLabel>
        <Input
          id="post-judul"
          value={form.judul}
          onChange={(e) => set("judul", e.target.value)}
          placeholder="mis. Kuis 1: Dasar Jaringan"
          aria-invalid={!!errors.judul}
          maxLength={255}
        />
        {errors.judul && <p className="text-sm text-destructive">{errors.judul}</p>}
      </Field>

      <Field data-invalid={!!errors.isi}>
        <FieldLabel htmlFor="post-isi">Deskripsi (opsional)</FieldLabel>
        <Textarea
          id="post-isi"
          value={form.isi}
          onChange={(e) => set("isi", e.target.value)}
          rows={3}
          aria-invalid={!!errors.isi}
          maxLength={10000}
        />
        {errors.isi && <p className="text-sm text-destructive">{errors.isi}</p>}
      </Field>

      <>
          <Field data-invalid={!!errors.durasiMenit}>
            <FieldLabel htmlFor="post-durasi">Durasi (menit)</FieldLabel>
            <Input
              id="post-durasi"
              type="number"
              min={1}
              max={300}
              value={form.durasiMenit}
              onChange={(e) => set("durasiMenit", e.target.value)}
              aria-invalid={!!errors.durasiMenit}
              className="sm:max-w-40"
            />
            {errors.durasiMenit && <p className="text-sm text-destructive">{errors.durasiMenit}</p>}
          </Field>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field data-invalid={!!errors.mulaiAt}>
              <FieldLabel htmlFor="post-mulai">Dibuka (opsional)</FieldLabel>
              <Input
                id="post-mulai"
                type="datetime-local"
                value={form.mulaiAt}
                onChange={(e) => set("mulaiAt", e.target.value)}
                aria-invalid={!!errors.mulaiAt}
              />
              {errors.mulaiAt && <p className="text-sm text-destructive">{errors.mulaiAt}</p>}
            </Field>
            <Field data-invalid={!!errors.selesaiAt}>
              <FieldLabel htmlFor="post-selesai">Ditutup (opsional)</FieldLabel>
              <Input
                id="post-selesai"
                type="datetime-local"
                value={form.selesaiAt}
                onChange={(e) => set("selesaiAt", e.target.value)}
                aria-invalid={!!errors.selesaiAt}
              />
              {errors.selesaiAt && <p className="text-sm text-destructive">{errors.selesaiAt}</p>}
            </Field>
          </div>
          <Field orientation="horizontal">
            <Checkbox id="post-acak" checked={form.acakSoal} onCheckedChange={(v) => set("acakSoal", v === true)} />
            <FieldLabel htmlFor="post-acak">Acak urutan soal tiap siswa</FieldLabel>
          </Field>
          <Field orientation="horizontal">
            <Checkbox
              id="post-nilai"
              checked={form.tampilkanNilai}
              onCheckedChange={(v) => set("tampilkanNilai", v === true)}
            />
            <div className="flex flex-col gap-0.5">
              <FieldLabel htmlFor="post-nilai">Tampilkan nilai ke siswa setelah selesai</FieldLabel>
              <FieldDescription>Soal dan jawaban benar tidak pernah ditampilkan ke siswa.</FieldDescription>
            </div>
          </Field>
          <Field orientation="horizontal">
            <Checkbox id="post-token" checked={form.wajibToken} onCheckedChange={(v) => set("wajibToken", v === true)} />
            <div className="flex flex-col gap-0.5">
              <FieldLabel htmlFor="post-token">Wajib token untuk memulai</FieldLabel>
              <FieldDescription>
                Token dibuat otomatis dan tampil di halaman ujian. Siswa memasukkannya saat menekan Mulai.
              </FieldDescription>
            </div>
          </Field>
      </>

      <Field>
        <FieldLabel>Lampiran (opsional)</FieldLabel>
        <LampiranInput
          value={form.lampiran}
          onChange={(v) => set("lampiran", v)}
          error={errors.berkas ?? errors["berkas.0"] ?? errors.tautan ?? errors["tautan.0"]}
        />
      </Field>
    </FieldGroup>
  )
}
