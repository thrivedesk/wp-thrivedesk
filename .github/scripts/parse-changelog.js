#!/usr/bin/env node
/**
 * Parse the top-level changelog.txt and create or update a GitHub Release
 * whose body is the block matching the version implied by TAG (e.g. v2.4.1).
 *
 * Environment:
 *   TAG        - required, the git tag (e.g. v2.4.1). The leading "v" is stripped.
 *   GH_TOKEN   - required for `gh` to authenticate. Provided by the workflow.
 *
 * Flags:
 *   --dry-run  - print the parsed body and the gh commands, do not execute them.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');
const os = require('os');

const CHANGELOG_PATH = path.resolve(__dirname, '..', '..', 'changelog.txt');
const HEADER_RE = /^(\d{4}-\d{2}-\d{2})\s*-\s*version\s+(\d+\.\d+\.\d+)\s*$/;
const BANNER_RE = /^\*\*\*.*\*\*\*\s*$/;

function fail(msg, code = 1) {
  process.stderr.write(`parse-changelog: ${msg}\n`);
  process.exit(code);
}

function parseChangelog(text) {
  const lines = text.split(/\r?\n/);

  // Drop a leading banner line if present, e.g. "*** WooCommerce Extension Changelog ***".
  let start = 0;
  if (lines.length > 0 && BANNER_RE.test(lines[0])) {
    start = 1;
  }

  const blocks = [];
  let current = null;

  for (let i = start; i < lines.length; i++) {
    const line = lines[i];
    const m = line.match(HEADER_RE);
    if (m) {
      if (current) blocks.push(current);
      current = { date: m[1], version: m[2], lines: [] };
      continue;
    }
    if (current) current.lines.push(line);
  }
  if (current) blocks.push(current);

  // Trim trailing blank lines from each block.
  for (const b of blocks) {
    while (b.lines.length > 0 && b.lines[b.lines.length - 1].trim() === '') {
      b.lines.pop();
    }
  }

  return blocks;
}

function findBlock(blocks, version) {
  return blocks.find((b) => b.version === version) || null;
}

function renderBody(block) {
  const header = `${block.date} - version ${block.version}`;
  return [header, '', ...block.lines].join('\n').trimEnd() + '\n';
}

function main() {
  const dryRun = process.argv.includes('--dry-run');
  const tag = process.env.TAG;
  if (!tag) fail('TAG env var is required (e.g. v2.4.1).');
  const version = tag.replace(/^v/, '');
  if (!/^\d+\.\d+\.\d+$/.test(version)) {
    fail(`TAG "${tag}" does not look like a semver tag (expected v<major>.<minor>.<patch>).`);
  }

  if (!fs.existsSync(CHANGELOG_PATH)) {
    fail(`changelog.txt not found at ${CHANGELOG_PATH}.`);
  }
  const text = fs.readFileSync(CHANGELOG_PATH, 'utf8');
  const blocks = parseChangelog(text);
  if (blocks.length === 0) {
    fail('No version blocks found in changelog.txt.');
  }

  const block = findBlock(blocks, version);
  if (!block) {
    const known = blocks.map((b) => b.version).join(', ');
    fail(
      `No changelog entry for version ${version}. Known versions: ${known}. ` +
        'Add a block to changelog.txt or push a tag that matches an existing block.'
    );
  }

  const body = renderBody(block);
  const tmp = path.join(os.tmpdir(), `release-notes-${version}.md`);
  fs.writeFileSync(tmp, body, 'utf8');

  if (dryRun) {
    process.stdout.write(`--- parsed block for ${version} ---\n`);
    process.stdout.write(body);
    process.stdout.write(`--- would run: gh release edit ${tag} --notes-file ${tmp}\n`);
    process.stdout.write(`--- fallback:    gh release create ${tag} --title ${tag} --notes-file ${tmp}\n`);
    return;
  }

  const ghToken = process.env.GH_TOKEN;
  if (!ghToken) fail('GH_TOKEN env var is required.');

  const env = { ...process.env, GH_TOKEN: ghToken };

  const edit = spawnSync(
    'gh',
    ['release', 'edit', tag, '--notes-file', tmp],
    { env, stdio: ['ignore', 'pipe', 'pipe'] }
  );
  if (edit.status === 0) {
    process.stdout.write(`Updated existing release ${tag}\n`);
    return;
  }

  // gh release edit failed - most likely the release does not exist yet. Try create.
  const create = spawnSync(
    'gh',
    ['release', 'create', tag, '--title', tag, '--notes-file', tmp],
    { env, stdio: ['inherit', 'inherit', 'inherit'] }
  );
  if (create.status !== 0) {
    process.stderr.write(
      `gh release create failed; here is the stderr from the edit attempt for diagnostics:\n${edit.stderr.toString()}\n`
    );
    process.exit(create.status || 1);
  }
  process.stdout.write(`Created release ${tag}\n`);
}

if (require.main === module) {
  main();
}

module.exports = { parseChangelog, findBlock, renderBody, HEADER_RE, BANNER_RE };
