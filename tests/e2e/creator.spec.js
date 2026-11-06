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

test.describe("Survey Creator in the form editor", () => {
  test.skip(!WP_CONTAINER, "Set WP_CONTAINER to the WordPress container name to read and delete the test form");

  let formId = 0;

  test.afterAll(() => {
    if (formId) contract("cleanup", formId);
  });

  test("replaces the JSON editor in the Config tab and adds GDPR from the toolbox", async ({ page }) => {
    await page.goto(LIST_URL);
    await page.locator("#wpbody-content").getByRole("link", { name: "Add New Form" }).click();
    formId = Number(new URL(page.url()).searchParams.get("id"));

    const tabs = page.locator(".wp-sjs-editor__tabs");
    await expect(tabs.locator(".nav-tab").first()).toHaveText("Config");
    await expect(tabs.locator(".nav-tab-active")).toHaveAttribute("data-tab", "creator");
    await expect(tabs.locator('[data-tab="survey"]')).toHaveCount(0);
    for (const tab of ["Theme", "Email", "Entries", "Spam Protection", "PDF", "Advanced"]) {
      await expect(tabs.getByRole("link", { name: tab, exact: true })).toBeVisible();
    }
    await expect(page.locator("#wp-sjs-monaco")).toBeHidden();

    const creator = page.locator('.wp-sjs-pro-creator[data-panel="creator"] .svc-creator');
    await expect(creator).toBeVisible();
    const creatorTabs = creator.locator(".svc-tabbed-menu");
    for (const tab of ["Designer", "Preview", "Logic", "JSON Editor"]) {
      await expect(creatorTabs.getByText(tab, { exact: true })).toBeVisible();
    }
    await expect(creatorTabs.getByText("Themes", { exact: true })).toHaveCount(0);

    await creator.locator(".svc-toolbox__tool", { hasText: "GDPR Agreement" }).locator(".svc-toolbox__item").click();
    await expect.poll(() => page.evaluate(() => JSON.stringify(window.surveyjsEditor.getSurvey()))).toContain("gdpr_agreement");
    expect(await page.evaluate(() => window.surveyjsEditor.isDirty())).toBe(true);
    await save(page);
    expect(await page.evaluate(() => window.surveyjsEditor.isDirty())).toBe(false);

    const { json } = contract("state", formId);
    const gdpr = (json.pages || [json]).flatMap(p => p.elements || []).find(e => e.name === "gdpr_agreement");
    expect(gdpr).toMatchObject({ type: "boolean", isRequired: true });

    await page.reload();
    await expect(page.locator(".svc-creator")).toBeVisible();
    expect(await page.evaluate(() => JSON.stringify(window.surveyjsEditor.getSurvey()))).toContain("gdpr_agreement");
    await expect(page.locator('.svc-creator [data-sv-drop-target-survey-element="gdpr_agreement"]')).toBeVisible();
  });

  test("opens full screen by default and remembers when it is turned off", async ({ page }) => {
    test.skip(!formId, "Needs the form from the previous test");
    await page.goto(`/wp-admin/admin.php?page=surveyjs_editor&id=${formId}`);
    await expect(page.locator(".wp-sjs-editor")).toHaveClass(/wp-sjs-pro-fullscreen/);
    await expect(page.locator("#adminmenu")).toBeHidden();
    await expect(page.locator("#wpadminbar")).toBeHidden();
    await page.getByRole("button", { name: "Exit full screen" }).click();
    await expect(page.locator(".wp-sjs-editor")).not.toHaveClass(/wp-sjs-pro-fullscreen/);
    await expect(page.locator("#adminmenu")).toBeVisible();
    await page.reload();
    const enter = page.getByRole("button", { name: "Full screen", exact: true });
    await expect(enter).toBeVisible();
    await expect(page.locator(".wp-sjs-editor")).not.toHaveClass(/wp-sjs-pro-fullscreen/);
    await enter.click();
    await expect(page.locator(".wp-sjs-editor")).toHaveClass(/wp-sjs-pro-fullscreen/);
    await expect(page.locator("#wpadminbar")).toBeHidden();
  });
});
