const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");
const { test, expect } = require("@playwright/test");

const WP_CONTAINER = process.env.WP_CONTAINER;
const USERNAME = process.env.WP_USERNAME || "admin";
const LANGUAGE_FILE = "/var/www/html/wp-content/plugins/surveyjs-forms-addon/languages/surveyjs-forms-addon-de_DE.l10n.php";

function docker(args, input) {
  return execFileSync("docker", ["exec", "-i", WP_CONTAINER, ...args], { input, stdio: ["pipe", "pipe", "ignore"] }).toString();
}

function php(fixture, ...args) {
  const output = docker(["php", "--", ...args.map(String)], fs.readFileSync(path.join(__dirname, "fixtures", fixture)));
  return JSON.parse(output.trim().split("\n").pop());
}

test.describe("translations bundled in languages/", () => {
  test.skip(!WP_CONTAINER, "Set WP_CONTAINER to the WordPress container name to install the test translation");

  let formId = 0;

  test.beforeAll(() => {
    docker(["sh", "-c", `cat > ${LANGUAGE_FILE}`], fs.readFileSync(path.join(__dirname, "fixtures/surveyjs-forms-addon-de_DE.l10n.php")));
    expect(php("i18n.php", USERNAME, "de_DE").user).toBeGreaterThan(0);
  });

  test.afterAll(() => {
    php("i18n.php", USERNAME, "");
    docker(["rm", "-f", LANGUAGE_FILE]);
    if (formId) php("contract.php", "cleanup", formId);
  });

  test("PHP strings, the menu and the plugin description switch to the user's language", async ({ page }) => {
    await page.goto("/wp-admin/admin.php?page=surveyjs-settings&tab=addon");
    await expect(page.locator("#wpbody-content")).toContainText("Formulare im Survey Creator bearbeiten");
    await expect(page.locator("#adminmenu a[href$='page=surveyjs-forms-addon-dashboard']")).toHaveText("Übersicht");

    await page.goto("/wp-admin/plugins.php");
    await expect(page.locator("tr[data-plugin='surveyjs-forms-addon/surveyjs-forms-addon.php'] .plugin-description")).toContainText("Fügt SurveyJS Forms den Survey Creator");
  });

  test("strings passed to the add-on scripts switch too", async ({ page }) => {
    await page.goto("/wp-admin/admin.php?page=surveyjs-main-menu");
    await page.locator("#wpbody-content").getByRole("link", { name: "Add New Form" }).click();
    await page.getByRole("button", { name: "Create blank form" }).click();
    await expect(page).toHaveURL(/page=surveyjs_editor/);
    formId = Number(new URL(page.url()).searchParams.get("id"));
    await expect(page.locator(".wp-sjs-addon-fullscreen-toggle .wp-sjs-addon-toolbar-button__label")).toHaveText("Vollbild beenden");
  });
});
