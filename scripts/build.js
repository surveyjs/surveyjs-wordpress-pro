const fs = require("fs");
const os = require("os");
const path = require("path");
const zlib = require("zlib");
const crypto = require("crypto");
const { execFileSync } = require("child_process");

const root = path.resolve(__dirname, "..");
const LOADER = "vip/client-mu-plugins/surveyjs-forms.php";
const NOT_IN_PRO_ZIP = [".github/", "vip/", "scripts/", "tests/", "node_modules/", "dist/", "README.md", "DEVELOPMENT.md", "composer.json", "package.json", "package-lock.json", "playwright.config.js", ".gitattributes", ".gitignore"];

function usage(message) {
  if (message) console.error(message);
  console.error(`Usage: node scripts/build.js [options]

  --ref <rev>          Pro commit or tag to build (default HEAD).
  --worktree           Build Pro from the working tree (tracked and untracked files, minus .gitignore) instead of --ref.
  --out <dir>          Output folder (default dist/).

Base (SurveyJS Forms) for the VIP archive, one of:
  (default)            https://downloads.wordpress.org/plugin/surveyjs.<Pro version>.zip
  --base-zip <file|url> A zip with one top folder surveyjs/, as on wordpress.org.
  --base-ref <rev>     src/trunk of the Base repo at <rev>.
  --base-worktree      src/trunk of the Base repo working tree.
  --base-repo <dir>    Base repo for --base-ref/--base-worktree (default ../surveyjs-wordpress).`);
  process.exit(1);
}

function parseArgs(argv) {
  const options = { ref: "HEAD", worktree: false, out: path.join(root, "dist"), baseRepo: path.resolve(root, "..", "surveyjs-wordpress") };
  for (let i = 0; i < argv.length; i++) {
    const value = () => (i + 1 < argv.length ? argv[++i] : usage(`${argv[i]} needs a value.`));
    switch (argv[i]) {
      case "--ref": options.ref = value(); break;
      case "--worktree": options.worktree = true; break;
      case "--out": options.out = path.resolve(value()); break;
      case "--base-zip": options.baseZip = value(); break;
      case "--base-ref": options.baseRef = value(); break;
      case "--base-worktree": options.baseWorktree = true; break;
      case "--base-repo": options.baseRepo = path.resolve(value()); break;
      default: usage(`Unknown option: ${argv[i]}`);
    }
  }
  if ([options.baseZip, options.baseRef, options.baseWorktree].filter(Boolean).length > 1) usage("Pass only one of --base-zip, --base-ref, --base-worktree.");
  return options;
}

function git(dir, args, env) {
  return execFileSync("git", ["-C", dir, ...args], { maxBuffer: 1 << 30, stdio: ["ignore", "pipe", "pipe"], env: { ...process.env, ...env } });
}

// git archive converts line endings like a checkout, so core.autocrlf=true would put CRLF files into the zip.
function archive(dir, args) {
  return git(dir, ["-c", "core.autocrlf=false", "-c", "core.eol=lf", "archive", "--format=zip", ...args]);
}

function worktreeTree(dir) {
  const index = path.join(os.tmpdir(), `surveyjs-build-${process.pid}-${Date.now()}.index`);
  const env = { GIT_INDEX_FILE: index };
  try {
    git(dir, ["read-tree", "HEAD"], env);
    git(dir, ["add", "--all", "--", "."], env);
    return git(dir, ["write-tree"], env).toString().trim();
  } finally {
    fs.rmSync(index, { force: true });
  }
}

function source(dir, ref, worktree) {
  if (worktree) return { treeish: worktreeTree(dir), label: "working tree" };
  if (git(dir, ["status", "--porcelain"]).length) console.warn(`Warning: ${dir} has uncommitted changes; they are not included.`);
  return { treeish: git(dir, ["rev-parse", "--verify", `${ref}^{commit}`]).toString().trim(), label: ref };
}

