# opencc-pinyin Agent Guide

## Project Overview

This repository provides OpenCC text dictionaries and configurations for converting Chinese characters to Hanyu Pinyin.

- `pinyin.json` converts Chinese characters to pinyin with tone marks.
- `pinyin_notone.json` runs OpenCC conversion with CJK compatibility normalization, then `phrase_pinyin.txt`, then `pinyin.txt`, then `tone_removal.txt` to remove tone marks.
- `third_party/pinyin-data/zdic.txt` is the upstream source data from `mozillazg/pinyin-data`, scraped from zdic.net.
- `third_party/pinyin-data/LICENSE` is the upstream pinyin-data MIT license.
- `third_party/phrase-pinyin-data/large_pinyin.txt` is the upstream merged phrase-pinyin data from `mozillazg/phrase-pinyin-data`. It is the source data for generating `phrase_pinyin.txt`.
- `third_party/phrase-pinyin-data/LICENSE` is the upstream phrase-pinyin-data MIT license.
- `third_party/OpenCC/CJK_Compatibility_Ideographs.txt` is vendored from OpenCC and is used as the CJK Compatibility Ideograph normalization pre-pass.
- `third_party/OpenCC/LICENSE` is the upstream OpenCC Apache-2.0 license.
- `gen_dict.py` generates `pinyin.txt` from `third_party/pinyin-data/zdic.txt`.
- `gen_phrase_dict.py` generates `phrase_pinyin.txt` from `third_party/phrase-pinyin-data/large_pinyin.txt`, keeping only multi-character phrases that contain at least one polyphonic character from `pinyin.txt`.
- `gen_packed_dict.py` generates the packed dataset under `dist/php/` from `pinyin.txt` and `tone_removal.txt`: a tone-marked syllable value table (`syllables.php`), the tone-stripping map (`tone_map.php`, marked letter => base letter), uint16-per-codepoint binary strips (`bmp.bin` for U+3400..U+9FFF, `supp.bin` for U+20000..U+2EBEF; `0xFFFF` = no reading), out-of-strip entries (`extra.php`), and the strip layout (`meta.php`). Only the first reading of each character is kept, **with tone marks**; toneless output is produced on the loader side via `tone_map.php`, so the binary strips (the bulk of the dataset) are unchanged from the toneless layout — their size depends only on the codepoint ranges, not on whether syllables carry tones. The dataset is complete and policy-free — every character in `pinyin.txt` is included; input policies (script or variant restrictions) belong on the consumer side, not in this data.
- `php/src/PinyinData.php` (composer package `frankslin/opencc-pinyin`, class `OpenccPinyin\PinyinData`) is the O(1) codepoint-addressed loader over `dist/php/`. Accessors (`lookup`, `syllables`, `toPinyin`) return tone-marked pinyin by default and accept a `$tone = false` argument for the toneless form (ü kept as ü). `setOverrides(char => reading)` registers caller overrides that win over the packed data (for pinning a polyphone's reading or supplying a missing one); overrides honor the tone flag. `php/verify_packed_dict.php` round-trips every `pinyin.txt` entry against the loader in both tone modes and checks the override path; run it after regenerating `dist/php/`.
- `phrase_pinyin.txt` is an OpenCC phrase dictionary for polyphonic phrase overrides. It must appear before `pinyin.txt` in both OpenCC configs.
- `tone_removal.txt` maps tone-marked pinyin letters to their no-tone forms. Keep `ü` as `ü`; do not normalize it to `u`.

This is a character-level dictionary. It does not segment words or disambiguate polyphonic characters by context. For polyphonic entries, preserve the reading order from `third_party/pinyin-data/zdic.txt`; OpenCC will use the first candidate by default.

## Data Flow

The canonical data flow is:

1. Edit or update `third_party/pinyin-data/zdic.txt`.
2. Run `python3 gen_dict.py` to regenerate `pinyin.txt`.
3. Keep `tone_removal.txt` complete for every tone-marked/non-base pinyin letter that appears in `pinyin.txt`.
4. Keep `pinyin.json` and `pinyin_notone.json` wired to the OpenCC CJK compatibility normalization dictionary.
5. Run `python3 gen_phrase_dict.py` to regenerate `phrase_pinyin.txt` from `third_party/phrase-pinyin-data/large_pinyin.txt`.
6. Keep `third_party/phrase-pinyin-data/large_pinyin.txt` as upstream source material.
7. Use OpenCC's dictionary sort tool for OpenCC text dictionaries.
8. Run `python3 gen_packed_dict.py` to regenerate `dist/php/` whenever `pinyin.txt` or `tone_removal.txt` changes, then verify with `php php/verify_packed_dict.php`.

Do not hand-edit `pinyin.txt` for source-data changes unless there is a narrow, intentional reason. Prefer changing `third_party/pinyin-data/zdic.txt` and regenerating.

## Dictionary Format

- `third_party/pinyin-data/zdic.txt` lines use `U+XXXX: reading1,reading2  # character`; lines beginning with `# U+...` are placeholders for characters without pinyin.
- `pinyin.txt` lines are `character<TAB>pinyin`, with multiple readings separated by spaces.
- `phrase_pinyin.txt` lines are `phrase<TAB>pinyin`, with multiple full-phrase readings separated by spaces. Do not keep syllable spaces inside one phrase reading; OpenCC treats spaces as candidate separators.
- `tone_removal.txt` lines are `marked-letter<TAB>base-letter`.
- `third_party/OpenCC/CJK_Compatibility_Ideographs.txt` lines are `compatibility-character<TAB>unified-character`; preserve the upstream OpenCC comments and Apache-2.0 license notice.
- `third_party/phrase-pinyin-data/large_pinyin.txt` lines are `phrase: syllable1 syllable2 ...`; lines beginning with `#` are upstream metadata or comments. Preserve the upstream file as vendored source data unless intentionally updating it from upstream.
- Preserve tabs in dictionary files. Do not replace dictionary separators with spaces.
- Preserve comment alignment in `third_party/pinyin-data/zdic.txt` when moving lines; do not reformat entries unless the task explicitly asks for it.

## Sorting Rules

- `third_party/pinyin-data/zdic.txt` should be ordered by numeric Unicode code point, not by raw string order. This matters for code points such as `U+2FA2` versus `U+2FA00`.
- Keep section headers consistent with the code point ranges they introduce.
- If duplicate `U+...` placeholder lines are found, prefer the complete section for that range and avoid duplicate code points.
- Sort OpenCC text dictionaries with the OpenCC helper:

```sh
python3 ../OpenCC/data/scripts/sort.py pinyin.txt pinyin.txt
python3 ../OpenCC/data/scripts/sort.py phrase_pinyin.txt phrase_pinyin.txt
python3 ../OpenCC/data/scripts/sort.py tone_removal.txt tone_removal.txt
```

`pinyin.txt` must still match `gen_dict.py` output after sorting. If sorting changes generated order, understand why before committing.
For `phrase_pinyin.txt`, generate a temporary output, sort that temporary output with OpenCC's sort tool, then compare it with the checked-in file.

## Normalization Config

Both OpenCC configs must keep the same CJK Compatibility Ideograph normalization pre-pass:

```json
"normalization": [
  {
    "dict": {
      "type": "text",
      "file": "third_party/OpenCC/CJK_Compatibility_Ideographs.txt"
    }
  }
]
```

This mirrors OpenCC's built-in configs and normalizes compatibility ideographs before pinyin lookup or tone removal. Keep this top-level `normalization` field, and do not repeat the same CJK dictionary in `conversion_chain`. Do not add `segmentation` for these configs; pinyin conversion is character-level and does not need an mmseg pass.

## Tone Removal Coverage

When editing `tone_removal.txt`, verify that it covers every non-ASCII pinyin letter in `pinyin.txt` except bare `ü`, which is the intended no-tone form.

Useful coverage check:

```sh
python3 - <<'PY'
from pathlib import Path

pinyin_chars = set()
for line in Path('pinyin.txt').read_text(encoding='utf-8').splitlines():
    if '\t' not in line:
        continue
    _, values = line.split('\t', 1)
    for ch in values:
        if ch.isalpha() and ord(ch) > 127:
            pinyin_chars.add(ch)

tone_removal = {}
for line in Path('tone_removal.txt').read_text(encoding='utf-8').splitlines():
    if not line.strip():
        continue
    key, value = line.split('\t')
    tone_removal[key] = value

missing = sorted(ch for ch in pinyin_chars if ch != 'ü' and ch not in tone_removal)
print('missing:', ''.join(missing) if missing else '(none)')
PY
```

Examples that must be handled:

- `ǹg ńg ňg ǹ ń ň` should become `ng ng ng n n n`.
- `ḿ` should become `m`.

## Validation Commands

Before finishing a data change, run the relevant checks:

```sh
python3 gen_dict.py third_party/pinyin-data/zdic.txt /tmp/opencc-pinyin.generated.txt
cmp -s pinyin.txt /tmp/opencc-pinyin.generated.txt
git diff --check
```

For `third_party/pinyin-data/zdic.txt` ordering changes, also verify numeric order and uniqueness:

```sh
python3 - <<'PY'
import re
from collections import Counter

pat = re.compile(r'^#?\s*U\+([0-9A-Fa-f]+):')
prev = None
regressions = []
codepoints = []

for lineno, line in enumerate(open('third_party/pinyin-data/zdic.txt', encoding='utf-8'), 1):
    match = pat.match(line)
    if not match:
        continue
    codepoint = int(match.group(1), 16)
    codepoints.append(codepoint)
    if prev and codepoint < prev[0]:
        regressions.append((prev[1], prev[0], lineno, codepoint))
    prev = (codepoint, lineno)

print('codepoint lines:', len(codepoints))
print('unique codepoints:', len(set(codepoints)))
print('duplicates:', sum(count > 1 for count in Counter(codepoints).values()))
print('regressions:', len(regressions))
PY
```

## Commit Guidance

- Keep commits focused. Source changes in `third_party/pinyin-data/zdic.txt`, regenerated `pinyin.txt`, and related `tone_removal.txt` fixes can be committed together when they are part of the same data maintenance task.
- Keep vendored third-party data and its license together under `third_party/<project>/`.
- Mention whether `pinyin.txt` was regenerated.
- If using commit-message rules from a sibling OpenCC checkout, follow `../OpenCC/AGENTS.md`.
- Do not include editor swap files such as `.zdic.txt.swp` or `.AGENTS.md.swp`.
