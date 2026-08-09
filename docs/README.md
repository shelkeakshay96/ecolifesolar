# docs/

| File | What it is |
|---|---|
| `technical-add.md` | **Source of truth.** The Phase 1 Application Design Document, v2.0. Edit this. |
| `build-docx.py` | Renders the Markdown into Word. Handles headings, tables, fenced code, lists, blockquotes and inline emphasis — nothing else. |
| `EcoLifeSolar-ADD-Phase1-v2.0.docx` | Generated deliverable. Committed so a reader gets the file without the toolchain. **Never edit directly** — it is overwritten on every build. |

## Regenerating

```bash
python3 -m venv docs/.venv
docs/.venv/bin/pip install python-docx
docs/.venv/bin/python docs/build-docx.py
```

The venv is required because Python 3.12 on Ubuntu is marked `EXTERNALLY-MANAGED`; a plain
`pip3 install` is refused. `docs/.venv/` is gitignored — recreate it on any new machine with the
three lines above.

`python-docx` is a **documentation-build dependency only**. It has no bearing on the application,
which remains free of Composer and npm.

## Editing rules

- Change `technical-add.md`, then rebuild. Never patch the `.docx`.
- Bump the version in the revision-history table at the top of the Markdown *and* in `COVER_FACTS`
  in `build-docx.py`, and rename `OUTPUT` to match, whenever the design changes materially.
- The Markdown subset is small on purpose. If you need a construct the renderer does not support,
  extend `build-docx.py` rather than hand-editing Word — otherwise the source of truth and the
  deliverable drift apart, which is the exact failure this setup exists to prevent.
