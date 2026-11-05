const fs = require("fs");
const path = require("path");

const root = path.join(__dirname, "..");
const modules = path.join(root, "node_modules");
const target = path.join(root, "libs");

const FILES = {
  "survey-creator-core": ["survey-creator-core.min.js", "survey-creator-core.min.css", "images", "LICENSE"],
  "survey-creator-js": ["survey-creator-js.min.js", "LICENSE"],
  "survey-pdf": ["survey.pdf.min.js", "survey.pdf.fonts.min.js", "layouts/spacious.min.js", "LICENSE"],
  "survey-analytics": [
    "survey.analytics.min.js",
    "survey.analytics.min.css",
    "survey.analytics.tabulator.min.js",
    "survey.analytics.tabulator.min.css",
    "LICENSE"
  ],
  "jspdf": ["dist/jspdf.umd.min.js", "LICENSE"],
  "chart.js": ["dist/chart.umd.min.js", "LICENSE.md"],
  "chartjs-plugin-datalabels": ["dist/chartjs-plugin-datalabels.min.js", "LICENSE.md"],
  "tabulator-tables": ["dist/js/tabulator.min.js", "dist/css/tabulator.min.css", "LICENSE"]
};

const SURVEYJS_PACKAGES = ["survey-core", "survey-js-ui", "survey-creator-core", "survey-creator-js", "survey-pdf", "survey-analytics"];

function packageVersion(name) {
  return JSON.parse(fs.readFileSync(path.join(modules, name, "package.json"), "utf8")).version;
}

function pluginLibraryVersion() {
  const source = fs.readFileSync(path.join(root, "surveyjs-pro.php"), "utf8");
  const match = source.match(/define\(\s*'SURVEYJS_PRO_LIBRARY_VERSION',\s*'([^']+)'\s*\)/);
  if (!match) throw new Error("SURVEYJS_PRO_LIBRARY_VERSION is not defined in surveyjs-pro.php");
  return match[1];
}

const expected = pluginLibraryVersion();
const mismatched = SURVEYJS_PACKAGES.filter((name) => packageVersion(name) !== expected);
if (mismatched.length) {
  console.error(`SURVEYJS_PRO_LIBRARY_VERSION is ${expected}, but node_modules has ${mismatched.map((name) => `${name}@${packageVersion(name)}`).join(", ")}.`);
  process.exit(1);
}

fs.rmSync(target, { recursive: true, force: true });
for (const [name, files] of Object.entries(FILES)) {
  for (const file of files) {
    const destination = path.join(target, name, path.basename(file));
    fs.cpSync(path.join(modules, name, file), destination, { recursive: true });
  }
  console.log(`${name}@${packageVersion(name)}`);
}
