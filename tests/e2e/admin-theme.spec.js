const { test, expect } = require("@playwright/test");

const SETTINGS_URL = "/wp-admin/admin.php?page=surveyjs-settings";
const DASHBOARD_URL = "/wp-admin/admin.php?page=surveyjs-pro-dashboard";

async function setColorMode(page, mode) {
  await page.goto(SETTINGS_URL);
  await page.getByLabel("Color mode").selectOption(mode);
  await Promise.all([
    page.waitForURL(/settings-updated=true/),
    page.getByRole("button", { name: "Save Changes" }).click()
  ]);
}

function isDark(page) {
  return page.evaluate(() => document.documentElement.classList.contains("wp-sjs-pro-dark"));
}

test.describe("light and dark colors of Pro pages", () => {
  test("the accent is the admin color scheme's color and a light admin keeps Pro pages light", async ({ page }) => {
    await setColorMode(page, "auto");
    await page.goto(DASHBOARD_URL);
    const colors = await page.evaluate(() => {
      const probe = document.createElement("span");
      probe.style.color = "var(--wp-admin-theme-color, #2271b1)";
      document.body.appendChild(probe);
      const admin = getComputedStyle(probe).color.replace(/^rgb\((.*)\)$/, "rgba($1, 1)");
      probe.remove();
      return { admin, accent: window.surveyjsProAdminTheme.color("accent") };
    });
    expect(colors.accent).toBe(colors.admin);
    expect(await isDark(page)).toBe(false);
  });

  test("the Dark and device color modes switch Pro pages to dark", async ({ page }) => {
    try {
      await setColorMode(page, "dark");
      await page.goto(DASHBOARD_URL);
      expect(await isDark(page)).toBe(true);

      await setColorMode(page, "system");
      await page.emulateMedia({ colorScheme: "light" });
      await page.goto(DASHBOARD_URL);
      expect(await isDark(page)).toBe(false);
      await page.emulateMedia({ colorScheme: "dark" });
      await expect.poll(() => isDark(page)).toBe(true);
    } finally {
      await setColorMode(page, "auto");
    }
  });
});
