const fs = require("fs");
const path = require("path");
const zlib = require("zlib");
const { execFileSync } = require("child_process");
const { test, expect } = require("@playwright/test");

const WP_CONTAINER = process.env.WP_CONTAINER;
const MAILPIT_URL = process.env.MAILPIT_URL;
const WHITE_LABEL_URL = "https://surveyjs.io/wordpress#white-label";
const LICENSE_REQUIRED = /developer license is required/i;

function fixture(...args) {
  const output = execFileSync("docker", ["exec", "-i", WP_CONTAINER, "php", "--", ...args.map(String)], {
    input: fs.readFileSync(path.join(__dirname, "fixtures/pdf-theme.php")),
    stdio: ["pipe", "pipe", "ignore"]
  });
  return JSON.parse(output.toString().trim().split("\n").pop());
}

function pdfText(buffer) {
  const raw = buffer.toString("latin1");
  let content = raw;
  const streams = /\/FlateDecode[^]*?stream\r?\n/g;
  let match;
  while ((match = streams.exec(raw))) {
    const start = match.index + match[0].length;
    try {
      content += zlib.inflateSync(buffer.subarray(start, raw.indexOf("endstream", start))).toString("latin1");
    } catch (error) {
      continue;
    }
  }
  return content;
}

function expectSurveyJsBranding(buffer) {
  const content = pdfText(buffer);
  expect(content.slice(0, 5)).toBe("%PDF-");
  expect(content).toContain("(Powered by SurveyJS");
  expect(content).toContain(`/URI (${WHITE_LABEL_URL})`);
  expect(content).not.toMatch(LICENSE_REQUIRED);
}

async function expectBanner(page) {
  expect(await page.evaluate(() => Survey.getEmbeddedLicense())).toEqual({ name: "wordpress", whiteLabelUrl: WHITE_LABEL_URL });
  await expect(page.getByText("Powered by", { exact: true })).toBeVisible();
  await expect(page.locator(`a[href="${WHITE_LABEL_URL}"]`)).toBeVisible();
  await expect(page.locator("body")).not.toContainText(LICENSE_REQUIRED);
}

// Real downloads in Edge close the browser at the end of the test (TargetClosedError), so the PDF is read from the link the page clicks.
async function clickedPdf(page, click) {
  await page.evaluate(() => {
    window.e2eDownloads = [];
    HTMLAnchorElement.prototype.click = function () {
      window.e2eDownloads.push(this.href);
    };
  });
  await click();
  await page.waitForFunction(() => window.e2eDownloads.length > 0);
  return Buffer.from(await page.evaluate(async () => Array.from(new Uint8Array(await (await fetch(window.e2eDownloads[0])).arrayBuffer()))));
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

test.describe("SurveyJS libraries run with the embedded WordPress license marker", () => {
  test.describe.configure({ mode: "serial" });
  test.skip(!WP_CONTAINER, "Set WP_CONTAINER to the WordPress container name to create the test form");

  let form = null;

  test.beforeAll(() => {
    form = fixture("create", `E2E Embedded License ${Date.now()}`);
  });

  test.afterAll(() => {
    if (form) fixture("cleanup", form.id, form.page);
  });

  test("Survey Creator shows the Powered by SurveyJS banner", async ({ page }) => {
    await page.goto(`/wp-admin/admin.php?page=surveyjs_editor&id=${form.id}`);
    await expect(page.locator(".svc-creator")).toBeVisible();
    await expectBanner(page);
  });

  test("Entry charts show the Powered by SurveyJS banner", async ({ page }) => {
    await page.goto(`/wp-admin/admin.php?page=surveyjs_results&id=${form.id}&view=chart`);
    await expect(page.locator(".sa-visualizer-wrapper").first()).toBeVisible();
    await expectBanner(page);
  });

  test("Entry PDFs have the Powered by SurveyJS header", async ({ page }) => {
    await page.goto(`/wp-admin/admin.php?page=surveyjs_results&id=${form.id}`);
    const link = page.locator(".wp-sjs-addon-pdf-entry").first();
    await expect(link).toBeVisible();
    expectSurveyJsBranding(await clickedPdf(page, () => link.click()));
  });

  test("The PDF made in the respondent's browser for the email has the Powered by SurveyJS header", async ({ page, request }) => {
    test.skip(!MAILPIT_URL, "Set MAILPIT_URL to the Mailpit address to check the emails");
    await page.context().clearCookies();
    const name = `Embedded ${Date.now()}`;
    await page.goto(form.url);
    await page.getByLabel("Full name").fill(name);
    const saved = page.waitForResponse((r) => r.url().includes("action=SurveyJS_SaveResult"));
    await page.getByRole("button", { name: "Complete" }).click();
    expect((await saved).ok()).toBe(true);
    const button = page.locator(".wp-sjs-addon-pdf-download button");
    await expect(button).toBeVisible();
    expectSurveyJsBranding(await clickedPdf(page, () => button.click()));

    const attachment = await mailPdf(request, `to:owner@example.test "${name}"`);
    expect(attachment).not.toBeNull();
    expectSurveyJsBranding(attachment);
  });
});
