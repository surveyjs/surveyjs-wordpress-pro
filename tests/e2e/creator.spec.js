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
    await page.getByRole("button", { name: "Create blank form" }).click();
    await expect(page).toHaveURL(/page=surveyjs_editor/);
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

  test("never renders Base's Config editor while Creator replaces it", async ({ page }) => {
    test.skip(!formId, "Needs the form from the previous test");
    const editorUrl = `/wp-admin/admin.php?page=surveyjs_editor&id=${formId}`;
    await page.addInitScript(() => {
      window.__sjsSeen = { config: false, fullscreenAtRoot: null };
      const check = () => {
        const pane = document.getElementById("wp-sjs-json-pane");
        if ((pane && !pane.hidden) || document.getElementById("wp-sjs-set-logo") || document.getElementById("wp-sjs-lint")) window.__sjsSeen.config = true;
        if (window.__sjsSeen.fullscreenAtRoot === null && document.querySelector(".wp-sjs-editor")) {
          window.__sjsSeen.fullscreenAtRoot = document.documentElement.classList.contains("wp-sjs-pro-fullscreen-mode");
        }
      };
      new MutationObserver(check).observe(document, { childList: true, subtree: true, attributes: true, attributeFilter: ["hidden"] });
    });
    await page.goto(editorUrl);
    await expect(page.locator(".svc-creator")).toBeVisible();
    await expect(page.locator(".wp-sjs-editor__tabs .nav-tab-active")).toHaveAttribute("data-tab", "creator");
    expect(await page.evaluate(() => window.__sjsSeen)).toEqual({ config: false, fullscreenAtRoot: true });
    await expect(page.locator("#wp-sjs-json-pane")).toBeHidden();

    await page.locator(".wp-sjs-editor__tabs").getByRole("link", { name: "Theme", exact: true }).click();
    await expect(page.locator("#wp-sjs-json-pane .monaco-editor")).toBeVisible();
    await expect(page.locator("#wp-sjs-set-logo")).toHaveCount(0);
  });

  test("shows Base's Config editor again when Creator is turned off", async ({ page }) => {
    test.skip(!formId, "Needs the form from the previous test");
    const setCreator = async (on) => {
      await page.goto("/wp-admin/admin.php?page=surveyjs-settings");
      const checkbox = page.getByLabel("Edit forms in Survey Creator");
      await checkbox.setChecked(on);
      const saved = page.waitForResponse(r => r.url().includes("/wp-admin/options.php") && r.request().method() === "POST");
      await page.getByRole("button", { name: "Save Changes" }).click();
      await saved;
      await page.waitForLoadState();
      await expect(checkbox).toBeChecked({ checked: on });
    };
    await setCreator(false);
    try {
      await page.goto(`/wp-admin/admin.php?page=surveyjs_editor&id=${formId}`);
      await expect(page.locator(".wp-sjs-editor__tabs .nav-tab-active")).toHaveAttribute("data-tab", "survey");
      await expect(page.locator("#wp-sjs-json-pane .monaco-editor")).toBeVisible();
      await expect(page.locator("#wp-sjs-set-logo")).toBeVisible();
      await expect(page.locator(".wp-sjs-pro-creator")).toHaveCount(0);
      await expect(page.locator("html")).not.toHaveClass(/wp-sjs-pro-fullscreen-mode/);
    } finally {
      await setCreator(true);
    }
  });

  test("hides the setting that stores files as text for a file question", async ({ page }) => {
    test.skip(!formId, "Needs the form from the previous test");
    await page.goto(`/wp-admin/admin.php?page=surveyjs_editor&id=${formId}`);
    const creator = page.locator(".svc-creator");
    await expect(creator).toBeVisible();
    await creator.locator(".svc-toolbox__tool", { hasText: "File Upload" }).locator(".svc-toolbox__item").click();
    const grid = creator.locator(".svc-side-bar");
    await expect(grid.getByText("Wait for upload to complete")).toBeVisible();
    await expect(grid.getByText("Store file content in JSON result as text")).toHaveCount(0);
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
