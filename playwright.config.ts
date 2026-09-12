import { defineConfig, devices } from "@playwright/test";
import { join } from "node:path";

const baseURL = (process.env.WP_BASE_URL ?? "http://localhost/business").replace(
	/\/+$/,
	""
);
const baseURLWithSlash = `${baseURL}/`;
const storageStatePath = join(
	process.cwd(),
	"artifacts",
	".auth",
	"storage-state.json"
);

export default defineConfig({
	testDir: "./tests",
	globalSetup: "./tests/global-setup.ts",
	outputDir: "./artifacts/test-results",
	fullyParallel: true,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: process.env.CI ? 1 : undefined,
	reporter: [
		["list"],
		["html", { outputFolder: "artifacts/playwright-report", open: "never" }],
	],
	use: {
		baseURL: baseURLWithSlash,
		storageState: storageStatePath,
		trace: "retain-on-failure",
		screenshot: "only-on-failure",
		video: "retain-on-failure",
		viewport: { width: 1280, height: 820 },
	},
	projects: [
		{
			name: "chromium",
			use: { ...devices["Desktop Chrome"] },
		},
	],
});