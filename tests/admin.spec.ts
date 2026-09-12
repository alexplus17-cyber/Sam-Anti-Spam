import { expect } from "@playwright/test";
import { test } from "./fixtures";
import { siteURL } from "./util";

const SETTINGS_URL = siteURL("/wp-admin/options-general.php?page=sam-anti-spam");
const SETUP_URL = siteURL("/wp-admin/options-general.php?page=sam-anti-spam-setup");

test.describe("Sam Anti Spam — WP Admin", () => {
	test("settings page renders without fatal errors", async ({ page }) => {
		await page.goto(SETTINGS_URL);

		await expect(
			page.locator("h1.sam-head__title").getByText("Sam Anti Spam")
		).toBeVisible();
		await expect(page.locator(".sam-anti-spam-wrap")).toBeVisible();
		await expect(page.locator("section.sam-hero")).toBeVisible();
	});

	test("general tab shows the Cloud Connection UI", async ({ page }) => {
		await page.goto(SETTINGS_URL);

		const status = page.locator("#sam-api-status");
		await expect(status).toBeVisible();

		const connectButton = page.locator("#sam-connect-btn");
		if (await connectButton.isVisible()) {
			await expect(status.locator(".sam-pill")).toContainText(
				"Not Connected"
			);
		} else {
			await expect(status.locator(".sam-pill")).toContainText(
				"Cloud Connected"
			);
		}
	});

	test("every settings tab loads its own content", async ({ page }) => {
		test.setTimeout(120_000);
		const tabs = [
			{ id: "general", title: "General" },
			{ id: "integrations", title: "Integrations" },
			{ id: "traffic", title: "Traffic Control" },
			{ id: "lists", title: "Whitelist & Blacklist" },
			{ id: "log", title: "Spam Log" },
		];

		for (const tab of tabs) {
			await page.goto(`${SETTINGS_URL}&tab=${tab.id}`, {
				waitUntil: "domcontentloaded",
			});
			await expect(page.locator(".sam-anti-spam-wrap")).toBeVisible();
			await expect(
				page.locator("h2.sam-card__title").first()
			).toHaveText(tab.title);
		}
	});

	test("integrations tab lists all five protections with a toggle each", async ({
		page,
	}) => {
		const integrations = [
			{ id: "enable_comments", label: "Protect Comments" },
			{ id: "enable_registrations", label: "Protect Registrations" },
			{ id: "enable_cf7", label: "Protect Contact Form 7" },
			{ id: "enable_woo", label: "Protect WooCommerce" },
			{ id: "enable_support_tickets", label: "Protect Support Tickets" },
		];

		await page.goto(`${SETTINGS_URL}&tab=integrations`);

		for (const integration of integrations) {
			const toggle = page.locator(`#${integration.id}`);
			await expect(toggle).toBeVisible();
			await expect(toggle).toHaveAttribute("type", "checkbox");
			const row = page
				.locator("tr")
				.filter({ has: toggle });
			await expect(row.locator("th")).toHaveText(integration.label);
		}
	});

	test("integrations protected counter matches the checked toggles", async ({
		page,
	}) => {
		const ids = [
			"enable_comments",
			"enable_registrations",
			"enable_cf7",
			"enable_woo",
			"enable_support_tickets",
		];

		await page.goto(`${SETTINGS_URL}&tab=integrations`);

		const checkedCount = await page.evaluate(
			(ids) =>
				ids.filter((id) => {
					const input = document.getElementById(id) as unknown as
						| HTMLInputElement
						| null;
					return input?.checked ?? false;
				}).length,
			ids
		);
		const expected = `${checkedCount}/5 protected`;

		await expect(
			page.locator(".sam-card__head .sam-pill.is-primary")
		).toContainText(expected);
		const protectionStatus = page
			.locator(".sam-hero-card")
			.filter({ hasText: "Protection Status" });
		await expect(
			protectionStatus.locator(".sam-hero-card__value")
		).toContainText(`${checkedCount}/5`);
	});

	test("settings form can be submitted without errors", async ({
		page,
	}) => {
		await page.goto(`${SETTINGS_URL}&tab=lists`);

		const textarea = page.locator("#whitelist_emails");
		await expect(textarea).toBeVisible();
		await page
			.locator('button[type="submit"], input[type="submit"]')
			.first()
			.click();

		// Redirect back to the page after saving.
		await page.waitForURL(/options-general\.php\?page=sam-anti-spam/);
		await expect(page.locator(".sam-anti-spam-wrap")).toBeVisible();
	});

	test("setup wizard page renders without fatal errors", async ({
		page,
	}) => {
		await page.goto(SETUP_URL);
		await expect(page).toHaveURL(/sam-anti-spam-setup/);
		await expect(
			page.locator(".wrap, .sam-setup-wrap, #wpbody-content").first()
		).toBeVisible();
	});

	test("plugin menu items are registered in Settings admin menu", async ({
		page,
	}) => {
		await page.goto(siteURL("/wp-admin/options-general.php"));

		const menu = page.locator("#menu-settings li a");
		await expect(menu).toContainText(["Sam Anti Spam", "Sam Setup"]);
	});
});