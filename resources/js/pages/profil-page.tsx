import * as React from "react"
import { Head, useForm, usePage } from "@inertiajs/react"
import { CalendarDays, IdCard, Mail, School, UserRound } from "lucide-react"

import { HeaderMandiri } from "@/components/header-mandiri"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import { Input } from "@/components/ui/input"
import { initials } from "@/lib/utils"

interface ProfilProps {
  akun: {
    nama: string
    username: string | null
    email: string | null
    peran: "ADMIN" | "KEPSEK" | "GURU" | "SISWA" | null
    bergabung: string | null
    fotoUrl: string | null
  }
  siswa: { nis: string | null; rombel: string | null } | null
  [key: string]: unknown
}

const PERAN: Record<string, string> = {
  ADMIN: "Administrator",
  KEPSEK: "Kepala Sekolah",
  GURU: "Guru",
  SISWA: "Siswa",
}

function Baris({
  icon: Icon,
  label,
  children,
}: {
  icon: React.ComponentType<{ className?: string }>
  label: string
  children: React.ReactNode
}) {
  return (
    <div className="flex items-start gap-3 text-sm">
      <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
      <div className="min-w-0">
        <div className="text-xs text-muted-foreground">{label}</div>
        <div className="break-words">{children}</div>
      </div>
    </div>
  )
}

function InformasiAkun({ akun, siswa }: ProfilProps) {
  const bergabung = akun.bergabung
    ? new Date(akun.bergabung).toLocaleDateString("id-ID", {
        day: "numeric",
        month: "long",
        year: "numeric",
      })
    : null

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Informasi Akun</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-5">
        <div className="flex items-start gap-4">
          <Avatar size="lg">
            {akun.fotoUrl && <AvatarImage src={akun.fotoUrl} alt={akun.nama} className="object-top" />}
            <AvatarFallback>{initials(akun.nama)}</AvatarFallback>
          </Avatar>
          <div className="flex min-w-0 flex-1 flex-col gap-3">
            {/* Nama di kiri, badge peran di seberang kanannya. */}
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0">
                <p className="truncate text-base font-medium">{akun.nama}</p>
                <p className="truncate text-sm text-muted-foreground">
                  {akun.username ? `@${akun.username}` : akun.email}
                </p>
              </div>
              <div className="flex shrink-0 flex-wrap justify-end gap-1.5">
                {akun.peran && <Badge variant="secondary">{PERAN[akun.peran]}</Badge>}
              </div>
            </div>
          </div>
        </div>

        <div className="flex flex-col gap-3 border-t pt-4">
          {akun.username && (
            <Baris icon={UserRound} label="Nama pengguna">
              {akun.username}
            </Baris>
          )}
          {akun.email && (
            <Baris icon={Mail} label="Email">
              {akun.email}
            </Baris>
          )}
          {siswa?.nis && (
            <Baris icon={IdCard} label="NIS">
              {siswa.nis}
            </Baris>
          )}
          {siswa?.rombel && (
            <Baris icon={School} label="Rombel">
              {siswa.rombel}
            </Baris>
          )}
          {bergabung && (
            <Baris icon={CalendarDays} label="Akun dibuat">
              {bergabung}
            </Baris>
          )}
        </div>
      </CardContent>
    </Card>
  )
}

function GantiPassword() {
  const form = useForm({
    current_password: "",
    password: "",
    password_confirmation: "",
  })

  function simpan(event: React.FormEvent) {
    event.preventDefault()
    form.patch("/profil/password", {
      preserveScroll: true,
      onSuccess: () => form.reset(),
    })
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Ganti Password</CardTitle>
        <CardDescription>Minimal 8 karakter, berisi huruf dan angka.</CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={simpan}>
          <FieldGroup>
            <Field data-invalid={!!form.errors.current_password}>
              <FieldLabel htmlFor="pw-lama">Password saat ini</FieldLabel>
              <Input
                id="pw-lama"
                type="password"
                autoComplete="current-password"
                required
                value={form.data.current_password}
                onChange={(e) => form.setData("current_password", e.target.value)}
                aria-invalid={!!form.errors.current_password}
              />
              {form.errors.current_password && (
                <p className="text-sm text-destructive">{form.errors.current_password}</p>
              )}
            </Field>
            <Field data-invalid={!!form.errors.password}>
              <FieldLabel htmlFor="pw-baru">Password baru</FieldLabel>
              <Input
                id="pw-baru"
                type="password"
                autoComplete="new-password"
                required
                minLength={8}
                maxLength={100}
                value={form.data.password}
                onChange={(e) => form.setData("password", e.target.value)}
                aria-invalid={!!form.errors.password}
              />
              {form.errors.password && <p className="text-sm text-destructive">{form.errors.password}</p>}
            </Field>
            <Field>
              <FieldLabel htmlFor="pw-ulang">Ulangi password baru</FieldLabel>
              <Input
                id="pw-ulang"
                type="password"
                autoComplete="new-password"
                required
                value={form.data.password_confirmation}
                onChange={(e) => form.setData("password_confirmation", e.target.value)}
              />
            </Field>
            <Field>
              <Button type="submit" disabled={form.processing} className="w-fit">
                {form.processing ? "Menyimpan..." : "Simpan Password"}
              </Button>
            </Field>
          </FieldGroup>
        </form>
      </CardContent>
    </Card>
  )
}

export function ProfilPage() {
  const props = usePage<ProfilProps>().props

  return (
    <div className="flex min-h-svh flex-col">
      <Head title="Profil" />
      <HeaderMandiri judul="Profil" />

      {/* Satu kolom ke bawah: data akun dulu, lalu ganti password. */}
      <main className="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 md:p-6">
        <div>
          <h1 className="text-xl font-semibold">Profil Saya</h1>
          <p className="text-sm text-muted-foreground">
            Data akun dan password aplikasi ujian.
          </p>
        </div>

        <InformasiAkun {...props} />
        <GantiPassword />
      </main>
    </div>
  )
}
