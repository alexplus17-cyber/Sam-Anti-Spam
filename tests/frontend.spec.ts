import { expect } from "@playwright/test";
import { test } from "./fixtures";
import { siteURL } from "./util";

const SAMPLE_POST = siteURL("/insurance-agency-for-sale/");

test.describe("Sam Anti Spam — Frontend", () => {
	test("front page loads and injects the client-side check script", async ({
		page,
	}) => {
		const response = await page.goto(siteURL("/"));
		expect(response?.status()).toBe(200);

		const content = await page.content();
		expect(content).toContain("sam_verified");
		expect(content).toContain("sam_load_time");
		expect(content).toContain("sam_check_spam");
	});

	test("the spam-check AJAX endpoint responds to a legit request", async ({
		page,
	}) => {
		await page.goto(siteURL("/"));

		const nonce = await page.evaluate(() => {
			const text = document.body.innerHTML;
			const match = text.match(/action=sam_check_spam&security=(\w+)/);
			return match ? match[1] : null;
		});
		expect(nonce, "sam_check_spam nonce should be injected").not.toBeNull();

		// Real visitors get this cookie from the plugin's front-end script; the
		// request must NOT override the Cookie header, or the browser's WP auth
		// cookies are dropped and the logged-in nonce no longer validates.
		await page.context().addCookies([
			{ name: "sam_verified", value: "true", url: siteURL("/") },
		]);

		const response = await page.request.post(
			siteURL("/wp-admin/admin-ajax.php"),
			{
				form: {
					action: "sam_check_spam",
					security: String(nonce),
					time_taken: "30",
				},
			}
		);
		expect(response.status()).toBe(200);

		const body = await response.json();
		expect(
			body.success,
			`AJAX spam check should accept clean requests (body: ${JSON.stringify(body)})`
		).toBe(true);
	});

	test("post pages with open comments render the honeypot", async ({
		page,
	}) => {
		const response = await page.goto(SAMPLE_POST);
		expect(response?.status()).toBe(200);

		const form = page.locator("#commentform, form.comment-form");
		if (await form.count()) {
			const honeypot = form.locator("#sam_hp_field");
			// The honeypot is a decoy input, so it must exist in the DOM,
			// be pointed away from humans (tabindex/autocomplete) and hidden.
			await expect(honeypot).toHaveCount(1);
			await expect(honeypot).toHaveAttribute("tabindex", "-1");
			await expect(honeypot).toHaveAttribute("autocomplete", "off");
			await expect(honeypot).not.toBeVisible();
		} else {
			await test.info().attach("comment-form", {
				body: "Theme does not render a standard WP comment form on this post.",
			});
		}
	});
});