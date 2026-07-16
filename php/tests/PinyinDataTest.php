<?php

declare(strict_types=1);

namespace OpenccPinyin\Tests;

use OpenccPinyin\PinyinData;
use PHPUnit\Framework\TestCase;

/**
 * Behavioural tests for the loader API. Full-dataset round-trip coverage lives
 * in php/verify_packed_dict.php (composer test:data); these focus on the
 * public contract and its edge cases.
 */
final class PinyinDataTest extends TestCase
{
    protected function setUp(): void
    {
        // The loader keeps data and overrides in static state; isolate tests.
        PinyinData::reset();
    }

    protected function tearDown(): void
    {
        PinyinData::reset();
    }

    public function testLookupReturnsToneMarkedByDefault(): void
    {
        $this->assertSame('sū', PinyinData::lookup('蘇'));
        $this->assertSame('dōng', PinyinData::lookup('東'));
    }

    public function testLookupToneless(): void
    {
        $this->assertSame('su', PinyinData::lookup('蘇', false));
        $this->assertSame('dong', PinyinData::lookup('東', false));
    }

    public function testUmlautIsPreservedInBothModes(): void
    {
        $this->assertSame('lǜ', PinyinData::lookup('綠'));
        $this->assertSame('lü', PinyinData::lookup('綠', false));
        $this->assertSame('lǚ', PinyinData::lookup('呂'));
        $this->assertSame('lü', PinyinData::lookup('呂', false));
    }

    public function testLookupReturnsNullForNonHanInput(): void
    {
        $this->assertNull(PinyinData::lookup('a'));
        $this->assertNull(PinyinData::lookup('。'));
        $this->assertNull(PinyinData::lookup('👍'));
        $this->assertNull(PinyinData::lookup(''));
    }

    public function testSyllablesPreservesNullBoundaries(): void
    {
        $this->assertSame(
            [
                ['char' => '蘇', 'pinyin' => 'sū'],
                ['char' => 'A', 'pinyin' => null],
                ['char' => '東', 'pinyin' => 'dōng'],
            ],
            PinyinData::syllables('蘇A東'),
        );
    }

    public function testSyllablesToneless(): void
    {
        $this->assertSame(
            [
                ['char' => '蘇', 'pinyin' => 'su'],
                ['char' => '東', 'pinyin' => 'dong'],
            ],
            PinyinData::syllables('蘇東', false),
        );
    }

    public function testSyllablesOfEmptyStringIsEmptyArray(): void
    {
        $this->assertSame([], PinyinData::syllables(''));
    }

    public function testToPinyinConcatenatesWithNoSeparatorByDefault(): void
    {
        $this->assertSame('sūshì', PinyinData::toPinyin('蘇軾'));
        $this->assertSame('sū shì', PinyinData::toPinyin('蘇軾', ' '));
        $this->assertSame('sū-shì', PinyinData::toPinyin('蘇軾', '-'));
        $this->assertSame('sushi', PinyinData::toPinyin('蘇軾', '', false));
    }

    public function testToPinyinKeepsUnreadableCharactersVerbatim(): void
    {
        // Non-Han and unread characters pass through as-is (the "no reading" signal).
        $this->assertSame('sūAdōng', PinyinData::toPinyin('蘇A東'));
        $this->assertSame('su A dong', PinyinData::toPinyin('蘇A東', ' ', false));
        $this->assertSame('dōng-pō-?', PinyinData::toPinyin('東坡?', '-'));
    }

    public function testOverrideWinsOverPackedData(): void
    {
        $this->assertSame('zhòng', PinyinData::lookup('重'));

        PinyinData::setOverrides(['重' => 'chóng']);
        $this->assertSame('chóng', PinyinData::lookup('重'));
    }

    public function testOverrideHonorsToneFlag(): void
    {
        PinyinData::setOverrides(['重' => 'chóng']);
        $this->assertSame('chong', PinyinData::lookup('重', false));
    }

    public function testOverrideCanSupplyAReadingTheDatasetLacks(): void
    {
        $this->assertNull(PinyinData::lookup('A'));

        PinyinData::setOverrides(['A' => 'ēi']);
        $this->assertSame('ēi', PinyinData::lookup('A'));
        $this->assertSame('ei', PinyinData::lookup('A', false));
    }

    public function testSetOverridesReplacesRatherThanMerges(): void
    {
        PinyinData::setOverrides(['重' => 'chóng']);
        PinyinData::setOverrides(['長' => 'zhǎng']);

        $this->assertSame('zhǎng', PinyinData::lookup('長'));
        $this->assertSame('zhòng', PinyinData::lookup('重'), 'earlier override should be gone');
    }

    public function testEmptyOverridesClearThem(): void
    {
        PinyinData::setOverrides(['重' => 'chóng']);
        PinyinData::setOverrides([]);

        $this->assertSame('zhòng', PinyinData::lookup('重'));
    }

    public function testResetClearsOverrides(): void
    {
        PinyinData::setOverrides(['重' => 'chóng']);
        PinyinData::reset();

        $this->assertSame('zhòng', PinyinData::lookup('重'));
    }

    public function testOverridesFlowThroughToPinyin(): void
    {
        PinyinData::setOverrides(['長' => 'zhǎng']);
        $this->assertSame('zhǎng dà', PinyinData::toPinyin('長大', ' '));
    }
}
