import * as React from "react"
import { Link, usePage } from "@inertiajs/react"
import {
  Archive,
  CalendarDays,
  CalendarRange,
  ChevronDown,
  GraduationCap,
  House,
  Library,
  ListChecks,
  School,
  UserRound,
  Users,
} from "lucide-react"

import type { KelasSaya } from "@/lib/classroom/types"
import { useAuth } from "@/context/classroom-auth"
import { warnaKelas } from "@/lib/classroom/kelas-warna"
import { cn } from "@/lib/classroom/utils"
import {
  Sidebar,
  SidebarContent,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
  SidebarSeparator,
  useSidebar,
} from "@/components/ui/sidebar"

interface NavItem {
  title: string
  url: string
  icon: React.ComponentType<{ className?: string }>
  /** Aktif hanya jika alamat persis sama (Beranda), bukan sekadar diawali. */
  persis?: boolean
}

// Sama dengan sidebar Classroom, tanpa Menu Utama, plus Profil.
const UTAMA: NavItem[] = [
  { title: "Beranda", url: "/kelas", icon: House, persis: true },
  { title: "Kalender", url: "/kalender", icon: CalendarDays },
]

const UJIAN_ITEM: NavItem = { title: "Daftar ujian", url: "/ujian", icon: ListChecks }
const ARSIP_ITEM: NavItem = { title: "Kelas yang diarsipkan", url: "/kelas?arsip=1", icon: Archive }
const PROFIL_ITEM: NavItem = { title: "Profil", url: "/profil", icon: UserRound }

// Data induk: admin mengelola, kepsek hanya melihat (tombol ubah disembunyikan di halamannya).
// Urut sesuai alur pengisian: tiap item hanya butuh item di atasnya (Rombel memilih wali dari Guru, Siswa memilih
// Rombel); ruang kelas dibuat terakhir dari Beranda karena butuh semuanya.
const DATA: NavItem[] = [
  { title: "Tahun Ajaran", url: "/tahun-ajaran", icon: CalendarRange },
  { title: "Mata Pelajaran", url: "/mapel", icon: Library },
  { title: "Guru", url: "/data-guru", icon: Users },
  { title: "Rombel", url: "/rombel", icon: School },
  { title: "Siswa", url: "/siswa", icon: GraduationCap },
]

const LABEL_KELAS: Record<string, string> = {
  SISWA: "Terdaftar",
  GURU: "Mengajar",
  ADMIN: "Semua kelas",
  KEPSEK: "Semua kelas",
}

