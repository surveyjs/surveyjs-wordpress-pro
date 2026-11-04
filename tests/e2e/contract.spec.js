const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");
const { test, expect } = require("@playwright/test");

const WP_CONTAINER = process.env.WP_CONTAINER;
const MU_PLUGIN = "/var/www/html/wp-content/mu-plugins/surveyjs-contract-test.php";
const LIST_URL = "/wp-admin/admin.php?page=surveyjs-main-menu";

function docker(args, input) {
  return execFileSync("docker", ["exec", "-i", WP_CONTAINER, ...args], { input, stdio: ["pipe", "pipe", "ignore"] }).toString();
}

function contract(...args) {
  const output = docker(["php", "--", ...args.map(String)], fs.readFileSync(path.join(__dirname, "fixtures/contract.php")));
  return JSON.parse(output.trim().split("\n").pop());
}

async function save(page) {
  const saved = page.waitForResponse(r => r.url().includes("action=SurveyJS_SaveSurvey"));
  await page.getByRole("button", { name: "Save", exact: true }).click();
  await saved;
  await expect(page.locator("#wp-sjs-editor-notice")).toContainText("Form saved");
}

test.describe("extension contract of SurveyJS Forms", () => {
  test.skip(!WP_CONTAINER, "Set WP_CONTAINER to the WordPress container name to install the contract mu-plugin");

  let formId = 0;

  test.beforeAll(() => {
    docker(["sh", "-c", `mkdir -p "$(dirname ${MU_PLUGIN})" && cat > ${MU_PLUGIN}`], fs.readFileSync(path.join(__dirname, "fixtures/contract-mu-plugin.php")));
  });

  test.afterAll(() => {
    contract("cleanup", formId);
    docker(["rm", "-f", MU_PLUGIN]);
  });

  test("editor tab, panel, per-form setting and JS API", async ({ page }) => {
    await page.goto(LIST_URL);
    await page.locator("#wpbody-content").getByRole("link", { name: "Add New Form" }).click();
    await expect(page.locator(".svc-creator")).toBeVisible();
    formId = Number(new URL(page.url()).searchParams.get("id"));

    await page.getByRole("link", { name: "Contract Tab" }).click();
    await expect(page.locator(".wp-sjs-editor")).toHaveAttribute("data-active-tab", "contract");
    await expect(page.locator("#wp-sjs-json-pane")).toBeHidden();
    await page.getByLabel("Contract option").fill("contract value");

    const json = { elements: [{ type: "text", name: "e2e_question", title: "Contract question" }] };
    expect(await page.evaluate(value => window.surveyjsEditor.setSurvey(value), json)).toBe(true);
    expect(await page.evaluate(() => window.surveyjsEditor.isDirty())).toBe(true);
    await save(page);

    const state = contract("state", formId);
    expect(state.settings.contractOption).toBe("contract value");
    expect(state.json).toEqual(json);

    await page.reload();
    await page.getByRole("link", { name: "Contract Tab" }).click();
    await expect(page.getByLabel("Contract option")).toHaveValue("contract value");
    expect(await page.evaluate(() => window.surveyjsEditor.getSurvey())).toEqual(json);
  });

  test("results views, title action, row and bulk actions, forms list and menu", async ({ page }) => {
    test.skip(!formId, "Needs the form from the editor test");
    const entry = contract("insert-entry", formId).id;
    const resultsUrl = `/wp-admin/admin.php?page=surveyjs_results&id=${formId}`;

    await page.goto(resultsUrl);
    await expect(page.locator("#contract-title-action")).toBeVisible();
    await expect(page.locator(".wp-sjs-results__views .nav-tab-active")).toHaveText("Entries");
    await expect(page.locator(".contract-row-action")).toHaveText(`Contract ${entry}`);

    await page.check(`input[name="result[]"][value="${entry}"]`);
    await page.selectOption("#bulk-action-selector-top", "contract_bulk");
    await page.click("#doaction");
    await expect(page.locator(".notice-success")).toContainText("1 result updated.");
    expect(contract("state", formId).bulk).toEqual([entry]);

    await page.getByRole("link", { name: "Contract View" }).click();
    await expect(page).toHaveURL(/view=contract/);
    await expect(page.locator("#contract-view")).toHaveText("1 entries");
    await expect(page.locator("#wp-sjs-results-list")).toHaveCount(0);

    await page.goto(LIST_URL);
    await expect(page.locator(".contract-form-action").first()).toBeAttached();
    await expect(page.locator("#adminmenu a[href$='page=surveyjs-contract']")).toHaveText("Contract Page");
  });
});