function readZip(buffer) {
  const end = buffer.lastIndexOf(Buffer.from([0x50, 0x4b, 0x05, 0x06]));
  if (end < 0) throw new Error("Not a zip file.");
  const count = buffer.readUInt16LE(end + 10);
  let p = buffer.readUInt32LE(end + 16);
  const entries = [];
  for (let i = 0; i < count; i++) {
    if (buffer.readUInt32LE(p) !== 0x02014b50) throw new Error("Broken zip central directory.");
    const entry = {
      madeBy: buffer.readUInt16LE(p + 4),
      method: buffer.readUInt16LE(p + 10),
      time: buffer.readUInt16LE(p + 12),
      date: buffer.readUInt16LE(p + 14),
      crc: buffer.readUInt32LE(p + 16),
      size: buffer.readUInt32LE(p + 24),
      attrs: buffer.readUInt32LE(p + 38)
    };
    const compressed = buffer.readUInt32LE(p + 20);
    const offset = buffer.readUInt32LE(p + 42);
    const nameLength = buffer.readUInt16LE(p + 28);
    entry.name = buffer.toString("utf8", p + 46, p + 46 + nameLength);
    if (compressed === 0xffffffff || offset === 0xffffffff) throw new Error(`${entry.name}: ZIP64 is not supported.`);
    if (entry.method !== 0 && entry.method !== 8) throw new Error(`${entry.name}: compression method ${entry.method} is not supported.`);
    const start = offset + 30 + buffer.readUInt16LE(offset + 26) + buffer.readUInt16LE(offset + 28);
    entry.raw = buffer.subarray(start, start + compressed);
    entries.push(entry);
    p += 46 + nameLength + buffer.readUInt16LE(p + 30) + buffer.readUInt16LE(p + 32);
  }
  return entries;
}

function entryData(entry) {
  return entry.method === 8 ? zlib.inflateRawSync(entry.raw) : entry.raw;
}

function newEntry(name, data, stamp) {
  const isDir = name.endsWith("/");
  const deflated = isDir ? data : zlib.deflateRawSync(data, { level: 9 });
  const stored = deflated.length >= data.length;
  return {
    name,
    madeBy: (3 << 8) | 20,
    method: stored ? 0 : 8,
    time: stamp.time,
    date: stamp.date,
    crc: zlib.crc32(data),
    size: data.length,
    attrs: isDir ? ((0o40755 << 16) | 0x10) >>> 0 : (0o100644 << 16) >>> 0,
    raw: stored ? data : deflated
  };
}

function writeZip(entries, file) {
  if (entries.length > 0xffff) throw new Error("Too many files for a zip without ZIP64.");
  const names = new Set();
  const locals = [];
  const centrals = [];
  let offset = 0;
  for (const entry of entries) {
    if (names.has(entry.name)) throw new Error(`Duplicate zip entry: ${entry.name}`);
    names.add(entry.name);
    const name = Buffer.from(entry.name, "utf8");
    const local = Buffer.alloc(30);
    local.writeUInt32LE(0x04034b50, 0);
    local.writeUInt16LE(20, 4);
    local.writeUInt16LE(0x0800, 6);
    local.writeUInt16LE(entry.method, 8);
    local.writeUInt16LE(entry.time, 10);
    local.writeUInt16LE(entry.date, 12);
    local.writeUInt32LE(entry.crc, 14);
    local.writeUInt32LE(entry.raw.length, 18);
    local.writeUInt32LE(entry.size, 22);
    local.writeUInt16LE(name.length, 26);
    locals.push(local, name, entry.raw);

    const central = Buffer.alloc(46);
    central.writeUInt32LE(0x02014b50, 0);
    central.writeUInt16LE(entry.madeBy, 4);
    central.writeUInt16LE(20, 6);
    central.writeUInt16LE(0x0800, 8);
    central.writeUInt16LE(entry.method, 10);
    central.writeUInt16LE(entry.time, 12);
    central.writeUInt16LE(entry.date, 14);
    central.writeUInt32LE(entry.crc, 16);
    central.writeUInt32LE(entry.raw.length, 20);
    central.writeUInt32LE(entry.size, 24);
    central.writeUInt16LE(name.length, 28);
    central.writeUInt32LE(entry.attrs, 38);
    central.writeUInt32LE(offset, 42);
    centrals.push(central, name);

    offset += local.length + name.length + entry.raw.length;
    if (offset > 0xffffffff) throw new Error("Zip is too large without ZIP64.");
  }
  const centralSize = centrals.reduce((size, buffer) => size + buffer.length, 0);
  const end = Buffer.alloc(22);
  end.writeUInt32LE(0x06054b50, 0);
  end.writeUInt16LE(entries.length, 8);
  end.writeUInt16LE(entries.length, 10);
  end.writeUInt32LE(centralSize, 12);
  end.writeUInt32LE(offset, 16);
  fs.writeFileSync(file, Buffer.concat([...locals, ...centrals, end]));
}

