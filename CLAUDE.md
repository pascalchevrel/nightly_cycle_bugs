# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A PHP CLI tool that extracts bug data for a Firefox Nightly release cycle by correlating Mozilla's Mercurial source control with Bugzilla. Given a release number, it fetches commit history from `hg.mozilla.org`, extracts bug IDs from commit messages, queries the Bugzilla REST API in batches, and outputs a consolidated JSON file.

## Running the Tool

```bash
./process.php 147              # Extract bugs for Nightly release 147
./process.php 147 --dry-run    # Fetch hg pushes and extract bug IDs only (no Bugzilla queries)
./process.php 147 -n           # Same as --dry-run
```

There is no build system, test framework, or linter configured.

## Architecture

**Data flow:**
```
hg.mozilla.org → local cache (data/json-pushes-nightly{N}.json)
    → Bugzilla::getBugsFromHgWeb() → unique bug IDs
    → bugzilla.mozilla.org REST API (batches of 150, 2s sleep between)
    → data/output/json-bugs-nightly{N}.json
```

The Mercurial query uses tag ranges: `FIREFOX_NIGHTLY_{N-1}_END` to `FIREFOX_NIGHTLY_{N}_END`.

**Key files:**
- `process.php` — CLI entry point; orchestrates fetch → extract → query → output
- `Bugzilla.php` — parses hg pushes to extract bug IDs (handles backouts, blocklist filtering); also has `getBugListLink()` and `linkify()` helpers
- `URL.php` — PHP enum defining Mozilla service endpoints (Bugzilla, Mercurial, BuildHub, etc.)
- `Utils.php` — HTTP fetching via Guzzle, string/date helpers, crash data utilities
- `Json.php` — JSON decode wrapper and HTTP response helpers (the HTTP response methods are unused by process.php)

**`data/` directory** is git-ignored; local JSON caches are reused on subsequent runs to avoid re-fetching from hg.mozilla.org.
