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
  await expect(page.locator(".wp-sjs-snackbars")).toContainText("Form saved");
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
    for (const tab of ["Theme", "Email", "Webhooks", "Entries", "Spam Protection", "PDF", "Advanced"]) {
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

  test("keeps WordPress button and input styles out of the find widget of the JSON Editor tab", async ({ page }) => {
    test.skip(!formId, "Needs the form from the previous test");
    await page.goto(`/wp-admin/admin.php?page=surveyjs_editor&id=${formId}`);
    const creator = page.locator(".svc-creator");
    await creator.locator(".svc-tabbed-menu").getByText("JSON Editor", { exact: true }).click();
    await creator.locator(".monaco-editor .view-lines").click();
    await page.keyboard.press("Control+h");
    const widget = creator.locator(".find-widget.visible");
    await expect(widget).toBeVisible();

    const leaks = await widget.evaluate(node => {
      const color = getComputedStyle(node).color;
      return [...node.querySelectorAll(".button, textarea")].flatMap(element => {
        const style = getComputedStyle(element);
        const found = [];
        if (style.borderTopStyle !== "none") found.push("border");
        if (style.minHeight !== "auto" && style.minHeight !== "0px") found.push("min-height " + style.minHeight);
        if (style.boxShadow !== "none") found.push("box-shadow");
        if (element.matches(".button") && style.color !== color) found.push("color");
        return found.map(leak => element.className + ": " + leak);
      });
    });
    expect(leaks).toEqual([]);
  });

  test("shows Base's Config editor again when Creator is turned off", async ({ page }) => {
    test.skip(!formId, "Needs the form from the previous test");
    const setCreator = async (on) => {
      await page.goto("/wp-admin/admin.php?page=surveyjs-settings&tab=addon");
      const checkbox = page.getByLabel("Edit forms in Survey Creator");
      if ((await checkbox.isChecked()) !== on) {
        const saved = page.waitForResponse(r => r.url().includes("/wp-admin/options.php") && r.request().method() === "POST");
        await checkbox.setChecked(on); // saved as soon as it changes
        await saved;
      }
      await page.reload();
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

  test("shows Embed as a dialog over full screen Creator and closes it back to Creator", async ({ page }) => {
    test.skip(!formId, "Needs the form from the previous test");
    const editorUrl = new RegExp(`page=surveyjs_editor&id=${formId}$`);
    await page.goto(`/wp-admin/admin.php?page=surveyjs_editor&id=${formId}`);
    await expect(page.locator(".wp-sjs-editor")).toHaveClass(/wp-sjs-pro-fullscreen/);
    const creator = page.locator(".svc-creator");
    await expect(creator).toBeVisible();
    const embedButton = page.locator("#wp-sjs-embed");
    const dialog = page.getByRole("dialog", { name: "Embed the form" });

    for (const width of [1400, 390]) {
      await page.setViewportSize({ width, height: 800 });
      await embedButton.click();
      await expect(dialog).toBeVisible();
      await expect(dialog.locator(".wp-sjs-embed__header").getByRole("button", { name: "Close" })).toBeInViewport();
      const box = await dialog.boundingBox();
      expect(box.x + box.width).toBeLessThanOrEqual(width);
      await page.mouse.click(2, 2);
      await expect(dialog).toBeHidden();
      await expect(page).toHaveURL(editorUrl);
      await expect(creator).toBeVisible();
    }

    await embedButton.click();
    await page.keyboard.press("Escape");
    await expect(dialog).toBeHidden();
    await expect(embedButton).toBeFocused();
    await embedButton.click();
    await page.goBack();
    await expect(dialog).toBeHidden();
    await expect(page).toHaveURL(editorUrl);
    await expect(creator).toBeVisible();
  });

  test("shows an AI change in Creator also when its JSON Editor tab is open, and saves that JSON", async ({ page }) => {
    test.skip(!formId, "Needs the form from the previous test");
    const stale = { elements: [{ type: "checkbox", name: "methods", choices: ["Medication", "Other"], showOtherItem: true }] };
    const fixed = { elements: [{ type: "checkbox", name: "methods", choices: ["Medication"], showOtherItem: true }] };
    await page.goto(`/wp-admin/admin.php?page=surveyjs_editor&id=${formId}`);
    const creator = page.locator(".svc-creator");
    await expect(creator).toBeVisible();
    await page.evaluate((json) => {
      window.surveyjsEditor.setSurvey(json);
      document.dispatchEvent(new CustomEvent("surveyjs:editor-external-change"));
    }, stale);
    await save(page);

    const tabs = creator.locator(".svc-tabbed-menu");
    await tabs.getByText("JSON Editor", { exact: true }).click();
    const creatorJsonText = () => page.evaluate(() => {
      const host = document.querySelector(".svc-creator");
      const own = window.monaco.editor.getEditors().find((item) => host.contains(item.getContainerDomNode()));
      return own ? own.getValue() : "";
    });
    await expect.poll(creatorJsonText).toContain('"Other"');

    await page.evaluate((json) => {
      const editor = window.surveyjsEditor;
      editor.applyAiDraft(null, { name: editor.config.name, json: json, theme: editor.getTheme(), settings: editor.getSettings() });
    }, fixed);
    await expect.poll(creatorJsonText).not.toContain('"Other"');
    await expect(creator.locator(".svc-json-error")).toHaveCount(0);

    await tabs.getByText("Designer", { exact: true }).click();
    await tabs.getByText("JSON Editor", { exact: true }).click();
    await expect.poll(creatorJsonText).not.toContain('"Other"');

    await save(page);
    await expect(page.locator(".wp-sjs-snackbars")).toContainText("Form saved.");
    const { json } = contract("state", formId);
    const methods = (json.pages || [json]).flatMap(p => p.elements || []).find(e => e.name === "methods");
    expect(methods.choices).toEqual(["Medication"]);
  });

  test("opens Creator's JSON Editor tab at the first issue from the save notice", async ({ page }) => {
    test.skip(!formId, "Needs the form from the previous test");
    await page.goto(`/wp-admin/admin.php?page=surveyjs_editor&id=${formId}`);
    const creator = page.locator(".svc-creator");
    await expect(creator).toBeVisible();
    await page.evaluate(() => {
      window.surveyjsEditor.setSurvey({ elements: [{ type: "text", name: "q1" }, { type: "checkbox", name: "methods", choices: ["Medication", "Other"], showOtherItem: true }] });
      document.dispatchEvent(new CustomEvent("surveyjs:editor-external-change"));
    });
    await save(page);
    const notice = page.locator(".wp-sjs-snackbars");
    await expect(notice).toContainText("Form saved with 1 issue.");
    await expect(notice.locator(".wp-sjs-editor__issues li")).toContainText('contain "Other" while showOtherItem is on');

    await notice.getByRole("button", { name: "Review and fix" }).click();
    await expect(creator.locator(".svc-json-error").first()).toContainText('"Other"');
    await expect.poll(() => page.evaluate(() => {
      const focused = window.monaco.editor.getEditors().find((item) => item.hasTextFocus());
      return focused ? focused.getModel().getLineContent(focused.getPosition().lineNumber) : "";
    })).toContain('"Other"');
  });
});
