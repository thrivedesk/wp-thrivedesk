'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const { parseChangelog, findBlock, renderBody, HEADER_RE } = require('./parse-changelog');

const SAMPLE = [
  '*** WooCommerce Extension Changelog ***',
  '',
  '2026-07-01 - version 2.4.1',
  '* fix: first thing',
  '* fix: second thing',
  '',
  '2026-06-29 - version 2.4.0',
  '* feat: a feature',
  '',
  '',
].join('\n');

test('drops the leading banner and does not treat it as a block', () => {
  const blocks = parseChangelog(SAMPLE);
  assert.equal(blocks.length, 2);
  assert.ok(!blocks.some((b) => b.version.includes('*')));
});

test('captures date, version, and body lines while excluding the header line', () => {
  const [first] = parseChangelog(SAMPLE);
  assert.equal(first.date, '2026-07-01');
  assert.equal(first.version, '2.4.1');
  assert.deepEqual(first.lines, ['* fix: first thing', '* fix: second thing']);
});

test('trims trailing blank lines from each block', () => {
  const blocks = parseChangelog(SAMPLE);
  const last = blocks[blocks.length - 1];
  assert.equal(last.lines[last.lines.length - 1], '* feat: a feature');
});

test('findBlock resolves a known version and returns null otherwise', () => {
  const blocks = parseChangelog(SAMPLE);
  assert.equal(findBlock(blocks, '2.4.0').version, '2.4.0');
  assert.equal(findBlock(blocks, '9.9.9'), null);
});

test('renderBody re-emits the header, a blank line, then the body, newline-terminated', () => {
  const block = findBlock(parseChangelog(SAMPLE), '2.4.1');
  assert.equal(
    renderBody(block),
    '2026-07-01 - version 2.4.1\n\n* fix: first thing\n* fix: second thing\n'
  );
});

test('a single-line block renders without a dangling blank body', () => {
  const one = parseChangelog('2026-03-10 - version 2.1.7\n* improve: one line\n');
  assert.equal(renderBody(one[0]), '2026-03-10 - version 2.1.7\n\n* improve: one line\n');
});

// Guards the real file against format drift: if a future header stops matching
// HEADER_RE its lines silently merge into the previous block, and the release
// note for that version comes out wrong. These checks fail loudly instead.
test('the shipped changelog.txt parses cleanly', () => {
  const text = fs.readFileSync(path.resolve(__dirname, '..', '..', 'changelog.txt'), 'utf8');
  const blocks = parseChangelog(text);

  assert.ok(blocks.length >= 4, 'expected the changelog to yield multiple version blocks');

  // Every header line the file actually contains must have become a block.
  const headerLines = text.split(/\r?\n/).filter((l) => HEADER_RE.test(l)).length;
  assert.equal(blocks.length, headerLines);

  // No orphan content ahead of the first header (banner/blank lines aside).
  const firstHeaderIdx = text.split(/\r?\n/).findIndex((l) => HEADER_RE.test(l));
  const preamble = text.split(/\r?\n/).slice(0, firstHeaderIdx);
  assert.ok(
    preamble.every((l) => l.trim() === '' || /^\*\*\*.*\*\*\*\s*$/.test(l)),
    'unexpected content before the first version header'
  );

  // Versions are unique, so a tag never resolves to an ambiguous block.
  const versions = blocks.map((b) => b.version);
  assert.equal(new Set(versions).size, versions.length, 'duplicate version headers');

  // Recent releases resolve to a non-empty body.
  for (const v of ['2.4.1', '2.4.0', '2.3.1']) {
    const block = findBlock(blocks, v);
    assert.ok(block, `expected a changelog block for ${v}`);
    assert.match(renderBody(block), /\n\* /, `expected bullet content for ${v}`);
  }
});
