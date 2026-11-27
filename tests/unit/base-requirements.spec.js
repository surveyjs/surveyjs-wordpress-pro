const path = require("path");
const { execSync } = require("child_process");
const { test, expect } = require("@playwright/test");

const root = path.join(__dirname, "../..");
const php = process.env.PHP_BIN || "php";
const MODULES = ["SurveyJS_Addon_Creator", "SurveyJS_Addon_Analytics", "SurveyJS_Addon_Dashboard", "SurveyJS_Addon_Pdf", "SurveyJS_Addon_Pdf_Respondent", "SurveyJS_Addon_Pdf_Password", "SurveyJS_Addon_AI"];

function run(name) {
  const output = execSync(`${php} tests/unit/php/requirements-cases.php ${name}`, { cwd: root, encoding: "utf8", stdio: ["ignore", "pipe", "pipe"] });
  return JSON.parse(output);
}

test("a SurveyJS Forms without the classes the add-on uses turns the features off instead of failing", () => {
  const { result, output, log } = run("base-without-classes");
  expect(result.disabled).toEqual(MODULES);
  expect(result.dashboard).toBe(false);
  expect(result.libs).toEqual([false, ""]);
  expect(output).toContain("SurveyJS Forms Add-on turned off Survey Creator, Charts, Dashboard");
  expect(output).toContain("SurveyJS_Themes::presets");
  expect(log).toBe("");
});

test("every feature loads when SurveyJS Forms has everything it needs", () => {
  const { result } = run("complete-base");
  expect(result.disabled).toEqual([]);
  expect(result.dashboard).toBe(10);
});

test("a removed method turns off only the feature that uses it", () => {
  const { result } = run("base-without-a-method");
  expect(result.disabled).toEqual({ SurveyJS_Addon_Creator: ["SurveyJS_Themes::presets"] });
  expect(result.creator).toBe(false);
  expect(result.pdf).toBe(10);
  expect(result.views).toBe(false);
});

test("an older SurveyJS Forms loads only the updater", () => {
  expect(run("older-base").result).toEqual({ creator: false, updater: 10 });
});

test("without SurveyJS Forms only the notice is added", () => {
  expect(run("no-base").result).toEqual({ notice: 10, creator: false });
});

test("a second copy of the add-on is skipped instead of redeclaring its classes", () => {
  expect(run("second-copy").result).toMatch(/^\d+\.\d+\.\d+/);
});
