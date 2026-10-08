export const API_URL =
  process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api/v1";

const TOKEN_KEY = "jobpilot_token";

export function getToken(): string | null {
  if (typeof window === "undefined") return null;
  return window.localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string | null): void {
  if (typeof window === "undefined") return;
  if (token === null) window.localStorage.removeItem(TOKEN_KEY);
  else window.localStorage.setItem(TOKEN_KEY, token);
}

export type ApiErrors = Record<string, string[]>;

export class ApiError extends Error {
  constructor(
    public status: number,
    public code: string,
    message: string,
    public errors: ApiErrors | null = null,
  ) {
    super(message);
  }
}

type RequestOptions = {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  body?: unknown;
  formData?: FormData;
};

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const headers: Record<string, string> = { Accept: "application/json" };
  const token = getToken();
  if (token) headers.Authorization = `Bearer ${token}`;

  let payload: BodyInit | undefined;
  if (options.formData) {
    payload = options.formData;
  } else if (options.body !== undefined) {
    headers["Content-Type"] = "application/json";
    payload = JSON.stringify(options.body);
  }

  const response = await fetch(`${API_URL}${path}`, {
    method: options.method ?? "GET",
    headers,
    body: payload,
  });

  if (response.status === 204) return undefined as T;

  const json = await response.json().catch(() => null);

  if (!response.ok) {
    if (response.status === 401) setToken(null);
    throw new ApiError(
      response.status,
      json?.code ?? "HTTP_ERROR",
      json?.message ?? `Request failed (${response.status})`,
      json?.errors ?? null,
    );
  }

  return (json?.data !== undefined ? json.data : json) as T;
}

export const api = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, body?: unknown) => request<T>(path, { method: "POST", body }),
  put: <T>(path: string, body?: unknown) => request<T>(path, { method: "PUT", body }),
  del: <T>(path: string) => request<T>(path, { method: "DELETE" }),
  upload: <T>(path: string, formData: FormData) => request<T>(path, { method: "POST", formData }),
  download: async (path: string, filename: string): Promise<void> => {
    const headers: Record<string, string> = {};
    const token = getToken();
    if (token) headers.Authorization = `Bearer ${token}`;
    const response = await fetch(`${API_URL}${path}`, { headers });
    if (!response.ok) {
      if (response.status === 401) setToken(null);
      throw new ApiError(response.status, "HTTP_ERROR", `Download failed (${response.status})`);
    }
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement("a");
    anchor.href = url;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    // Revoking synchronously can abort the download in Firefox/Safari.
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  },
};
