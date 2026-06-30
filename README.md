# opencc-pinyin

通過 [OpenCC](https://github.com/BYVoid/OpenCC) 的匹配機制，將繁體及簡體中文漢字轉換為漢語拼音。

拼音數據來源：[mozillazg/pinyin-data](https://github.com/mozillazg/pinyin-data/blob/master/zdic.txt)（抓取自漢典網 zdic.net）。  
多音字以 `zdic.txt` 中出現的順序為準，取第一個讀音。

---

## 文件說明

| 文件 | 說明 |
|------|------|
| `pinyin.json` | OpenCC 轉換配置文件 |
| `pinyin.txt` | OpenCC 文本格式字典（每行：`漢字 TAB 拼音`） |
| `zdic.txt` | 原始拼音數據，來自 mozillazg/pinyin-data |
| `gen_dict.py` | 從 `zdic.txt` 生成 `pinyin.txt` 的腳本 |

---

## 使用方法

**前提條件**：已安裝 OpenCC CLI 工具，版本 ≥ 1.3.2。

### 基本用法

使用 `-c` 參數指定配置文件的**絕對路徑**或相對路徑：

```sh
# 從標準輸入讀取，輸出到標準輸出
echo "你好世界" | opencc -c /path/to/opencc-pinyin/pinyin.json

# 從文件讀取，輸出到文件
opencc -c /path/to/opencc-pinyin/pinyin.json -i input.txt -o output.txt
```

### 示例

```
輸入（繁體）：愛龍漢字轉拼音
輸出：        àilónghànzìzhuǎnpīnyīn

輸入（簡體）：汉字转拼音
輸出：        hànzìzhuǎnpīnyīn

輸入（混合）：中文 English 混合
輸出：        zhōngwén English hùnhé

輸入（標點）：你好！世界123
輸出：        nǐhǎo！shìjiè123
```

### 注意事項

- 每個漢字的拼音**直接拼接**，字間無空格。非漢字字符（英文、數字、標點等）原樣保留。
- 若需在每個字的拼音之間加空格，可用 `tr` 等工具進行後處理，但需注意這會影響英文詞間空格。
- 本字典為**字符級**（character-level）字典，不進行詞語分詞，僅使用 OpenCC 的最長匹配算法按字逐一查找。

---

## 重新生成字典

若需從最新版 `zdic.txt` 重新生成 `pinyin.txt`：

```sh
# 1. 下載最新的 zdic.txt
curl -fsSL https://raw.githubusercontent.com/mozillazg/pinyin-data/master/zdic.txt -o zdic.txt

# 2. 生成字典
python3 gen_dict.py
```

`gen_dict.py` 腳本接受可選參數：

```sh
python3 gen_dict.py [zdic.txt 路徑] [輸出字典路徑]
```

---

## 算法說明

本項目利用 OpenCC 的**最長匹配**（Longest Match）字典查找機制：

1. OpenCC 讀取配置文件 `pinyin.json`，加載 `pinyin.txt` 字典。
2. 對輸入文本從左到右掃描，對每個位置嘗試字典中的最長匹配。
3. 匹配到漢字時，輸出其對應拼音；未匹配的字符原樣輸出。

字典涵蓋以下 Unicode 範圍：

- CJK 基本：U+4E00–9FFF
- CJK 擴展 A：U+3400–4DBF
- CJK 擴展 B：U+20000–2A6DF
- CJK 擴展 C/D：U+2A700–2B81D
- CJK 兼容擴展：U+2F800–2FA1F
- 以及其他部首、筆畫範圍

共收錄約 42,000 個漢字。
