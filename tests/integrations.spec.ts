import { expect, type Page } from "@playwright/test";
import { test } from "./fixtures";
import { siteURL } from "./util";

const SAMPLE_POST = siteURL("/insurance-agency-for-sale/");
const SETTINGS_URL = siteURL("/wp-admin/options-general.php?page=sam-anti-spam");

async function markVerified(page: Page) {
	// The plugin's own front-end script issues this cookie; a real visitor has
	// it before submitting any form.
	await page.context().addCookies([
		{ name: "sam_verified", value: "true", url: siteURL("/") },
	]);
}

async function saveSettings(page: Page) {
	await page
		.locator('button[type="submit"], input[type="submit"]')
		.first()
		.click();
	await page.waitForURL(/options-general\.php\?page=sam-anti-spam/);
	await expect(page.locator(".sam-anti-spam-wrap")).toBeVisible();
}

test.describe.configure({ mode: "serial" });

test.describe("Sam Anti Spam — Integration behaviour", () => {
	test("comment with a filled honeypot is blocked (marked spam, never published)", async ({
		page,
	}) => {
		test.setTimeout(120_000);
		const token = `sam-e2e-comment-${Date.now()}`;

		await page.goto(SAMPLE_POST);
		await markVerified(page);

		const commentForm = page.locator("#commentform");
		await expect(commentForm).toBeVisible();
		await commentForm
			.locator("#comment")
			.fill(`Test comment that must be caught as spam ${token}`);
		// The honeypot is display:none on purpose; fill()/check() won't touch it,
		// so set its value in the DOM directly.
		await commentForm.locator("#sam_hp_field").evaluate((el) => {
			(el as HTMLInputElement).value = "https://spam.example";
		});

		await Promise.all([
			page.waitForNavigation({ waitUntil: "domcontentloaded" }),
			commentForm.locator("#submit").click(),
		]);

		await expect(page.locator("#comments").getByText(token)).toHaveCount(0);

		// The blocked comment must sit in the admin spam queue, searchable by its text.
		await page.goto(
			siteURL(`/wp-admin/edit-comments.php?comment_status=spam&s=${token}`)
		);
		const spamRow = page
			.locator("#the-comment-list tr")
			.filter({ hasText: token });
		await expect(spamRow).toBeVisible();

		// Cleanup: permanently delete the spam comment we just created. Row
		// actions only appear on hover. Deleting can take a while on a local
		// box, so re-open the spam queue until the comment is gone.
		await spamRow.hover();
		await spamRow.locator('a[href*="action=delete"]').click();

		const spamQueue = () =>
			page
				.locator("#the-comment-list tr")
				.filter({ hasText: token });
		for (let attempt = 0; attempt < 6; attempt++) {
			await page.goto(
				siteURL(
					`/wp-admin/edit-comments.php?comment_status=spam&s=${token}`
				)
			);
			if ((await spamQueue().count()) === 0) {
				break;
			}
			await page.waitForTimeout(5_000);
		}
		await expect(spamQueue()).toHaveCount(0, { timeout: 30_000 });
	});

	test("support-ticket submission is rejected while the toggle is on, then restored", async ({
		page,
	}) => {
		test.setTimeout(120_000);
		await markVerified(page);

		await page.goto(`${SETTINGS_URL}&tab=integrations`);
		const toggle = page.locator("#enable_support_tickets");
		await expect(toggle).toBeVisible();
		const wasEnabled = await toggle.isChecked();

		try {
			if (!wasEnabled) {
				await toggle.check();
				await saveSettings(page);
			}
			await expect(
				page.locator(".sam-card__head .sam-pill.is-primary")
			).toContainText("/5 protected");

			const response = await page.request.post(
				siteURL("/wp-admin/admin-ajax.php"),
				{
					form: {
						action: "bs_submit_ticket",
						description: "Free bitcoin giveaway ticket",
						sam_hp_field: "https://spam.example",
					},
				}
			);
			expect(response.status()).toBe(200);
			const body = await response.json();
			expect(
				body.success,
				`ticket with a filled honeypot must be rejected: ${JSON.stringify(body)}`
			).toBe(false);
		} finally {
			// Restore the original toggle state via the settings UI.
			await page.goto(`${SETTINGS_URL}&tab=integrations`);
			const restored = page.locator("#enable_support_tickets");
			if ((await restored.isChecked()) !== wasEnabled) {
				await (wasEnabled ? restored.check() : restored.uncheck());
				await saveSettings(page);
			}
			await expect(restored).toBeChecked({
				checked: wasEnabled,
			});
		}
	});
});