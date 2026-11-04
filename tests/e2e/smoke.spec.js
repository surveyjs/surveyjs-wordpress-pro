const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");
const { test, expect } = require("@playwright/test");

const WP_CONTAINER = process.env.WP_CONTAINER;
const SETTINGS_URL = "/wp-admin/admin.php?page=surveyjs-settings";
const BASE_LIBS = "/wp-content/plugins/surveyjs/libs/library/";
const PRO_LIBS = "/wp-content/plugins/surveyjs-pro/libs/";

function basePlugin(action) {
  const output = execFileSync("docker", ["exec", "-i", WP_CONTAINER, "php", "--", action], {
    input: fs.readFileSync(path.join(__dirname, "fixtures/base-plugin.php")),
    stdio: ["pipe", "pipe", "ignore"]
  });
  return JSON.parse(output.toString().trim().split("\n").pop());
}

async function saveSettings(page) {
  await Promise.all([
    page.waitForURL(/settings-updated=true/),
    page.getByRole("button", { name: "Save Changes" }).click()
  ]);
}

async function addScripts(page, urls) {
  for (const url of urls) {
    await page.addScriptTag({ url });
  }
}

test("Pro is active next to SurveyJS Forms", async ({ page }) => {
  await page.goto("/wp-admin/plugins.php");
  await expect(page.locator('tr[data-plugin="surveyjs-pro/surveyjs-pro.php"]')).toHaveClass(/\bactive\b/);
  await expect(page.locator('tr[data-plugin="surveyjs/surveyjs.php"]')).toHaveClass(/\bactive\b/);
  await expect(page.locator(".surveyjs-pro-base-notice")).toHaveCount(0);
});

test("the license key is saved in the Pro section of Settings", async ({ page }) => {
  await page.goto(SETTINGS_URL);
  await expect(page.getByRole("heading", { name: "SurveyJS Forms Pro" })).toBeVisible();
  const field = page.getByLabel("SurveyJS License Key");
  const original = await field.inputValue();
  const key = `e2e-license-${Date.now()}`;
  try {
    await field.fill(key);
    await saveSettings(page);
    await page.goto(SETTINGS_URL);
    await expect(page.getByLabel("SurveyJS License Key")).toHaveValue(key);
  } finally {
    await page.goto(SETTINGS_URL);
    await page.getByLabel("SurveyJS License Key").fill(original);
    await saveSettings(page);
  }
});

test("Pro libraries run on the Form Library shipped with SurveyJS Forms", async ({ page }) => {
  const errors = [];
  page.on("pageerror", error => errors.push(error.message));
  await page.goto("/wp-admin/index.php");
  await addScripts(page, [
    BASE_LIBS + "survey.core.min.js",
    BASE_LIBS + "survey-js-ui.min.js",
    PRO_LIBS + "survey-creator-core/survey-creator-core.min.js",
    PRO_LIBS + "survey-creator-js/survey-creator-js.min.js",
    PRO_LIBS + "jspdf/jspdf.umd.min.js",
    PRO_LIBS + "survey-pdf/survey.pdf.min.js",
    PRO_LIBS + "chart.js/chart.umd.min.js",
    PRO_LIBS + "chartjs-plugin-datalabels/chartjs-plugin-datalabels.min.js",
    PRO_LIBS + "survey-analytics/survey.analytics.min.js",
    PRO_LIBS + "tabulator-tables/tabulator.min.js",
    PRO_LIBS + "survey-analytics/survey.analytics.tabulator.min.js"
  ]);
  const result = await page.evaluate(async () => {
    const json = { elements: [{ type: "radiogroup", name: "color", choices: ["red", "green"] }] };
    const host = document.createElement("div");
    host.style.height = "600px";
    document.body.append(host);
    new SurveyCreator.SurveyCreator({}).render(host);
    const pdf = await new SurveyPDF.SurveyPDF(json).raw();
    return {
      setLicenseKey: typeof Survey.setLicenseKey,
      pdf: pdf.slice(0, 5),
      analytics: typeof SurveyAnalytics.VisualizationPanel,
      tabulator: typeof SurveyAnalyticsTabulator.Tabulator
    };
  });
  expect(result).toEqual({ setLicenseKey: "function", pdf: "%PDF-", analytics: "function", tabulator: "function" });
  await expect(page.locator(".svc-creator")).toBeVisible();
  expect(errors).toEqual([]);
});

test("Pro shows a notice instead of loading when SurveyJS Forms is inactive", async ({ page }) => {
  test.skip(!WP_CONTAINER, "Set WP_CONTAINER to the WordPress container name to deactivate SurveyJS Forms");
  expect(basePlugin("deactivate").active).toBe(false);
  try {
    await page.goto("/wp-admin/index.php");
    await expect(page.locator(".surveyjs-pro-base-notice")).toContainText("Activate SurveyJS Forms to use Pro.");
  } finally {
    expect(basePlugin("activate").active).toBe(true);
  }
  await page.goto("/wp-admin/index.php");
  await expect(page.locator(".surveyjs-pro-base-notice")).toHaveCount(0);
});
