/**
 * Klien API tunggal untuk seluruh frontend.
 *
 * Kontrak response: { success, data|message } — docs/03-api-spec.md §1.1.
 * Autentikasi: cookie sesi Sanctum (HttpOnly), bukan token di localStorage.
 * Karena itu semua permintaan memakai `credentials: "include"`, dan tidak ada
 * satu pun token yang pernah disentuh JavaScript.
 */

export type ApiMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type FieldErrors = Record<string, string[]>;

/** Error yang membawa pesan Bahasa Indonesia dari backend, siap ditampilkan. */
export class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly errors?: FieldErrors,
    readonly retryAfter?: number,
  ) {
    super(message);
    this.name = "ApiError";
  }

  /** Pesan untuk satu field, dipakai di bawah input form. */
  fieldError(field: string): string | undefined {
    return this.errors?.[field]?.[0];
  }
}

function readCookie(name: string): string | undefined {
  return document.cookie
    .split("; ")
    .find((row) => row.startsWith(`${name}=`))
    ?.split("=")
    .slice(1)
    .join("=");
}

/**
 * Sanctum menaruh token CSRF di cookie XSRF-TOKEN, dan menunggunya kembali di
 * header X-XSRF-TOKEN. Nilainya ter-URL-encode di cookie, jadi WAJIB
 * di-decode sebelum dikirim — kalau tidak, semua POST ditolak 419 dan
 * penyebabnya sangat tidak jelas saat dikejar deadline.
 */
async function ensureCsrfCookie(): Promise<void> {
  if (readCookie("XSRF-TOKEN")) return;

  await fetch("/sanctum/csrf-cookie", { credentials: "include" });
}

type RequestOptions = {
  method?: string;
  body?: unknown;
  signal?: AbortSignal;
};

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const method = options.method ?? "GET";
  const isWrite = method !== "GET" && method !== "HEAD";

  if (isWrite) await ensureCsrfCookie();

  const headers: Record<string, string> = { Accept: "application/json" };

  if (isWrite) {
    const token = readCookie("XSRF-TOKEN");
    if (token) headers["X-XSRF-TOKEN"] = decodeURIComponent(token);
  }

  let body: BodyInit | undefined;

  if (options.body instanceof FormData) {
    // JANGAN set Content-Type untuk FormData — browser harus menambahkan
    // boundary-nya sendiri, dan menyetelnya manual justru merusak unggahan.
    body = options.body;
  } else if (options.body !== undefined) {
    headers["Content-Type"] = "application/json";
    body = JSON.stringify(options.body);
  }

  const response = await fetch(path, {
    method,
    headers,
    body,
    credentials: "include",
    signal: options.signal,
  });

  const payload = await response.json().catch(() => null);

  if (!response.ok || payload?.success === false) {
    const retryAfter = Number(response.headers.get("Retry-After")) || undefined;

    throw new ApiError(
      payload?.message ?? "Terjadi kesalahan pada server. Silakan coba lagi.",
      response.status,
      payload?.errors,
      retryAfter,
    );
  }

  return payload?.data as T;
}

/** Varian yang juga mengembalikan meta paginasi. */
async function requestPaginated<T>(path: string): Promise<{ data: T[]; meta: ApiMeta }> {
  const response = await fetch(path, {
    headers: { Accept: "application/json" },
    credentials: "include",
  });

  const payload = await response.json().catch(() => null);

  if (!response.ok || payload?.success === false) {
    throw new ApiError(
      payload?.message ?? "Terjadi kesalahan pada server. Silakan coba lagi.",
      response.status,
      payload?.errors,
    );
  }

  return { data: payload.data as T[], meta: payload.meta as ApiMeta };
}

export const api = {
  get: <T>(path: string, signal?: AbortSignal) => request<T>(path, { signal }),
  paginated: requestPaginated,
  post: <T>(path: string, body?: unknown) => request<T>(path, { method: "POST", body }),
  put: <T>(path: string, body?: unknown) => request<T>(path, { method: "PUT", body }),
  delete: <T>(path: string) => request<T>(path, { method: "DELETE" }),
};
