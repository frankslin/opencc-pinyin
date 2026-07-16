<?php

/**
 * Full round-trip verification of the packed dataset against pinyin.txt:
 * every entry's toneless first reading must equal PinyinData::lookup(), and
 * a sample of unmapped codepoints must return null.
 *
 * Usage: php php/verify_packed_dict.php
 */
require __DIR__.'/src/PinyinData.php';

use OpenccPinyin\PinyinData;

$root = dirname(__DIR__);

$toneMap = [];
foreach (explode("\n", trim(file_get_contents($root.'/tone_removal.txt'))) as $line) {
    [$marked, $base] = explode("\t", $line);
    $toneMap[$marked] = $base;
}
$stripTones = function (string $syllable) use ($toneMap): string {
    $out = '';
    foreach (preg_split('//u', $syllable, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        $out .= $toneMap[$ch] ?? $ch;
    }

    return $out;
};

$checked = 0;
$mapped = [];
foreach (explode("\n", trim(file_get_contents($root.'/pinyin.txt'))) as $line) {
    [$char, $readings] = explode("\t", $line, 2);
    $toned = explode(' ', $readings)[0];
    $toneless = $stripTones($toned);
    foreach ([[true, $toned], [false, $toneless]] as [$tone, $expected]) {
        $actual = PinyinData::lookup($char, $tone);
        if ($actual !== $expected) {
            fwrite(STDERR, sprintf("MISMATCH %s (U+%04X) tone=%s: expected %s, got %s\n",
                $char, mb_ord($char, 'UTF-8'), $tone ? 'on' : 'off',
                $expected, var_export($actual, true)));
            exit(1);
        }
    }
    $mapped[mb_ord($char, 'UTF-8')] = true;
    $checked++;
}

// Unmapped codepoints inside the strips must return null.
$nullChecked = 0;
foreach ([[0x3400, 0x9FFF], [0x20000, 0x2EBEF]] as [$lo, $hi]) {
    for ($cp = $lo; $cp <= $hi; $cp += 97) {
        if (!isset($mapped[$cp])) {
            if (PinyinData::lookup(mb_chr($cp, 'UTF-8')) !== null) {
                fwrite(STDERR, sprintf("FALSE POSITIVE at U+%04X\n", $cp));
                exit(1);
            }
            $nullChecked++;
        }
    }
}

// Behavioural checks (explicit, not assert(), so they run regardless of
// zend.assertions). PHPUnit covers these in depth; this keeps the data
// verifier self-contained for CI.
$check = function (string $what, $got, $want): void {
    if ($got !== $want) {
        fwrite(STDERR, sprintf("CHECK FAILED %s: expected %s, got %s\n",
            $what, var_export($want, true), var_export($got, true)));
        exit(1);
    }
};

// Non-Han / empty input is null in lookup.
$check('lookup(a)', PinyinData::lookup('a'), null);
$check('lookup(。)', PinyinData::lookup('。'), null);
$check("lookup('')", PinyinData::lookup(''), null);

// Overrides win over packed data and still honor the tone flag.
$check('lookup(重)', PinyinData::lookup('重'), 'zhòng');
PinyinData::setOverrides(['重' => 'chóng']);
$check('override lookup(重)', PinyinData::lookup('重'), 'chóng');
$check('override lookup(重,false)', PinyinData::lookup('重', false), 'chong');
PinyinData::setOverrides([]);
$check('cleared lookup(重)', PinyinData::lookup('重'), 'zhòng');

printf("OK: %d entries round-tripped (toned + toneless), %d unmapped codepoints verified null\n", $checked, $nullChecked);
printf("sample: 東坡集峯卷一 → %s | %s\n",
    PinyinData::toPinyin('東坡集峯卷一', ' '), PinyinData::toPinyin('東坡集峯卷一', ' ', false));
printf("sample: 淸厰愼槀靑頴 → %s | %s\n",
    PinyinData::toPinyin('淸厰愼槀靑頴', ' '), PinyinData::toPinyin('淸厰愼槀靑頴', ' ', false));
printf("sample: 龘 → %s | 净 → %s\n", PinyinData::toPinyin('龘'), PinyinData::toPinyin('净'));
