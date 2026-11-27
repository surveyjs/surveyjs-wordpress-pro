const fs = require("fs");
const path = require("path");

const root = path.join(__dirname, "..");
const modules = path.join(root, "node_modules");
const target = path.join(root, "libs");

const FILES = {
  "survey-creator-core": ["survey-creator-core.min.js", "survey-creator-core.min.css", "images", "LICENSE"],
  "survey-creator-js": ["survey-creator-js.min.js", "LICENSE"],
  "survey-pdf": ["survey.pdf.min.js", "layouts/spacious.min.js", "LICENSE"],
  "survey-analytics": [
    "survey.analytics.min.js",
    "survey.analytics.min.css",
    "LICENSE"
  ],
  "jspdf": ["dist/jspdf.umd.min.js", "LICENSE"],
  "chart.js": ["dist/chart.umd.min.js", "LICENSE.md"],
  "chartjs-plugin-datalabels": ["dist/chartjs-plugin-datalabels.min.js", "LICENSE.md"]
};

const TRANSLATIONS = { "survey-creator-core": "i18n" };
const SKIPPED_TRANSLATIONS = ["index.min.js", "english.min.js"];

const SURVEYJS_PACKAGES = ["survey-core", "survey-js-ui", "survey-creator-core", "survey-creator-js", "survey-pdf", "survey-analytics"];

function packageVersion(name) {
  return JSON.parse(fs.readFileSync(path.join(modules, name, "package.json"), "utf8")).version;
}

function pluginLibraryVersion() {
  const source = fs.readFileSync(path.join(root, "surveyjs-forms-addon.php"), "utf8");
  const match = source.match(/define\(\s*'SURVEYJS_ADDON_LIBRARY_VERSION',\s*'([^']+)'\s*\)/);
  if (!match) throw new Error("SURVEYJS_ADDON_LIBRARY_VERSION is not defined in surveyjs-forms-addon.php");
  return match[1];
}

const expected = pluginLibraryVersion();
const mismatched = SURVEYJS_PACKAGES.filter((name) => packageVersion(name) !== expected);
if (mismatched.length) {
  console.error(`SURVEYJS_ADDON_LIBRARY_VERSION is ${expected}, but node_modules has ${mismatched.map((name) => `${name}@${packageVersion(name)}`).join(", ")}.`);
  process.exit(1);
}

for (const [name, files] of Object.entries(FILES)) {
  fs.rmSync(path.join(target, name), { recursive: true, force: true });
  for (const file of files) {
    const destination = path.join(target, name, path.basename(file));
    fs.cpSync(path.join(modules, name, file), destination, { recursive: true });
  }
  console.log(`${name}@${packageVersion(name)}`);
}
for (const [name, dir] of Object.entries(TRANSLATIONS)) {
  const source = path.join(modules, name, dir);
  const files = fs.readdirSync(source).filter((file) => file.endsWith(".min.js") && !SKIPPED_TRANSLATIONS.includes(file));
  fs.mkdirSync(path.join(target, name, dir), { recursive: true });
  files.forEach((file) => fs.copyFileSync(path.join(source, file), path.join(target, name, dir, file)));
  console.log(`${name}/${dir}: ${files.length} languages`);
}
