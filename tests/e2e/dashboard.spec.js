const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");
const { test, expect } = require("@playwright/test");

const WP_CONTAINER = process.env.WP_CONTAINER;
const DASHBOARD_URL = "/wp-admin/admin.php?page=surveyjs-pro-dashboard";

function fixture(...args) {
  const output = execFileSync("docker", ["exec", "-i", WP_CONTAINER, "php", "--", ...args.map(String)], {
    input: fs.readFileSync(path.join(__dirname, "fixtures/dashboard.php")),
    stdio: ["pipe", "pipe", "ignore"]
  });
  return JSON.parse(output.toString().trim().split("\n").pop());
}

test.describe("Pro dashboard", () => {
  test.skip(!WP_CONTAINER, "Set WP_CONTAINER to the WordPress container name to create the test form");

  let created = null;

  test.beforeAll(() => {
    created = fixture("create");
  });

  test.afterAll(() => {
    if (created) {
      fixture("cleanup", created.form);
    }
  });

  test("shows counts, charts and latest entries with links to the entry", async ({ page }) => {
    await page.goto("/wp-admin/admin.php?page=surveyjs-main-menu");
    await page.locator("#toplevel_page_surveyjs-main-menu").getByRole("link", { name: "Dashboard", exact: true }).click();
    await expect(page).toHaveURL(/page=surveyjs-pro-dashboard/);
    await expect(page.getByRole("heading", { name: "Dashboard", level: 1 })).toBeVisible();
    await expect(page.locator("#wp-sjs-pro-dashboard-timeline")).toBeVisible();
    await expect(page.locator("#wp-sjs-pro-dashboard-forms")).toBeVisible();
    expect(await page.evaluate(() => typeof window.Chart)).toBe("function");

    const formRow = page.locator(".wp-sjs-pro-dashboard__forms tbody tr").filter({ has: page.locator(`a[href$="page=surveyjs_editor&id=${created.form}"]`) });
    await expect(formRow.locator("td")).toHaveText([/E2E Dashboard/, "2", "2", "2", /.+/]);

    const latest = page.locator("table").first().locator("tbody tr");
    const newest = created.entries[1];
    await expect(latest.first()).toContainText(`#${newest}`);

    await page.getByRole("link", { name: `#${newest}`, exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`page=surveyjs_results&id=${created.form}&entry=${newest}`));
    await expect(page.locator("#wp-sjs-result-popup").getByLabel("What is your name?")).toHaveValue("Bob");
    await expect(page.locator("#the-list tr")).toHaveCount(1);
  });

  test("switches the period", async ({ page }) => {
    await page.goto(DASHBOARD_URL);
    await page.getByRole("link", { name: "Last 12 months" }).click();
    await expect(page).toHaveURL(/range=12m/);
    await expect(page.getByRole("link", { name: "Last 12 months" })).toHaveAttribute("aria-current", "page");
    await expect(page.locator(".wp-sjs-pro-dashboard__forms thead")).toContainText("Last 12 months");
  });
});
