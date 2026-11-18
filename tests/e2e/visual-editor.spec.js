const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");
const { test, expect } = require("@playwright/test");

const WP_CONTAINER = process.env.WP_CONTAINER;
const LIST_URL = "/wp-admin/admin.php?page=surveyjs-main-menu";
const SETTINGS_URL = "/wp-admin/admin.php?page=surveyjs-settings";
const TEMPLATES_URL = "/wp-admin/admin.php?page=surveyjs-templates";

function contract(...args) {
  const output = execFileSync("docker", ["exec", "-i", WP_CONTAINER, "php", "--", ...args.map(String)], {
    input: fs.readFileSync(path.join(__dirname, "fixtures/contract.php")),
    stdio: ["pipe", "pipe", "ignore"]
  });
  return JSON.parse(output.toString().trim().split("\n").pop());
}

async function setCreator(page, on) {
  await page.goto(SETTINGS_URL);
  await page.getByLabel("Edit forms in Survey Creator").setChecked(on);
  await Promise.all([
    page.waitForURL(/settings-updated=true/),
    page.getByRole("button", { name: "Save Changes" }).click()
  ]);
}

test.describe.serial("Base's blank form texts and empty-form hint follow the Creator setting", () => {
  test.skip(!WP_CONTAINER, "Set WP_CONTAINER to the WordPress container name to delete the test form");

  let formId = 0;
  const editorUrl = () => `/wp-admin/admin.php?page=surveyjs_editor&id=${formId}`;

  test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage();
    await page.goto(LIST_URL);
    await page.locator("#wpbody-content").getByRole("link", { name: "Add New Form" }).click();
    await page.getByRole("button", { name: "Create blank form" }).click();
    await expect(page).toHaveURL(/page=surveyjs_editor/);
    formId = Number(new URL(page.url()).searchParams.get("id"));
    await page.close();
  });

  test.afterAll(async ({ browser }) => {
    const page = await browser.newPage();
    await setCreator(page, true);
    await page.close();
    if (formId) contract("cleanup", formId);
  });

  test("with the Creator on, the blank template talks about the Config tab and the empty form has no hint", async ({ page }) => {
    await setCreator(page, true);
    await page.goto(TEMPLATES_URL);
    await expect(page.locator(".wp-sjs-template--blank .wp-sjs-template__description")).toHaveText("Start from scratch and add questions visually in the Config tab.");
    await page.goto(editorUrl());
    await expect(page.locator("#wp-sjs-preview-panel")).toBeAttached();
    await expect(page.locator("#wp-sjs-empty-hint")).toHaveCount(0);
  });

  test("with the Creator off, the blank template and the empty-form hint point to the JSON editor", async ({ page }) => {
    await setCreator(page, false);
    await page.goto(TEMPLATES_URL);
    await expect(page.locator(".wp-sjs-template--blank .wp-sjs-template__description")).toHaveText("Start from scratch and add questions in the JSON editor or the visual UI Builder.");
    await page.goto(editorUrl());
    await expect(page.locator(".monaco-editor")).toBeVisible();
    await expect(page.locator("#wp-sjs-empty-hint")).toBeVisible();
  });
});
