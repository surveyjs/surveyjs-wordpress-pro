const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");
const { test, expect } = require("@playwright/test");

const WP_CONTAINER = process.env.WP_CONTAINER;
const LIST_URL = "/wp-admin/admin.php?page=surveyjs-main-menu";

function contract(...args) {
  const output = execFileSync("docker", ["exec", "-i", WP_CONTAINER, "php", "--", ...args.map(String)], {
    input: fs.readFileSync(path.join(__dirname, "fixtures/contract.php")),
    stdio: ["pipe", "pipe", "ignore"]
  });
  return JSON.parse(output.toString().trim().split("\n").pop());
}

async function save(page) {
  const saved = page.waitForResponse(r => r.url().includes("action=SurveyJS_SaveSurvey"));
  await page.getByRole("button", { name: "Save", exact: true }).click();
  await saved;
  await expect(page.locator("#wp-sjs-editor-notice")).toContainText("Form saved");
}

async function toggleFormProperty(page, search, label) {
  const creator = page.locator(".svc-creator");
  await creator.getByPlaceholder(/Type to search/).fill(search);
  await creator.getByText(label, { exact: true }).click();
}

test.describe("Survey Creator in the form editor", () => {
  test.skip(!WP_CONTAINER, "Set WP_CONTAINER to the WordPress container name to read and delete the test form");

  let formId = 0;

  test.afterAll(() => {
    if (formId) contract("cleanup", formId);
  });

  test("replaces the JSON tab and keeps GDPR and Save & Resume as form properties", async ({ page }) => {
    await page.goto(LIST_URL);
    await page.locator("#wpbody-content").getByRole("link", { name: "Add New Form" }).click();
    formId = Number(new URL(page.url()).searchParams.get("id"));

    const tabs = page.locator(".wp-sjs-editor__tabs");
    await expect(tabs.locator(".nav-tab-active")).toHaveText("Creator");
    await expect(tabs.getByRole("link", { name: "Config" })).toHaveCount(0);
    await expect(tabs.getByRole("link", { name: "Spam Protection" })).toHaveCount(0);
    await expect(tabs.getByRole("link", { name: "Save & Resume" })).toHaveCount(0);
    await expect(tabs.getByRole("link", { name: "Email Notification" })).toBeVisible();
    await expect(page.locator(".svc-creator")).toBeVisible();
    for (const tab of ["Designer", "Preview", "Themes", "Logic", "JSON Editor"]) {
      await expect(page.locator(".svc-creator").getByText(tab, { exact: true }).first()).toBeVisible();
    }

    await toggleFormProperty(page, "GDPR", "GDPR agreement");
    await expect.poll(() => page.evaluate(() => JSON.stringify(window.surveyjsEditor.getSurvey()))).toContain("gdpr_agreement");
    await toggleFormProperty(page, "continue later", "Let respondents continue later");
    expect(await page.evaluate(() => window.surveyjsEditor.getSettings().saveResume)).toBe(true);
    expect(await page.evaluate(() => window.surveyjsEditor.isDirty())).toBe(true);
    await save(page);

    const state = contract("state", formId);
    expect(state.settings.saveResume).toBe(true);
    expect(JSON.stringify(state.json)).toContain("gdpr_agreement");
    expect(JSON.stringify(state.json)).not.toContain("gdprAgreement");

    await page.reload();
    await expect(page.locator(".svc-creator")).toBeVisible();
    expect(await page.evaluate(() => window.surveyjsEditor.getSettings().saveResume)).toBe(true);
    await toggleFormProperty(page, "GDPR", "GDPR agreement");
    await expect.poll(() => page.evaluate(() => JSON.stringify(window.surveyjsEditor.getSurvey()))).not.toContain("gdpr_agreement");
  });

  test("full screen hides the WordPress admin menu and bar", async ({ page }) => {
    test.skip(!formId, "Needs the form from the previous test");
    await page.goto(`/wp-admin/admin.php?page=surveyjs_editor&id=${formId}`);
    await page.getByRole("button", { name: "Full screen" }).click();
    await expect(page.locator(".wp-sjs-editor")).toHaveClass(/wp-sjs-pro-fullscreen/);
    await expect(page.locator("#adminmenumain")).toBeHidden();
    await expect(page.locator("#wpadminbar")).toBeHidden();
    await page.reload();
    await expect(page.locator(".wp-sjs-editor")).toHaveClass(/wp-sjs-pro-fullscreen/);
    await page.getByRole("button", { name: "Exit full screen" }).click();
    await expect(page.locator(".wp-sjs-editor")).not.toHaveClass(/wp-sjs-pro-fullscreen/);
    await expect(page.locator("#adminmenumain")).toBeVisible();
  });
});
