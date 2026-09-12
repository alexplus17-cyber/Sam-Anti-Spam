import { mkdir, writeFile } from "node:fs/promises";
import { join } from "node:path";

const rawBaseURL =
	process.env.WP_BASE_URL ?? "http://localhost/business";
const baseURL = rawBaseURL.replace(/\/+$/, "");
const loginURL = `${baseURL}/login/`;
const storageStatePath = join(
	process.cwd(),
	"artifacts",
	".auth",
	"storage-state.json"
);

type RawCookie = {
	name: string;
	value: string;
	expires: number;
	path: string;
	domain: string;
	httpOnly: boolean;
	secure: boolean;
	sameSite: "None" | "Lax" | "Strict";
};

function parseSetCookie(header: string): RawCookie {
	const [pair, ...attrs] = header.split(";").map((part) => part.trim());
	const eq = pair.indexOf("=");
	const name = pair.slice(0, eq);
	let value = pair.slice(eq + 1);
	if (value.startsWith('"') && value.endsWith('"')) {
		value = value.slice(1, -1);
	}

	const cookie: RawCookie = {
		name,
		value,
		expires: -1,
		path: "/",
		domain: "",
		httpOnly: false,
		secure: false,
		sameSite: "Lax",
	};

	for (const attr of attrs) {
		const [key, ...rest] = attr.split("=");
		const val = rest.join("=");
		switch (key.toLowerCase()) {
			case "expires":
				cookie.expires = Math.floor(Date.parse(val) / 1000);
				break;
			case "path":
				cookie.path = val;
				break;
			case "domain":
				cookie.domain = val.replace(/^\./, "");
				break;
			case "httponly":
				cookie.httpOnly = true;
				break;
			case "secure":
				cookie.secure = true;
				break;
			case "samesite":
				cookie.sameSite = val as RawCookie["sameSite"];
				break;
		}
	}
	return cookie;
}

function cookieHeader(jar: RawCookie[]): string {
	return jar.map((c) => `${c.name}=${c.value}`).join("; ");
}

export default async function globalSetup() {
	const username = process.env.WP_USERNAME;
	const password = process.env.WP_PASSWORD;

	if (!username || !password) {
		console.error(
			"\nWP_USERNAME and WP_PASSWORD must be set to run the e2e suite."
		);
		throw new Error("Missing WP_USERNAME/WP_PASSWORD environment variables.");
	}

	// 1. Load the custom login page to obtain the theme's login nonce.
	const pageResponse = await fetch(loginURL, { redirect: "manual" });
	const html = await pageResponse.text();
	const nonceMatch = html.match(
		/name="nonce"\s+value="([a-f0-9]+)"/i
	);
	if (!nonceMatch) {
		throw new Error(
			`Could not find the login nonce on ${loginURL}. Is the Sam Anti Spam e2e runner targeting the right site?`
		);
	}
	const nonce = nonceMatch[1];

	// 2. Submit the theme's login form (same flow as page-login.php).
	const authResponse = await fetch(loginURL, {
		method: "POST",
		redirect: "manual",
		headers: {
			"content-type": "application/x-www-form-urlencoded",
		},
		body: new URLSearchParams({
			action: "bs_login_user",
			nonce,
			email: username,
			password,
			remember: "1",
			redirect_to: `${baseURL}/wp-admin/`,
		}),
	});

	// Keep every WP auth cookie. wp-admin validates against the "auth" cookie
	// (`wordpress_<hash>`), while the front end uses `wordpress_logged_in_<hash>`;
	// dropping the former produced an infinite wp-admin <-> /login/ redirect loop.
	const authCookies = (authResponse.headers.getSetCookie?.() ?? [])
		.map(parseSetCookie)
		.filter((cookie) => cookie.name.startsWith("wordpress"));

	if (authCookies.length === 0) {
		const body = await authResponse.text();
		const errorMatch = body.match(
			/id="login-errors"[^>]*>\s*([^<]+)/i
		);
		const error = errorMatch ? errorMatch[1].trim() : "unknown error";
		throw new Error(
			`Login failed for "${username}" (no auth cookie issued; 2FA/rate-limit or bad credentials). Server says: ${error}`
		);
	}

	if (authResponse.status >= 400) {
		throw new Error(
			`Login POST returned HTTP ${authResponse.status}.`
		);
	}

	const domain = new URL(loginURL).hostname;
	for (const cookie of authCookies) {
		cookie.domain = domain;
	}

	await mkdir(join(storageStatePath, ".."), { recursive: true });
	await writeFile(
		storageStatePath,
		JSON.stringify(
			{
				cookies: authCookies.map((cookie) => ({
					name: cookie.name,
					value: cookie.value,
					domain: cookie.domain,
					path: cookie.path,
					expires: cookie.expires,
					httpOnly: cookie.httpOnly,
					secure: cookie.secure,
					sameSite: cookie.sameSite,
				})),
				origins: [],
			},
			null,
			2
		)
	);

	console.log(
		`Authenticated as "${username}" and stored auth state at ${storageStatePath}`
	);
}