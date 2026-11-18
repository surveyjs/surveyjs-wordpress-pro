const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");
const { test, expect } = require("@playwright/test");

const WP_CONTAINER = process.env.WP_CONTAINER;
const LIST_URL = "/wp-admin/admin.php?page=surveyjs-main-menu";
const SETTINGS_URL = "/wp-admin/admin.php?page=surveyjs-settings";
const ADMIN_STYLE = "Match the WordPress admin style";

function contract(...args) {
  const output = execFileSync("docker", ["exec", "-i", WP_CONTAINER, "php", "--", ...args.map(String)], {
    input: fs.readFileSync(path.join(__dirname, "fixtures/contract.php")),
    stdio: ["pipe", "pipe", "ignore"]
  });
  return JSON.parse(output.toString().trim().split("\n").pop());
}

async function setAdminStyle(page, on) {
  await page.goto(SETTINGS_URL);
  await page.getByLabel(ADMIN_STYLE).setChecked(on);
  await Promise.all([
    page.waitForURL(/settings-updated=true/),
    page.getByRole("button", { name: "Save Changes" }).click()
  ]);
}

async function creatorStyles(page) {
  await page.locator(".svc-toolbox__tool", { hasText: "Single-Line Input" }).locator(".svc-toolbox__item").click();
  await expect(page.locator(".svc-creator .svc-side-bar .sd-formbox").first()).toBeVisible();
  return page.evaluate(() => {
    const tab = getComputedStyle(document.querySelector(".svc-tabbed-menu-item__text"));
    const input = getComputedStyle(document.querySelector(".svc-side-bar .sd-formbox"));
    const probe = document.createElement("span");
    probe.style.color = "var(--wp-sjs-pro-input-border)";
    document.body.appendChild(probe);
    const border = getComputedStyle(probe).color;
    probe.remove();
    const themeSettings = Boolean(document.querySelector('.svc-creator [*|href*="gear-24x24"]'));
    return { themeSettings, tabFont: tab.fontSize, inputBg: input.backgroundColor, inputShadow: input.boxShadow, inputRadius: input.borderRadius, border };
  });
}

test.describe("Survey Creator in the WordPress admin style", () => {
  test.skip(!WP_CONTAINER, "Set WP_CONTAINER to the WordPress container name to delete the test form");

  let formId = 0;
  const editorUrl = () => `/wp-admin/admin.php?page=surveyjs_editor&id=${formId}`;

  test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage();
    await page.goto(LIST_URL);
    await page.locator("#wpbody-content").getByRole("link", { name: "Add New Form" }).click();
    await page.getByRole("button", { name: "Create blank form" }).click();
    await expect(page).toHaveURL(/page=surveyjs_editor/);
    formId = Number(new URL(page.url()).searchParams.get("id"));
    await page.close();
  });

  test.afterAll(async ({ browser }) => {
    const page = await browser.newPage();
    await setAdminStyle(page, true);
    await page.close();
    if (formId) contract("cleanup", formId);
  });

  test("Creator takes WordPress admin sizes and input styles", async ({ page }) => {
    await setAdminStyle(page, true);
    await page.goto(editorUrl());
    const styles = await creatorStyles(page);
    expect(styles.themeSettings).toBe(false);
    expect(styles.tabFont).toBe("13px");
    expect(styles.inputBg).toBe("rgb(255, 255, 255)");
    expect(styles.inputShadow).toContain(styles.border);
    expect(styles.inputRadius).toBe("2px");
  });

  test("turning the setting off brings back Creator's default look", async ({ page }) => {
    await setAdminStyle(page, false);
    await page.goto(editorUrl());
    const styles = await creatorStyles(page);
    expect(styles.tabFont).not.toBe("13px");
    expect(styles.inputRadius).not.toBe("2px");
    expect(styles.inputShadow).not.toContain(styles.border);
  });
});