function moved(entries, from, to) {
  return entries
    .filter((entry) => entry.name.startsWith(from) && entry.name !== from)
    .map((entry) => ({ ...entry, name: to + entry.name.slice(from.length) }));
}

function withDirectories(entries, stamp) {
  const names = new Set(entries.map((entry) => entry.name));
  const result = [];
  for (const entry of entries) {
    const parts = entry.name.split("/");
    for (let i = 1; i < parts.length; i++) {
      const dir = parts.slice(0, i).join("/") + "/";
      if (dir !== entry.name && !names.has(dir)) {
        names.add(dir);
        result.push(newEntry(dir, Buffer.alloc(0), stamp));
      }
    }
    result.push(entry);
  }
  return result;
}

function text(entries, name) {
  const entry = entries.find((e) => e.name === name);
  if (!entry) throw new Error(`${name} is missing.`);
  return entryData(entry).toString("utf8");
}

function match(content, regex, what) {
  const found = content.match(regex);
  if (!found) throw new Error(`${what} not found.`);
  return found[1].trim();
}

function constant(content, name) {
  return match(content, new RegExp(`define\\(\\s*'${name}',\\s*'([^']+)'\\s*\\)`), name);
}

function compareVersions(a, b) {
  const pa = a.split(/[.-]/).map((part) => parseInt(part, 10) || 0);
  const pb = b.split(/[.-]/).map((part) => parseInt(part, 10) || 0);
  for (let i = 0; i < Math.max(pa.length, pb.length); i++) {
    if ((pa[i] || 0) !== (pb[i] || 0)) return (pa[i] || 0) - (pb[i] || 0);
  }
  return 0;
}

async function download(url) {
  const response = await fetch(url);
  if (!response.ok) throw new Error(`${url}: HTTP ${response.status}.`);
  return Buffer.from(await response.arrayBuffer());
}

async function baseEntries(options, version) {
  if (options.baseRef || options.baseWorktree) {
    const { treeish, label } = source(options.baseRepo, options.baseRef, options.baseWorktree);
    const zip = archive(options.baseRepo, [treeish, "src/trunk"]);
    return { entries: moved(readZip(zip), "src/trunk/", "plugins/surveyjs/"), label: `${options.baseRepo} ${label}` };
  }
  const zipSource = options.baseZip || `https://downloads.wordpress.org/plugin/surveyjs.${version}.zip`;
  let zip;
  try {
    zip = /^https?:\/\//.test(zipSource) ? await download(zipSource) : fs.readFileSync(zipSource);
  } catch (error) {
    const hint = options.baseZip ? "" : ` SurveyJS Forms ${version} may not be on wordpress.org yet: pass --base-ref <rev> (Base repo ${options.baseRepo}) or --base-zip <file>.`;
    throw new Error(`${error.message}${hint}`);
  }
  const entries = readZip(zip);
  const stray = entries.find((entry) => !entry.name.startsWith("surveyjs/"));
  if (stray) throw new Error(`${zipSource}: ${stray.name} is outside the surveyjs/ folder.`);
  return { entries: moved(entries, "surveyjs/", "plugins/surveyjs/"), label: zipSource };
}

