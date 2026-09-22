/** Bentuk data dari API. Mengikuti docs/03-api-spec.md. */

export type AdminRole = "admin" | "operator";

export type AdminProfile = {
  id: number;
  name: string;
  email: string;
  role: AdminRole;
};

export type Student = {
  id: number;
  nim: string;
  name: string;
  faculty: string;
  study_program: string;
  group_name: string | null;
  gender: "L" | "P" | null;
  birth_date: string | null;
  phone: string | null;
  email: string | null;
  /** Hanya ada bila daftar difilter terhadap satu sesi presensi. */
  is_present?: boolean;
};

export type ImportRowError = {
  row: number;
  column: string;
  message: string;
};

export type ImportBatch = {
  batch_id: number;
  original_filename: string;
  status: "processing" | "done" | "failed";
  total_rows: number;
  inserted_count: number;
  updated_count: number;
  failed_count: number;
  imported_by: string | null;
  created_at: string | null;
  errors?: ImportRowError[];
};

/** Hasil pencarian NIM oleh mahasiswa baru (endpoint publik). */
export type StudentLookup = {
  nim: string;
  name: string;
  faculty: string;
  study_program: string;
  group_name: string | null;
  attendance: {
    session_name: string;
    status: "hadir" | "belum";
    scanned_at: string | null;
  }[];
  downloads: {
    nametag_url: string;
    qr_url: string;
  };
};

// ── Presensi (Fase 4) ────────────────────────────────────────────────

export type ScanSession = {
  id: number;
  name: string;
  starts_at: string | null;
  ends_at: string | null;
  is_active: boolean;
  is_open_now: boolean;
};

export type ScanContext = {
  operator: { name: string; role: AdminRole };
  sessions: ScanSession[];
  server_time: string;
};

export type ScanResult = {
  result: "recorded" | "duplicate" | "unknown_token" | "session_closed" | "gagal_kirim";
  message: string;
  student: {
    name: string;
    nim: string;
    study_program: string;
    group_name: string | null;
  } | null;
  scanned_at: string | null;
};

export type RecentScan = {
  name: string | null;
  nim: string | null;
  scanned_at: string | null;
  method: "qr" | "manual";
};

export type AttendanceSessionAdmin = ScanSession & {
  event_date: string | null;
  attendances_count: number;
};

export type DashboardStats = {
  total_students: number;
  active_session: { id: number; name: string } | null;
  present_count: number;
  absent_count: number;
  by_faculty: { faculty: string; total: number; present: number }[];
  scans_per_10min: { at: string; count: number }[];
};
