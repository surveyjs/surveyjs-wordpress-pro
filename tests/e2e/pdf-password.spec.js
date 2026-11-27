const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");
const { test, expect } = require("@playwright/test");

const WP_CONTAINER = process.env.WP_CONTAINER;
const MAILPIT_URL = process.env.MAILPIT_URL;
const PASSWORD = "Test-1234";

function fixture(...args) {
  const output = execFileSync("docker", ["exec", "-i", WP_CONTAINER, "php", "--", ...args.map(String)], {
    input: fs.readFileSync(path.join(__dirname, "fixtures/pdf-password.php")),
    stdio: ["pipe", "pipe", "ignore"]
  });
  return JSON.parse(output.toString().trim().split("\n").pop());
}

function encryption(buffer) {
  const text = buffer.toString("latin1");
  return {
    pdf: text.startsWith("%PDF-"),
    encrypted: /\/Encrypt \d+ 0 R/.test(text) && /\/V 5\b/.test(text) && /\/R 6\b/.test(text) && text.includes("/AESV3"),
    plainInfo: text.includes("(jsPDF")
  };
}

const ENCRYPTED = { pdf: true, encrypted: true, plainInfo: false };
const PLAIN = { pdf: true, encrypted: false, plainInfo: true };

function unzip(buffer) {
  const files = [];
  let offset = 0;
  while (buffer.readUInt32LE(offset) === 0x04034b50) {
    const size = buffer.readUInt32LE(offset + 18);
    const nameLength = buffer.readUInt16LE(offset + 26);
    const extraLength = buffer.readUInt16LE(offset + 28);
    const start = offset + 30 + nameLength + extraLength;
    files.push({ name: buffer.toString("utf8", offset + 30, offset + 30 + nameLength), data: buffer.subarray(start, start + size) });
    offset = start + size;
  }
  return files;
}

async function clickEntryPdf(page) {
  const row = page.locator("#the-list tr").first();
  await row.hover();
  await row.locator(".wp-sjs-addon-pdf-entry").click();
}

async function download(page, click) {
  const [file] = await Promise.all([page.waitForEvent("download"), click()]);
  return { name: file.suggestedFilename(), data: fs.readFileSync(await file.path()) };
}

async function save(page) {
  const saved = page.waitForResponse(r => r.url().includes("action=SurveyJS_SaveSurvey"));
  await page.getByRole("button", { name: "Save", exact: true }).click();
  await saved;
  await expect(page.locator(".wp-sjs-snackbars")).toContainText("Form saved");
}

async function mail(request, query) {
  for (let attempt = 0; attempt < 30; attempt++) {
    const search = await (await request.get(`${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(query)}`)).json();
    if (search.messages.length) {
      const message = await (await request.get(`${MAILPIT_URL}/api/v1/message/${search.messages[0].ID}`)).json();
      const pdf = message.Attachments.find((part) => part.FileName.endsWith(".pdf"));
      if (!pdf) return null;
      return Buffer.from(await (await request.get(`${MAILPIT_URL}/api/v1/message/${message.ID}/part/${pdf.PartID}`)).body());
    }
    await new Promise((resolve) => setTimeout(resolve, 1000));
  }
  throw new Error(`No email for ${query}`);
}

