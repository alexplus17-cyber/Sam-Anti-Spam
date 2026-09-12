import { test as base, expect } from "@playwright/test";

export const test = base.extend({
	page: async ({ page }, use) => {
		const errors: string[] = [];

		page.on("pageerror", (error) => {
			errors.push(`Uncaught exception: ${error.message}`);
		});
		page.on("console", (msg) => {
			if (msg.type() === "error") {
				const text = msg.text();
				// Ignore benign noise that is not plugin-related.
				if (
					/favicon/i.test(text) ||
					/Failed to load resource/i.test(text) ||
					/net::ERR_/i.test(text)
				) {
					return;
				}
				errors.push(`Console error: ${text}`);
			}
		});

		await use(page);

		expect(
			errors,
			`The page logged unexpected JavaScript errors:\n${errors.join("\n")}`
		).toEqual([]);
	},
});