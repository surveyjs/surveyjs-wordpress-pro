const fs = require("fs");
const path = require("path");
const zlib = require("zlib");
const { execFileSync } = require("child_process");
const { test, expect } = require("@playwright/test");

const WP_CONTAINER = process.env.WP_CONTAINER;
const MAILPIT_URL = process.env.MAILPIT_URL;
const ACCENT = "#1b4b6e";
const FOOTER = "acme.example, +1 555 0100";

function fixture(...args) {
  const output = execFileSync("docker", ["exec", "-i", WP_CONTAINER, "php", "--", ...args.map(String)], {
    input: fs.readFileSync(path.join(__dirname, "fixtures/pdf-theme.php")),
    stdio: ["pipe", "pipe", "ignore"]
  });
  return JSON.parse(output.toString().trim().split("\n").pop());
}

function inspect(buffer) {
  const raw = buffer.toString("latin1");
  let content = raw;
  const streams = /\/FlateDecode[^]*?stream\r?\n/g;
  let match;
  while ((match = streams.exec(raw))) {
    const start = match.index + match[0].length;
    const end = raw.indexOf("endstream", start);
    try {
      content += zlib.inflateSync(buffer.subarray(start, end)).toString("latin1");
    } catch (error) {
      continue;
    }
  }
  const fontIds = (name) => {
    const objects = [...raw.matchAll(new RegExp(`(\\d+) 0 obj\\s*<<[^>]*/BaseFont /${name}\\b`, "g"))].map((m) => m[1]);
    return [...raw.matchAll(/\/(F\d+) (\d+) 0 R/g)].filter((m) => objects.includes(m[2])).map((m) => m[1]);
  };
  const usesFont = (name) => fontIds(name).some((id) => new RegExp(`/${id} [\\d.]+ Tf`).test(content));
  const usesColor = (hex) => {
    const target = [1, 3, 5].map((i) => parseInt(hex.substr(i, 2), 16) / 255);
    return [...content.matchAll(/([\d.]+) ([\d.]+) ([\d.]+) (?:rg|RG)\b/g)]
      .some((m) => m.slice(1, 4).every((value, i) => Math.abs(parseFloat(value) - target[i]) < 0.005));
  };
  return {
    pdf: raw.startsWith("%PDF-"),
    landscape: [...raw.matchAll(/\/MediaBox \[0 0 ([\d.]+) ([\d.]+)\]/g)].some((m) => parseFloat(m[1]) > parseFloat(m[2])),
    portrait: /\/MediaBox \[0 0 612\.? 792\.?\]|\/MediaBox \[0 0 595\.2\d* 841\.8\d*\]/.test(raw),
    content,
    usesFont,
    usesColor
  };
}

async function download(page, click) {
  const [file] = await Promise.all([page.waitForEvent("download"), click()]);
  return { name: file.suggestedFilename(), data: fs.readFileSync(await file.path()) };
}

async function entryPdf(page, formId) {
  await page.goto(`/wp-admin/admin.php?page=surveyjs_results&id=${formId}`);
  const row = page.locator("#the-list tr").first();
  await row.hover();
  return download(page, () => row.locator(".wp-sjs-addon-pdf-entry").click());
}

async function save(page) {
  const saved = page.waitForResponse((r) => r.url().includes("action=SurveyJS_SaveSurvey"));
  await page.getByRole("button", { name: "Save", exact: true }).click();
  await saved;
  await expect(page.locator(".wp-sjs-snackbars")).toContainText("Form saved");
}