test.describe("PDF password", () => {
  test.skip(!WP_CONTAINER, "Set WP_CONTAINER to the WordPress container name to create the test form");

  let form = null;
  const editorUrl = () => `/wp-admin/admin.php?page=surveyjs_editor&id=${form.id}&tab=pdf`;
  const resultsUrl = () => `/wp-admin/admin.php?page=surveyjs_results&id=${form.id}`;

  test.beforeAll(() => {
    form = fixture("create", `E2E PDF Password ${Date.now()}`);
  });

  test.afterAll(() => {
    if (form) fixture("cleanup", form.id, form.page);
  });

  test("the PDF tab saves the password without printing it back", async ({ page }) => {
    await page.goto(editorUrl());
    const toggle = page.locator('input[data-setting="addonPdfPassword"]');
    const input = page.locator("#wp-sjs-addon-pdf-password");
    await expect(input).toBeDisabled();
    await toggle.check();
    await input.fill(PASSWORD);
    await save(page);
    await expect(page.locator("#wp-sjs-addon-pdf-status")).toHaveText("PDF password saved.");
    await expect(input).toHaveValue("********");

    const stored = fixture("password", form.id, PASSWORD);
    expect(stored).toEqual({ stored: true, readable: false, password: PASSWORD, enabled: true });

    const response = await page.goto(editorUrl());
    expect(await response.text()).not.toContain(PASSWORD);
    await expect(input).toHaveValue("********");
    await expect(input).toHaveAttribute("type", "password");
  });

  test("the editor encrypts the blank form download but not the preview", async ({ page }) => {
    await page.goto(editorUrl());
    const blank = await download(page, () => page.locator("#wp-sjs-addon-pdf-blank").click());
    expect(encryption(blank.data)).toEqual(ENCRYPTED);

    await expect(page.locator("#wp-sjs-addon-pdf-live")).toHaveAttribute("src", /^blob:/);
    const preview = await page.evaluate(async () => {
      const response = await fetch(document.getElementById("wp-sjs-addon-pdf-live").src);
      return Array.from(new Uint8Array(await response.arrayBuffer()));
    });
    expect(encryption(Buffer.from(preview))).toEqual(PLAIN);
  });

  test("the results page encrypts the entry PDF, the blank form and every PDF in the ZIP", async ({ page }) => {
    await page.goto(resultsUrl());
    const entry = await download(page, () => clickEntryPdf(page));
    expect(entry.name).toMatch(/-entry-\d+\.pdf$/);
    expect(encryption(entry.data)).toEqual(ENCRYPTED);

    const blank = await download(page, () => page.locator(".wp-sjs-addon-pdf-blank").click());
    expect(encryption(blank.data)).toEqual(ENCRYPTED);

    await page.locator("#cb-select-all-1").check();
    await page.locator("#bulk-action-selector-top").selectOption("addon_pdf");
    const archive = await download(page, () => page.locator("#doaction").click());
    expect(archive.name).toMatch(/-entries\.zip$/);
    const files = unzip(archive.data);
    expect(files).toHaveLength(2);
    files.forEach((file) => expect(encryption(file.data)).toEqual(ENCRYPTED));
  });

  test("the encrypt endpoint checks the nonce and the body", async ({ page }) => {
    await page.goto(resultsUrl());
    const statuses = await page.evaluate(async () => {
      const url = new URL(window.surveyjsAddonPdf.password.encryptUrl, window.location.href);
      const post = (target, body) => fetch(target, { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/pdf" }, body }).then((r) => r.status);
      const wrongNonce = new URL(url);
      wrongNonce.searchParams.set("_ajax_nonce", "wrong");
      return {
        wrongNonce: await post(wrongNonce, "%PDF-1.3"),
        notPdf: await post(url, "<html></html>"),
        brokenPdf: await post(url, "%PDF-1.3\n1 0 obj\n<<>>\nendobj\n")
      };
    });
    expect(statuses).toEqual({ wrongNonce: 403, notPdf: 400, brokenPdf: 422 });
  });

  test("the email notification gets an encrypted PDF, the auto-reply a plain one", async ({ browser, request }) => {
    test.skip(!MAILPIT_URL, "Set MAILPIT_URL to the Mailpit address to check the emails");
    const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    const page = await context.newPage();
    const email = `respondent-${Date.now()}@example.test`;
    await page.goto(form.url);
    await page.getByLabel("Имя и фамилия").fill("Мария Иванова");
    await page.getByLabel("Email").fill(email);
    const saved = page.waitForResponse((r) => r.url().includes("action=SurveyJS_SaveResult"));
    await page.getByRole("button", { name: "Complete" }).click();
    expect((await saved).ok()).toBe(true);
    await context.close();

    expect(encryption(await mail(request, `to:owner@example.test "${email}"`))).toEqual(ENCRYPTED);
    expect(encryption(await mail(request, `to:${email}`))).toEqual(PLAIN);
  });

  test("turning the option off removes the password", async ({ page }) => {
    await page.goto(editorUrl());
    await page.locator('input[data-setting="addonPdfPassword"]').uncheck();
    await save(page);
    await expect(page.locator("#wp-sjs-addon-pdf-status")).toContainText("PDF password removed.");
    expect(fixture("password", form.id, PASSWORD)).toEqual({ stored: false, readable: false, password: "", enabled: false });

    await page.goto(resultsUrl());
    const entry = await download(page, () => clickEntryPdf(page));
    expect(encryption(entry.data)).toEqual(PLAIN);
  });
});
