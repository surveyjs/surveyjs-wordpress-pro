const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");
const { test, expect } = require("@playwright/test");

const WP_CONTAINER = process.env.WP_CONTAINER;

function fixture(...args) {
  const output = execFileSync("docker", ["exec", "-i", WP_CONTAINER, "php", "--", ...args.map(String)], {
    input: fs.readFileSync(path.join(__dirname, "fixtures/analytics.php")),
    stdio: ["pipe", "pipe", "ignore"]
  });
  return JSON.parse(output.toString().trim().split("\n").pop());
}

test.describe("analytics on the results page", () => {
  test.skip(!WP_CONTAINER, "Set WP_CONTAINER to the WordPress container name to create the test form");

  let formId = 0;

  test.beforeAll(() => {
    formId = fixture("create").id;
  });

  test.afterAll(() => {
    fixture("cleanup", formId);
  });

  test("Charts shows charts of submitted entries", async ({ page }) => {
    await page.goto(`/wp-admin/admin.php?page=surveyjs_results&id=${formId}`);
    await page.locator(".wp-sjs-results__views").getByRole("link", { name: "Charts" }).click();
    await expect(page).toHaveURL(/view=chart/);
    const view = page.locator("#wp-sjs-pro-analytics");
    await expect(view.locator(".sa-question__title", { hasText: "Favorite color" })).toBeVisible();
    await expect(view.locator("canvas").first()).toBeVisible();
    await expect(view.locator(".wp-sjs-pro-analytics__progress")).toBeHidden();
  });


  test("the entries endpoint rejects a wrong nonce", async ({ page }) => {
    await page.goto(`/wp-admin/admin.php?page=surveyjs_results&id=${formId}&view=chart`);
    const status = await page.evaluate(async id => {
      const response = await fetch(window.ajaxurl, {
        method: "POST",
        credentials: "same-origin",
        body: new URLSearchParams({ action: "surveyjs_pro_analytics_entries", form: id, _ajax_nonce: "wrong" })
      });
      return response.status;
    }, formId);
    expect(status).toBe(403);
  });
});