async function mailPdf(request, query) {
  for (let attempt = 0; attempt < 30; attempt++) {
    const search = await (await request.get(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(query)}`)).json();
    if (search.messages.length) {
      const message = await (await request.get(`${MAILPIT_URL}/api/v1/message/${search.messages[0].ID}`)).json();
      const pdf = message.Attachments.find((part) => part.FileName.endsWith(".pdf"));
      return pdf ? Buffer.from(await (await request.get(`${MAILPIT_URL}/api/v1/message/${message.ID}/part/${pdf.PartID}`)).body()) : null;
    }
    await new Promise((resolve) => setTimeout(resolve, 1000));
  }
  throw new Error(`No email for ${query}`);
}

test.describe("PDF theme", () => {
  test.describe.configure({ mode: "serial" });
  test.skip(!WP_CONTAINER, "Set WP_CONTAINER to the WordPress container name to create the test form");

  let form = null;
  const editorUrl = () => `/wp-admin/admin.php?page=surveyjs_editor&id=${form.id}&tab=pdf`;

  test.beforeAll(() => {
    form = fixture("create", `E2E PDF Theme ${Date.now()}`);
  });

  test.afterAll(() => {
    if (form) fixture("cleanup", form.id, form.page);
  });

  test("new forms keep the default PDF look", async ({ page }) => {
    expect(fixture("settings", form.id)).toMatchObject({
      addonPdfOrientation: "portrait",
      addonPdfFont: "helvetica",
      addonPdfAccent: false,
      addonPdfAccentColor: "#19b394",
      addonPdfFooterText: "",
      addonPdfFooterDate: false
    });

    const pdf = inspect((await entryPdf(page, form.id)).data);
    expect(pdf.pdf).toBe(true);
    expect(pdf.portrait).toBe(true);
    expect(pdf.landscape).toBe(false);
    expect(pdf.usesFont("Helvetica")).toBe(true);
    expect(pdf.usesFont("Times-Roman")).toBe(false);
    expect(pdf.usesColor("#19b394")).toBe(true);
    expect(pdf.usesColor(ACCENT)).toBe(false);
  });

  test("the PDF tab saves the theme settings", async ({ page }) => {
    await page.goto(editorUrl());
    const color = page.locator('input[data-setting="addonPdfAccentColor"]');
    await expect(color).toBeDisabled();
    await page.locator("#wp-sjs-addon-pdf-font").selectOption("times");
    await page.locator('input[data-setting="addonPdfAccent"]').check();
    await expect(color).toBeEnabled();
    await color.fill(ACCENT);
    await page.locator("#wp-sjs-addon-pdf-header-site").click();
    const siteTitle = await page.evaluate(() => window.surveyjsAddonPdf.site.title);
    await expect(page.locator("#wp-sjs-addon-pdf-header-text")).toHaveValue(siteTitle);
    await page.locator("#wp-sjs-addon-pdf-footer-text").fill(FOOTER);
    await page.locator('input[data-setting="addonPdfFooterDate"]').check();
    await save(page);

    expect(fixture("settings", form.id)).toMatchObject({
      addonPdfFont: "times",
      addonPdfAccent: true,
      addonPdfAccentColor: ACCENT,
      addonPdfHeaderText: siteTitle,
      addonPdfFooterText: FOOTER,
      addonPdfFooterDate: true
    });

    await page.goto(editorUrl());
    await expect(page.locator("#wp-sjs-addon-pdf-font")).toHaveValue("times");
    await expect(color).toHaveValue(ACCENT);
    await expect(page.locator("#wp-sjs-addon-pdf-footer-text")).toHaveValue(FOOTER);
    await expect(page.locator('input[data-setting="addonPdfFooterDate"]')).toBeChecked();
  });

  test("invalid theme values fall back to the defaults", () => {
    expect(fixture("sanitize", JSON.stringify({
      addonPdfOrientation: "sideways",
      addonPdfFont: "comic-sans",
      addonPdfAccentColor: "red; background: url(x)",
      addonPdfFooterText: `<b>${"x".repeat(150)}</b>`
    }))).toMatchObject({ addonPdfOrientation: "portrait", addonPdfFont: "helvetica", addonPdfAccentColor: "#19b394", addonPdfFooterText: "x".repeat(100) });
    expect(fixture("sanitize", JSON.stringify({ addonPdfAccentColor: "#ABC" })).addonPdfAccentColor).toBe("#aabbcc");
    expect(fixture("sanitize", JSON.stringify({ addonPdfOrientation: "landscape" })).addonPdfOrientation).toBe("landscape");
  });

  test("the entry PDF and the live preview use the theme", async ({ page }) => {
    const pdf = inspect((await entryPdf(page, form.id)).data);
    expect(pdf.portrait).toBe(true);
    expect(pdf.usesFont("Times-Roman")).toBe(true);
    expect(pdf.usesFont("Times-Bold")).toBe(true);
    expect(pdf.usesColor(ACCENT)).toBe(true);
    expect(pdf.usesColor("#19b394")).toBe(false);
    expect(pdf.content).toContain(`(${FOOTER}`);

    await page.goto(editorUrl());
    await expect(page.locator("#wp-sjs-addon-pdf-live")).toHaveAttribute("src", /^blob:/);
    const preview = await page.evaluate(async () => {
      const response = await fetch(document.getElementById("wp-sjs-addon-pdf-live").src);
      return Array.from(new Uint8Array(await response.arrayBuffer()));
    });
    const live = inspect(Buffer.from(preview));
    expect(live.usesFont("Times-Roman")).toBe(true);
    expect(live.usesColor(ACCENT)).toBe(true);
  });

  test("a form with Cyrillic text still gets a font that has its letters", async ({ page }) => {
    await page.goto(editorUrl());
    await page.locator("#wp-sjs-addon-pdf-footer-text").fill("ООО «Ромашка»");
    await save(page);
    const pdf = inspect((await entryPdf(page, form.id)).data);
    expect(pdf.usesFont("Times-Roman")).toBe(false);
    expect(pdf.content).toMatch(/\/BaseFont \/[A-Za-z+]*NotoSans/);
  });

  test("the PDF attached to the email notification uses the theme", async ({ browser, request }) => {
    test.skip(!MAILPIT_URL, "Set MAILPIT_URL to the Mailpit address to check the emails");
    const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    const page = await context.newPage();
    const name = `Theme ${Date.now()}`;
    await page.goto(form.url);
    await page.getByLabel("Full name").fill(name);
    const saved = page.waitForResponse((r) => r.url().includes("action=SurveyJS_SaveResult"));
    await page.getByRole("button", { name: "Complete" }).click();
    expect((await saved).ok()).toBe(true);
    await expect(page.locator(".wp-sjs-addon-pdf-download button")).toBeVisible();
    await context.close();

    const attachment = await mailPdf(request, `to:owner@example.test "${name}"`);
    expect(attachment).not.toBeNull();
    const pdf = inspect(attachment);
    expect(pdf.portrait).toBe(true);
    expect(pdf.usesColor(ACCENT)).toBe(true);
    expect(pdf.content).toMatch(/\/BaseFont \/[A-Za-z+]*NotoSans/);
  });

  test("Landscape page orientation turns the live preview and the entry PDF", async ({ page }) => {
    await page.goto(editorUrl());
    const live = page.locator("#wp-sjs-addon-pdf-live");
    await expect(live).toHaveAttribute("src", /^blob:/);
    const portraitSrc = await live.getAttribute("src");
    await page.locator("#wp-sjs-addon-pdf-orientation").selectOption("landscape");
    await expect(live).not.toHaveAttribute("src", portraitSrc);
    const preview = await page.evaluate(async () => {
      const response = await fetch(document.getElementById("wp-sjs-addon-pdf-live").src);
      return Array.from(new Uint8Array(await response.arrayBuffer()));
    });
    expect(inspect(Buffer.from(preview)).landscape).toBe(true);
    await save(page);
    expect(fixture("settings", form.id).addonPdfOrientation).toBe("landscape");

    const pdf = inspect((await entryPdf(page, form.id)).data);
    expect(pdf.landscape).toBe(true);
    expect(pdf.portrait).toBe(false);
  });
});
