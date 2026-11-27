const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");
const { test, expect } = require("@playwright/test");

const WP_CONTAINER = process.env.WP_CONTAINER;
const SETTINGS_URL = "/wp-admin/admin.php?page=surveyjs-settings&tab=addon";
const BASE_LIBS = "/wp-content/plugins/surveyjs/libs/library/";
const ADDON_LIBS = "/wp-content/plugins/surveyjs-forms-addon/libs/";

function php(fixture, ...args) {
  const output = execFileSync("docker", ["exec", "-i", WP_CONTAINER, "php", "--", ...args], {
    input: fs.readFileSync(path.join(__dirname, "fixtures", fixture)),
    stdio: ["pipe", "pipe", "ignore"]
  });
  return JSON.parse(output.toString().trim().split("\n").pop());
}

function basePlugin(action) {
  return php("base-plugin.php", action);
}

async function selectAndSave(select, value) {
  await select.selectOption(value);
  await expect.poll(() => php("addon-settings.php", "get").admin_theme).toBe(value);
}

async function addScripts(page, urls) {
  for (const url of urls) {
    await page.addScriptTag({ url });
  }
}

test("The add-on is active next to SurveyJS Forms", async ({ page }) => {
  await page.goto("/wp-admin/plugins.php");
  await expect(page.locator('tr[data-plugin="surveyjs-forms-addon/surveyjs-forms-addon.php"]')).toHaveClass(/\bactive\b/);
  await expect(page.locator('tr[data-plugin="surveyjs/surveyjs.php"]')).toHaveClass(/\bactive\b/);
  await expect(page.locator(".surveyjs-forms-addon-base-notice")).toHaveCount(0);
});

test("The Add-on tab has no license key, and a license_key left in the stored settings is dropped on save", async ({ page }) => {
  test.skip(!WP_CONTAINER, "Set WP_CONTAINER to the WordPress container name to write the stored settings");
  const original = php("addon-settings.php", "get");
  const stale = `e2e-stale-license-${Date.now()}`;
  try {
    php("addon-settings.php", "set", JSON.stringify({ ...(original || {}), license_key: stale }));
    await page.goto(SETTINGS_URL);
    await expect(page.getByRole("heading", { name: "SurveyJS Forms Add-on" })).toBeVisible();
    await expect(page.locator("#wpbody-content")).not.toContainText(/license key/i);
    expect(await page.content()).not.toContain(stale);
    const mode = page.getByLabel("Color mode");
    const current = await mode.inputValue();
    await selectAndSave(mode, current === "dark" ? "light" : "dark");
    await selectAndSave(mode, current);
    expect(php("addon-settings.php", "get")).not.toHaveProperty("license_key");
  } finally {
    php("addon-settings.php", "set", JSON.stringify(original));
  }
});

test("The add-on libraries run on the Form Library shipped with SurveyJS Forms", async ({ page }) => {
  const errors = [];
  page.on("pageerror", error => errors.push(error.message));
  await page.goto("/wp-admin/index.php");
  await addScripts(page, [
    BASE_LIBS + "survey.core.min.js",
    BASE_LIBS + "survey-js-ui.min.js",
    ADDON_LIBS + "survey-creator-core/survey-creator-core.min.js",
    ADDON_LIBS + "survey-creator-js/survey-creator-js.min.js",
    ADDON_LIBS + "jspdf/jspdf.umd.min.js",
    ADDON_LIBS + "survey-pdf/survey.pdf.min.js",
    ADDON_LIBS + "chart.js/chart.umd.min.js",
    ADDON_LIBS + "chartjs-plugin-datalabels/chartjs-plugin-datalabels.min.js",
    ADDON_LIBS + "survey-analytics/survey.analytics.min.js"
  ]);
  const result = await page.evaluate(async () => {
    const json = { elements: [{ type: "radiogroup", name: "color", choices: ["red", "green"] }] };
    const host = document.createElement("div");
    host.style.height = "600px";
    document.body.append(host);
    new SurveyCreator.SurveyCreator({}).render(host);
    const pdf = await new SurveyPDF.SurveyPDF(json).raw();
    return {
      pdf: pdf.slice(0, 5),
      analytics: typeof SurveyAnalytics.VisualizationPanel
    };
  });
  expect(result).toEqual({ pdf: "%PDF-", analytics: "function" });
  await expect(page.locator(".svc-creator")).toBeVisible();
  expect(errors).toEqual([]);
});

test("The add-on's SurveyJS libraries have the version of the Form Library shipped with SurveyJS Forms", async ({ page }) => {
  await page.goto("/wp-admin/index.php");
  await addScripts(page, [BASE_LIBS + "survey.core.min.js"]);
  await page.evaluate(() => {
    window.e2eLibraryVersions = {};
    Survey.checkLibraryVersion = (version, name) => {
      window.e2eLibraryVersions[name] = version;
    };
  });
  await addScripts(page, [
    BASE_LIBS + "survey-js-ui.min.js",
    ADDON_LIBS + "survey-creator-core/survey-creator-core.min.js",
    ADDON_LIBS + "survey-creator-js/survey-creator-js.min.js",
    ADDON_LIBS + "jspdf/jspdf.umd.min.js",
    ADDON_LIBS + "survey-pdf/survey.pdf.min.js"
  ]);
  const { core, versions } = await page.evaluate(() => ({ core: Survey.Version, versions: window.e2eLibraryVersions }));
  const analytics = await (await page.request.get(ADDON_LIBS + "survey-analytics/survey.analytics.min.js")).text();
  versions["survey-analytics"] = (analytics.match(/SurveyJS Analytics library v(\S+)/) || [])[1];
  expect(versions).toEqual({
    "survey-js-ui": core,
    "survey-creator-core": core,
    "survey-creator-react": core,
    "survey-pdf": core,
    "survey-analytics": core
  });
});

test("The add-on shows a notice instead of loading when SurveyJS Forms is inactive", async ({ page }) => {
  test.skip(!WP_CONTAINER, "Set WP_CONTAINER to the WordPress container name to deactivate SurveyJS Forms");
  expect(basePlugin("deactivate").active).toBe(false);
  try {
    await page.goto("/wp-admin/index.php");
    await expect(page.locator(".surveyjs-forms-addon-base-notice")).toContainText("Activate SurveyJS Forms to use the add-on.");
  } finally {
    expect(basePlugin("activate").active).toBe(true);
  }
  await page.goto("/wp-admin/index.php");
  await expect(page.locator(".surveyjs-forms-addon-base-notice")).toHaveCount(0);
});
