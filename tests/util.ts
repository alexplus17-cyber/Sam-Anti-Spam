export const BASE = (
	process.env.WP_BASE_URL ?? "http://localhost/business"
).replace(/\/+$/, "");

/** Build an absolute URL for the local site (Playwright's baseURL handling is unreliable here). */
export const siteURL = (path: string): string => `${BASE}${path}`;