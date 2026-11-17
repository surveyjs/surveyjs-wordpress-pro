const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");
const { test, expect } = require("@playwright/test");

const WP_CONTAINER = process.env.WP_CONTAINER;
const ROOT = "/var/www/html";
const PLUGINS_URL = "/wp-admin/plugins.php";
const PRO_ROW = 'tr[data-plugin="surveyjs-pro/surveyjs-pro.php"]';
const BASE_UPDATE_ROW = '#surveyjs-update';
const COMPOSER_JSON = JSON.stringify({ require: { "surveyjs/surveyjs-pro": "3.2.4" } });
const installedJson = installPath => JSON.stringify({
  packages: [{ name: "surveyjs/surveyjs-pro", version: "3.2.4", type: "wordpress-plugin", "install-path": installPath }],
  dev: true,
  "dev-package-names": []
});

function docker(args, input) {
  return execFileSync("docker", ["exec", "-i", WP_CONTAINER, ...args], { input, stdio: ["pipe", "pipe", "ignore"] }).toString();
}

function updater(command = "") {
  const output = docker(["php", "--", command], fs.readFileSync(path.join(__dirname, "fixtures/updater.php")));
  return JSON.parse(output.trim().split("\n").pop());
}

function installWithComposer(installPath) {
  docker(["sh", "-c", `mkdir -p ${ROOT}/vendor/composer && cat > ${ROOT}/vendor/composer/installed.json`], installedJson(installPath));
  docker(["sh", "-c", `cat > ${ROOT}/composer.json`], COMPOSER_JSON);
}

function removeComposer() {
  docker(["rm", "-rf", `${ROOT}/vendor`, `${ROOT}/composer.json`]);
}

test.describe("Pro installed with Composer", () => {
  test.skip(!WP_CONTAINER, "Set WP_CONTAINER to the WordPress container name to fake a Composer install");

  test.beforeAll(() => {
    test.skip(docker(["sh", "-c", `test -e ${ROOT}/composer.json || test -e ${ROOT}/vendor && echo yes || echo no`]).trim() === "yes", "The site already has Composer files");
  });

  test.afterAll(() => {
    removeComposer();
    updater("clear-base-update");
  });

  test("a normal install keeps self-update and shows no Composer note", async ({ page }) => {
    expect(updater()).toEqual({ composer: false, enabled: true });
    await page.goto(PLUGINS_URL);
    await expect(page.locator(PRO_ROW)).toBeVisible();
    await expect(page.locator(`${PRO_ROW} .surveyjs-pro-composer-note`)).toHaveCount(0);
  });

  test("a composer.json that installs Pro elsewhere is not a Composer install of this copy", async () => {
    installWithComposer("../../wp-content/plugins/another-surveyjs-pro");
    try {
      expect(updater()).toEqual({ composer: false, enabled: true });
    } finally {
      removeComposer();
    }
  });

  test("Composer install turns self-update off and says how to update", async ({ page }) => {
    installWithComposer("../../wp-content/plugins/surveyjs-pro");
    expect(updater("offer-base-update")).toEqual({ composer: true, enabled: false });
    await page.goto(PLUGINS_URL);
    await expect(page.locator(`${PRO_ROW} .surveyjs-pro-composer-note`)).toContainText(
      "Installed with Composer, so it doesn't update itself. Update both plugins with composer update surveyjs/surveyjs-pro --with-dependencies"
    );
    await expect(page.locator(BASE_UPDATE_ROW)).toContainText("SurveyJS Forms Pro is installed with Composer and is not updated here");
    await expect(page.locator(BASE_UPDATE_ROW)).not.toContainText("will be updated to the same version automatically");
  });
});