function sha256(file) {
  return crypto.createHash("sha256").update(fs.readFileSync(file)).digest("hex");
}

function report(file) {
  const files = readZip(fs.readFileSync(file)).filter((entry) => !entry.name.endsWith("/")).length;
  console.log(`${file}: ${files} files, ${(fs.statSync(file).size / 1048576).toFixed(1)} MB`);
}

async function main() {
  const options = parseArgs(process.argv.slice(2));
  const pro = source(root, options.ref, options.worktree);
  const proZip = archive(root, ["--prefix=surveyjs-pro/", pro.treeish]);
  const proEntries = readZip(proZip);

  const main = text(proEntries, "surveyjs-pro/surveyjs-pro.php");
  const version = match(main, /^\s*Version:\s*(.+)$/m, "Version header in surveyjs-pro.php");
  const stable = match(text(proEntries, "surveyjs-pro/readme.txt"), /^Stable tag:\s*(.+)$/m, "Stable tag in readme.txt");
  if (version !== constant(main, "SURVEYJS_PRO_VERSION") || version !== stable) {
    throw new Error(`Version mismatch: header ${version}, SURVEYJS_PRO_VERSION ${constant(main, "SURVEYJS_PRO_VERSION")}, readme.txt Stable tag ${stable}.`);
  }
  const leaked = proEntries.find((entry) => NOT_IN_PRO_ZIP.some((name) => entry.name === `surveyjs-pro/${name}` || (name.endsWith("/") && entry.name.startsWith(`surveyjs-pro/${name}`))));
  if (leaked) throw new Error(`${leaked.name} must not be in the Pro zip (add it to .gitattributes export-ignore).`);
  console.log(`SurveyJS Forms Pro ${version} (${pro.label})`);

  const base = await baseEntries(options, version);
  const baseMain = text(base.entries, "plugins/surveyjs/surveyjs.php");
  const baseVersion = match(baseMain, /^\s*Version:\s*(.+)$/m, "Version header in surveyjs.php");
  const minBase = constant(main, "SURVEYJS_PRO_MIN_BASE_VERSION");
  const library = constant(main, "SURVEYJS_PRO_LIBRARY_VERSION");
  console.log(`SurveyJS Forms ${baseVersion} (${base.label})`);
  if (compareVersions(baseVersion, minBase) < 0) throw new Error(`SurveyJS Forms ${baseVersion} is older than SURVEYJS_PRO_MIN_BASE_VERSION ${minBase}.`);
  const baseLibrary = match(text(base.entries, "plugins/surveyjs/libs/library/survey.core.min.js").slice(0, 300), / v(\d+\.\d+\.\d+\S*)/, "SurveyJS library version in Base");
  if (baseLibrary !== library) throw new Error(`SurveyJS Forms ${baseVersion} ships SurveyJS ${baseLibrary}, Pro is built for ${library}.`);

  const proStamp = proEntries.find((entry) => entry.name === "surveyjs-pro/surveyjs-pro.php");
  let loader;
  try {
    loader = git(root, ["show", `${pro.treeish}:${LOADER}`]);
  } catch {
    throw new Error(`${LOADER} is not in ${pro.label}.`);
  }
  fs.mkdirSync(options.out, { recursive: true });
  const proFile = path.join(options.out, `surveyjs-pro-${version}.zip`);
  const vipFile = path.join(options.out, `surveyjs-forms-vip-${version}.zip`);
  fs.writeFileSync(proFile, proZip);
  writeZip(withDirectories([
    ...base.entries,
    ...moved(proEntries, "", "plugins/"),
    newEntry("client-mu-plugins/surveyjs-forms.php", loader, proStamp)
  ], proStamp), vipFile);

  const sums = [proFile, vipFile].map((file) => `${sha256(file)}  ${path.basename(file)}`).join("\n") + "\n";
  fs.writeFileSync(path.join(options.out, "SHA256SUMS"), sums);
  report(proFile);
  report(vipFile);
}

main().catch((error) => {
  console.error(error.message);
  process.exit(1);
});
