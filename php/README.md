# frankslin/opencc-pinyin

> O(1) 碼位定址的漢語拼音查詢庫 · O(1) codepoint-addressed Hanyu Pinyin lookup for PHP

繁體中文 ｜ [English](#english)

---

## 繁體中文

將單個漢字或整段中文，查為漢語拼音。資料源自漢典網（[mozillazg/pinyin-data](https://github.com/mozillazg/pinyin-data)，抓取自 zdic.net），打包成緊湊的二進制資料集，配一個純 PHP 的載入器。**預設輸出帶聲調**拼音，也可一鍵切換為無聲調。

- **O(1) 查詢**：每個漢字用碼位直接定址到 uint16 索引，不做線性掃描、不建雜湊表。
- **帶聲調 / 無聲調**：預設 `sū`，傳 `$tone = false` 得 `su`（`ü` 保持為 `ü`，不併入 `u`）。
- **零依賴**：僅需 PHP ≥ 8.2 與 `ext-mbstring`，不依賴 OpenCC 執行檔。
- **覆蓋完整**：涵蓋 `pinyin.txt` 全部 42083 個漢字（CJK 基本區、擴充 A–F、兼容表意文字、康熙部首等）。

> ⚠️ 這是**字元級**字典，只取每個漢字的**首個讀音**，不做分詞、不按上下文消歧多音字。需要精準讀音的場景，請在你自己這一側疊加詞語詞典後再回退到本庫。

### 安裝

```bash
composer require frankslin/opencc-pinyin
```

**環境需求**：PHP ≥ 8.2、`ext-mbstring`。

### 快速上手

```php
require 'vendor/autoload.php';

use OpenccPinyin\PinyinData;

// 單字查詢（預設帶聲調）
PinyinData::lookup('蘇');           // "sū"
PinyinData::lookup('蘇', false);    // "su"   —— 無聲調
PinyinData::lookup('a');            // null   —— 非漢字 / 無讀音

// 整段轉換
PinyinData::toPinyin('蘇軾與蘇轍');                // "sūshìyǔsūzhé"  預設不加分隔符
PinyinData::toPinyin('蘇軾與蘇轍', ' ');           // "sū shì yǔ sū zhé"
PinyinData::toPinyin('蘇軾與蘇轍', ' ', false);    // "su shi yu su zhe"
PinyinData::toPinyin('東坡，一二三', '-');          // "dōng-pō-，-yī-èr-sān"

// 逐字拆分（保留 null 邊界，方便自行決定拼接規則）
PinyinData::syllables('蘇A東');
// [
//   ['char' => '蘇', 'pinyin' => 'sū'],
//   ['char' => 'A',  'pinyin' => null],   // 非漢字保留 null
//   ['char' => '東', 'pinyin' => 'dōng'],
// ]
```

### API

所有方法均為靜態方法，末位 `bool $tone = true` 控制是否帶聲調（`false` 即無聲調）。

| 方法 | 說明 |
|------|------|
| `lookup(string $char, bool $tone = true): ?string` | 單個漢字的首個讀音；無讀音或非漢字輸入回傳 `null`。 |
| `syllables(string $text, bool $tone = true): array` | 逐字拆分為 `['char' => …, 'pinyin' => ?string]`，無讀音處 `pinyin` 為 `null`。 |
| `toPinyin(string $text, string $separator = '', bool $tone = true): string` | 用 `$separator` 拼接（**預設不加分隔符**，需空格請傳 `' '`）；無讀音字元原樣保留。 |
| `setOverrides(array $map): void` | 註冊 `漢字 => 讀音` 覆蓋表，優先於打包資料（見下）。 |
| `reset(): void` | 清除已載入的資料與覆蓋表（測試用）。 |

無聲調輸出是在載入器側，用 `dist/php/tone_map.php` 於執行時剝離聲調產生的；`ü` 依規範保留為 `ü`。

### 手動覆蓋

打包資料每個漢字只保留**首個讀音**，未必符合你的領域。用 `setOverrides()` 傳一個 `漢字 => 讀音` 的 map，命中時優先回傳你要的結果——既可糾正多音字，也可為資料集沒有的字補讀音。

```php
PinyinData::lookup('重');                      // "zhòng" —— 資料集首讀音
PinyinData::setOverrides(['重' => 'chóng']);    // 你的領域要「重複」的 chóng
PinyinData::lookup('重');                      // "chóng"
PinyinData::lookup('重', false);               // "chong" —— 覆蓋值同樣走去聲調

PinyinData::setOverrides([]);                  // 傳空陣列即清除
```

覆蓋值請**帶聲調**（與資料集一致）；`$tone = false` 時會用同一張聲調表剝離。`setOverrides()` 為整體替換而非合併。

### 資料集與體積

儲存的是**帶聲調**首讀音，但二進制條帶存的是「音節值表」的 uint16 索引，其大小只取決於碼位範圍，**與是否帶聲調無關**——所以帶聲調只讓值表（`syllables.php`）變大，二進制主體不變。

| 檔案 | 大小 | 內容 |
|------|------|------|
| `dist/php/bmp.bin` | 55,296 B | U+3400–U+9FFF 每碼位一個 uint16 索引（`0xFFFF` = 無讀音） |
| `dist/php/supp.bin` | 120,800 B | U+20000–U+2EBEF 同上 |
| `dist/php/syllables.php` | ~17 KB | 去重後的帶聲調音節值表（1436 條） |
| `dist/php/tone_map.php` | ~0.6 KB | 帶調字母 → 無調字母（28 條），供執行時去聲調 |
| `dist/php/extra.php` | <1 KB | 條帶範圍外的零星條目（兼容表意文字、康熙部首、U+3007 等） |
| `dist/php/meta.php` | <1 KB | 條帶佈局，讓載入器與範圍無關 |

二進制主體約 176 KB，帶聲調前後不變。

### 驗證

倉庫內含全量往返驗證腳本，會把 `pinyin.txt` 每一條在帶聲調與無聲調兩種模式下都對照載入器輸出：

```bash
php php/verify_packed_dict.php
# OK: 42083 entries round-tripped (toned + toneless), 489 unmapped codepoints verified null
```

若本機未安裝 PHP，可用 Docker：

```bash
docker run --rm -v "$PWD":/app -w /app php:8.3-cli php php/verify_packed_dict.php
```

`dist/php/` 由 `python3 gen_packed_dict.py` 從 `pinyin.txt` 與 `tone_removal.txt` 生成；改動這兩個來源後需重新生成並跑上面的驗證。

### 授權

MIT。拼音資料源自 [mozillazg/pinyin-data](https://github.com/mozillazg/pinyin-data)（抓取自漢典網 zdic.net，[CC0 1.0](https://zdic.net/terms/)）。

---

## English

Look up Hanyu Pinyin for a single Han character or a whole run of Chinese text. The readings come from zdic.net (via [mozillazg/pinyin-data](https://github.com/mozillazg/pinyin-data)), packed into a compact binary dataset with a pure-PHP loader. **Tone-marked output by default**, with a one-flag switch to toneless.

- **O(1) lookup** — each character is codepoint-addressed straight to a uint16 index; no linear scan, no hash table.
- **Toned / toneless** — `sū` by default, `su` when you pass `$tone = false` (`ü` stays `ü`, never folded to `u`).
- **Zero runtime deps** — only PHP ≥ 8.2 and `ext-mbstring`; no OpenCC binary required.
- **Complete coverage** — all 42,083 characters from `pinyin.txt` (CJK URO, Ext A–F, compatibility ideographs, Kangxi radicals, …).

> ⚠️ This is a **character-level** dictionary that keeps only the **first reading** of each character. It does not segment words or disambiguate polyphones by context. If you need curated readings, overlay your own phrase dictionary and fall back to this one.

### Install

```bash
composer require frankslin/opencc-pinyin
```

**Requirements**: PHP ≥ 8.2, `ext-mbstring`.

### Quick start

```php
require 'vendor/autoload.php';

use OpenccPinyin\PinyinData;

// Single character (tone-marked by default)
PinyinData::lookup('蘇');           // "sū"
PinyinData::lookup('蘇', false);    // "su"   — toneless
PinyinData::lookup('a');            // null   — non-Han / no reading

// Whole string
PinyinData::toPinyin('蘇軾與蘇轍');                // "sūshìyǔsūzhé"  no separator by default
PinyinData::toPinyin('蘇軾與蘇轍', ' ');           // "sū shì yǔ sū zhé"
PinyinData::toPinyin('蘇軾與蘇轍', ' ', false);    // "su shi yu su zhe"
PinyinData::toPinyin('東坡，一二三', '-');          // "dōng-pō-，-yī-èr-sān"

// Per-character breakdown (null boundaries preserved for your own joining rules)
PinyinData::syllables('蘇A東');
// [
//   ['char' => '蘇', 'pinyin' => 'sū'],
//   ['char' => 'A',  'pinyin' => null],   // non-Han kept as null
//   ['char' => '東', 'pinyin' => 'dōng'],
// ]
```

### API

All methods are static. The trailing `bool $tone = true` toggles tone marks (`false` = toneless).

| Method | Description |
|--------|-------------|
| `lookup(string $char, bool $tone = true): ?string` | First reading of one character; `null` for no reading or non-Han input. |
| `syllables(string $text, bool $tone = true): array` | Per-character `['char' => …, 'pinyin' => ?string]`; `pinyin` is `null` where there is no reading. |
| `toPinyin(string $text, string $separator = '', bool $tone = true): string` | Joins with `$separator` (**no separator by default**; pass `' '` for spaces); characters without a reading pass through unchanged. |
| `setOverrides(array $map): void` | Register a `char => reading` map that wins over the packed data (see below). |
| `reset(): void` | Clears loaded data and overrides (for tests). |

Toneless output is produced on the loader side by stripping tones at runtime via `dist/php/tone_map.php`; `ü` is kept as `ü`.

### Manual overrides

The packed data keeps only each character's **first reading**, which may not fit your domain. Pass a `char => reading` map to `setOverrides()` to make your reading win — to fix a polyphone, or to supply a reading for a character the dataset lacks.

```php
PinyinData::lookup('重');                      // "zhòng" — dataset first reading
PinyinData::setOverrides(['重' => 'chóng']);    // your domain wants chóng (重複)
PinyinData::lookup('重');                      // "chóng"
PinyinData::lookup('重', false);               // "chong" — overrides honor $tone too

PinyinData::setOverrides([]);                  // pass an empty array to clear
```

Provide readings **tone-marked** (matching the dataset); `$tone = false` strips them through the same tone map. `setOverrides()` replaces rather than merges.

### Dataset & size

The dataset stores the **tone-marked** first reading, but the binary strips hold uint16 indexes into a syllable value table — and their size depends only on the codepoint ranges, **not** on whether syllables carry tones. So adding tones only grows the value table (`syllables.php`); the binary bulk is unchanged.

| File | Size | Contents |
|------|------|----------|
| `dist/php/bmp.bin` | 55,296 B | One uint16 index per codepoint, U+3400–U+9FFF (`0xFFFF` = no reading) |
| `dist/php/supp.bin` | 120,800 B | Same encoding, U+20000–U+2EBEF |
| `dist/php/syllables.php` | ~17 KB | Distinct tone-marked syllable value table (1,436 entries) |
| `dist/php/tone_map.php` | ~0.6 KB | Tone-marked letter → base letter (28 entries), for runtime toneless output |
| `dist/php/extra.php` | <1 KB | Entries outside the strips (compatibility ideographs, Kangxi radicals, U+3007, …) |
| `dist/php/meta.php` | <1 KB | Strip layout, keeping the loader range-agnostic |

The binary bulk is ~176 KB and identical with or without tones.

### Verify

The repo ships a full round-trip verifier that checks every `pinyin.txt` entry against the loader in both tone modes:

```bash
php php/verify_packed_dict.php
# OK: 42083 entries round-tripped (toned + toneless), 489 unmapped codepoints verified null
```

No PHP locally? Use Docker:

```bash
docker run --rm -v "$PWD":/app -w /app php:8.3-cli php php/verify_packed_dict.php
```

`dist/php/` is generated by `python3 gen_packed_dict.py` from `pinyin.txt` and `tone_removal.txt`; regenerate and re-run the verifier after changing either source.

### License

MIT. Pinyin data from [mozillazg/pinyin-data](https://github.com/mozillazg/pinyin-data) (scraped from zdic.net, [CC0 1.0](https://zdic.net/terms/)).
