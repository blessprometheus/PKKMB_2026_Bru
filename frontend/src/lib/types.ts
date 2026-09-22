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