export function ClassroomSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  const { user } = useAuth()
  const { url, props: pageProps } = usePage<{ kelasSaya?: KelasSaya[] }>()
  const pathname = url.split("?")[0]
  const { isMobile, setOpenMobile } = useSidebar()
  const [kelasBuka, setKelasBuka] = React.useState(true)
  const kelasSaya = pageProps.kelasSaya ?? []
  const role = user?.role ?? "SISWA"
  const lihatData = role === "ADMIN" || role === "KEPSEK"
  // Mapel + rombel yang muncul lebih dari sekali (diajar guru berbeda) perlu nama guru supaya bisa dibedakan.
  const kembar = new Map<string, number>()
  for (const k of kelasSaya) kembar.set(`${k.mapel}|${k.rombel}`, (kembar.get(`${k.mapel}|${k.rombel}`) ?? 0) + 1)

  /** Baris kedua kartu kelas. Siswa hanya melihat kelas rombelnya sendiri, jadi yang membedakan adalah gurunya. */
  function keterangan(kelas: KelasSaya): string {
    if (role === "SISWA") return kelas.guru
    return (kembar.get(`${kelas.mapel}|${kelas.rombel}`) ?? 0) > 1 ? `${kelas.rombel} · ${kelas.guru}` : kelas.rombel
  }

  function tutupMobile() {
    if (isMobile) setOpenMobile(false)
  }

  function isActive(item: NavItem): boolean {
    const arsip = new URLSearchParams(url.split("?")[1] ?? "").get("arsip") === "1"
    if (item === ARSIP_ITEM) return pathname === "/kelas" && arsip
    if (item.persis) return pathname === item.url && !arsip
    return pathname.startsWith(item.url)
  }

  function renderItem(item: NavItem) {
    return (
      <SidebarMenuItem key={item.title}>
        <SidebarMenuButton isActive={isActive(item)} onClick={tutupMobile} render={<Link href={item.url} />}>
          <item.icon />
          {item.title}
        </SidebarMenuButton>
      </SidebarMenuItem>
    )
  }

  return (
    <Sidebar {...props}>
      <SidebarHeader>
        <div className="flex items-center gap-2 px-2 py-1.5">
          <img src="/logo_smk.png" alt="Logo SMK Budi Mulia" className="size-7 shrink-0 object-contain" />
          <div className="flex flex-col leading-none">
            <span className="font-medium">Ujian</span>
            <span className="text-xs text-muted-foreground">SMK Budi Mulia</span>
          </div>
        </div>
      </SidebarHeader>
      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupContent>
            <SidebarMenu>{UTAMA.map(renderItem)}</SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>

        <SidebarSeparator />

        <SidebarGroup>
          <SidebarGroupContent>
            <SidebarMenu>
              <SidebarMenuItem>
                <SidebarMenuButton onClick={() => setKelasBuka((v) => !v)} aria-expanded={kelasBuka}>
                  <GraduationCap />
                  <span className="font-medium">{LABEL_KELAS[role]}</span>
                  <ChevronDown className={cn("ml-auto transition-transform", !kelasBuka && "-rotate-90")} />
                </SidebarMenuButton>
              </SidebarMenuItem>
              {kelasBuka && renderItem(UJIAN_ITEM)}
              {kelasBuka &&
                kelasSaya.map((kelas) => {
                  const dasar = `/kelas/${kelas.id}`
                  const aktif = pathname === dasar || pathname.startsWith(`${dasar}/`)
                  return (
                    <SidebarMenuItem key={kelas.id}>
                      <SidebarMenuButton
                        isActive={aktif}
                        tooltip={`${kelas.mapel} · ${kelas.rombel} · ${kelas.guru}`}
                        className="h-auto py-1.5"
                        onClick={tutupMobile}
                        render={<Link href={dasar} />}
                      >
                        <span
                          className={cn(
                            "flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-medium",
                            warnaKelas(kelas.id),
                          )}
                        >
                          {kelas.mapel.charAt(0).toUpperCase()}
                        </span>
                        <div className="flex min-w-0 flex-col leading-tight">
                          <span className="truncate text-sm">{kelas.mapel}</span>
                          <span className="truncate text-xs text-muted-foreground" title={`${kelas.rombel} · ${kelas.guru}`}>
                            {keterangan(kelas)}
                          </span>
                        </div>
                      </SidebarMenuButton>
                    </SidebarMenuItem>
                  )
                })}
              {kelasBuka && kelasSaya.length === 0 && (
                <SidebarMenuItem>
                  <p className="px-2 py-1.5 text-xs text-muted-foreground group-data-[collapsible=icon]:hidden">
                    Belum ada ruang kelas
                  </p>
                </SidebarMenuItem>
              )}
              {renderItem(ARSIP_ITEM)}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>

        {lihatData && (
          <>
            <SidebarSeparator />
            <SidebarGroup>
              <SidebarGroupLabel>Data</SidebarGroupLabel>
              <SidebarGroupContent>
                <SidebarMenu>{DATA.map(renderItem)}</SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>
          </>
        )}

        <SidebarSeparator />
        <SidebarGroup>
          <SidebarGroupContent>
            <SidebarMenu>{renderItem(PROFIL_ITEM)}</SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>
      <SidebarRail />
    </Sidebar>
  )
}
