## Get data from all bugs in a Firefox development cycle

In the terminal:

```
./process.php 147
----------------------------------------------------------------------
Firefox Nightly Bug Extraction Tool
  - Fetches hg json-pushes for Nightly cycle
  - Extracts bug IDs from pushlog
  - Queries Bugzilla in batches
  - Produces a single JSON file
----------------------------------------------------------------------
Nightly release: 147
From tag: FIREFOX_NIGHTLY_146_END
To tag:   FIREFOX_NIGHTLY_147_END
Fetching hg pushes JSON from:
  https://hg.mozilla.org/mozilla-central/json-pushes?fromchange=FIREFOX_NIGHTLY_146_END&tochange=FIREFOX_NIGHTLY_147_END&full&version=2
Saved hg log to: /home/pascal/repos/github/nightly_cycle_bugs/data/json-pushes-nightly147.json
Log parsing done. Found 1765 unique bug IDs.
Start querying Bugzilla (12 chunks)…
  Chunk 1/12 → requesting 150 bugs…
    OK – total bugs collected so far: 145
  Chunk 2/12 → requesting 150 bugs…
    OK – total bugs collected so far: 293
  Chunk 3/12 → requesting 150 bugs…
    OK – total bugs collected so far: 442
  Chunk 4/12 → requesting 150 bugs…
    OK – total bugs collected so far: 591
  Chunk 5/12 → requesting 150 bugs…
    OK – total bugs collected so far: 735
  Chunk 6/12 → requesting 150 bugs…
    OK – total bugs collected so far: 880
  Chunk 7/12 → requesting 150 bugs…
    OK – total bugs collected so far: 1026
  Chunk 8/12 → requesting 150 bugs…
    OK – total bugs collected so far: 1169
  Chunk 9/12 → requesting 150 bugs…
    OK – total bugs collected so far: 1318
  Chunk 10/12 → requesting 150 bugs…
    OK – total bugs collected so far: 1466
  Chunk 11/12 → requesting 150 bugs…
    OK – total bugs collected so far: 1613
  Chunk 12/12 → requesting 115 bugs…
    OK – total bugs collected so far: 1726
Exported JSON: /home/pascal/repos/github/nightly_cycle_bugs/data/output/json-bugs-nightly147.json
Done.
```
