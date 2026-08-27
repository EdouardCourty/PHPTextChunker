<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit\Strategy;

use Ecourty\TextChunker\Strategy\HtmlChunkingStrategy;
use PHPUnit\Framework\TestCase;

class HtmlChunkingStrategyTest extends TestCase
{
    public function testSplitsOnHeadingsByDefault(): void
    {
        $strategy = new HtmlChunkingStrategy();
        $input = '<h1>Title</h1><p>Intro.</p><h2>Section</h2>Content.';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(2, $chunks);
        $this->assertSame('<h1>Title</h1><p>Intro.</p>', $chunks[0]->getText());
        $this->assertSame('<h2>Section</h2>Content.', $chunks[1]->getText());
    }

    public function testOpeningTagIsIncludedInChunkText(): void
    {
        $strategy = new HtmlChunkingStrategy();
        $chunks = iterator_to_array($strategy->process('<h2 id="a">Head</h2>Body', true));

        $this->assertStringStartsWith('<h2 id="a">', $chunks[0]->getText());
    }

    public function testContentBeforeFirstHeadingIsYieldedSeparately(): void
    {
        $strategy = new HtmlChunkingStrategy();
        $input = 'Intro text.<h1>Title</h1>Body';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(2, $chunks);
        $this->assertSame('Intro text.', $chunks[0]->getText());
        $this->assertNull($chunks[0]->getMetadata()['tag']);
    }

    public function testMetadataContainsStrategyLengthTagAndAttributes(): void
    {
        $strategy = new HtmlChunkingStrategy();
        $chunks = iterator_to_array(
            $strategy->process('<h2 class="sec" data-x="1">Head</h2>Body', true),
        );

        $metadata = $chunks[0]->getMetadata();
        $this->assertSame('html', $metadata['strategy']);
        $this->assertSame(mb_strlen($chunks[0]->getText()), $metadata['length']);
        $this->assertSame('h2', $metadata['tag']);
        $this->assertSame(['class' => 'sec', 'data-x' => '1'], $metadata['attributes']);
    }

    public function testCustomTagsSplitPoints(): void
    {
        $strategy = new HtmlChunkingStrategy(tags: ['section']);
        $input = '<section class="a">One</section><section>Two</section>';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(2, $chunks);
        $this->assertSame('section', $chunks[0]->getMetadata()['tag']);
        $this->assertSame(['class' => 'a'], $chunks[0]->getMetadata()['attributes']);
        $this->assertSame('<section class="a">One</section>', $chunks[0]->getText());
        $this->assertSame('<section>Two</section>', $chunks[1]->getText());
    }

    public function testCaseInsensitiveTagMatching(): void
    {
        $strategy = new HtmlChunkingStrategy(tags: ['H2']);
        $input = '<H2 CLASS="Big">Head</H2>Body';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(1, $chunks);
        $this->assertSame('h2', $chunks[0]->getMetadata()['tag']);
        $this->assertSame(['class' => 'Big'], $chunks[0]->getMetadata()['attributes']);
    }

    public function testStripTagsRemovesMarkupAndScriptStyleContent(): void
    {
        $strategy = new HtmlChunkingStrategy(stripTags: true);
        $input = "<h1>Title</h1>\n<script>alert('x');</script><p>Text with <em>emph</em>.</p>";

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(1, $chunks);
        $this->assertSame("Title\nText with emph.", $chunks[0]->getText());
    }

    public function testCommentsAreDroppedByDefaultAndDoNotTriggerSplits(): void
    {
        $strategy = new HtmlChunkingStrategy();
        $input = 'A<!-- hidden <h2>fake -->B<h2>Real</h2>';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(2, $chunks);
        $this->assertSame('AB', $chunks[0]->getText());
    }

    public function testKeepCommentsOptionKeepsCommentsInText(): void
    {
        $strategy = new HtmlChunkingStrategy(keepComments: true);
        $input = 'A<!-- hidden <h2>fake -->B<h2>Real</h2>';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(2, $chunks);
        $this->assertSame('A<!-- hidden <h2>fake -->B', $chunks[0]->getText());
    }

    public function testCommentSplitAcrossCallsDoesNotLeakOrSplit(): void
    {
        $strategy = new HtmlChunkingStrategy();

        foreach ($strategy->process('A<!-- hid', false) as $chunk) {
            $this->fail('No chunk should be emitted while a comment is open');
        }

        $chunks = iterator_to_array($strategy->process('den -->B<h2>R</h2>', true));

        $this->assertCount(2, $chunks);
        $this->assertSame('AB', $chunks[0]->getText());
    }

    public function testGreaterThanInsideQuotedAttributeDoesNotBreakParsing(): void
    {
        $strategy = new HtmlChunkingStrategy();
        $input = '<h1 title="a > b">Weird</h1><p>ok</p>';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(1, $chunks);
        $this->assertSame(['title' => 'a > b'], $chunks[0]->getMetadata()['attributes']);
    }

    public function testScriptStyleContentsDoNotTriggerSplits(): void
    {
        $strategy = new HtmlChunkingStrategy();
        $input = "<h2>Before</h2><script>if (a < b) { log('<h2>nope</h2>'); }</script>"
            . '<style>h2 { color: red; }</style><h2>After</h2>';

        $chunks = iterator_to_array($strategy->process($input, true));

        $texts = array_map(fn ($c) => $c->getText(), $chunks);
        $this->assertCount(2, $chunks);
        $this->assertStringContainsString("log('<h2>nope</h2>');", $texts[0]);
        $this->assertSame('<h2>After</h2>', end($texts));
    }

    public function testSelfClosingScriptIsTreatedAsEmptyElement(): void
    {
        $strategy = new HtmlChunkingStrategy(stripTags: true);
        $input = "<h2>A</h2><script src=\"x.js\"/>After<script>alert('no');</script>";

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(1, $chunks);
        $this->assertSame('AAfter', $chunks[0]->getText());
    }

    public function testUppercaseClosingTagTerminatesOpaqueBlock(): void
    {
        $strategy = new HtmlChunkingStrategy();
        $input = '<h2>Before</h2><script>x</SCRIPT>After<h2>Next</h2>';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(2, $chunks);
        $this->assertSame('<h2>Before</h2><script>x</SCRIPT>After', $chunks[0]->getText());
        $this->assertSame('<h2>Next</h2>', $chunks[1]->getText());
    }

    public function testSimilarTagNamePrefixDoesNotCloseOpaqueBlock(): void
    {
        $strategy = new HtmlChunkingStrategy();
        $input = '<style>a{}</stylesheet>b</style>c';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(1, $chunks);
        $this->assertSame('<style>a{}</stylesheet>b</style>c', $chunks[0]->getText());
    }

    public function testUnterminatedCommentAtEndOfFileIsDropped(): void
    {
        $strategy = new HtmlChunkingStrategy();
        $chunks = iterator_to_array($strategy->process('A<!-- oops', true));

        $this->assertCount(1, $chunks);
        $this->assertSame('A', $chunks[0]->getText());

        $keeping = new HtmlChunkingStrategy(keepComments: true);
        $chunks = iterator_to_array($keeping->process('A<!-- oops', true));

        $this->assertCount(1, $chunks);
        $this->assertSame('A<!-- oops', $chunks[0]->getText());
    }

    public function testUnterminatedScriptAtEndOfFileKeepsRawContent(): void
    {
        $strategy = new HtmlChunkingStrategy();
        $chunks = iterator_to_array($strategy->process('<h2>A</h2><script>var x = 1;', true));

        $this->assertCount(1, $chunks);
        $this->assertSame('<h2>A</h2><script>var x = 1;', $chunks[0]->getText());

        $stripped = new HtmlChunkingStrategy(stripTags: true);
        $chunks = iterator_to_array($stripped->process('<h2>A</h2><script>var x = 1;', true));

        $this->assertCount(1, $chunks);
        $this->assertSame('A', $chunks[0]->getText());
    }

    public function testDoctypeAndProcessingInstructionsPassThrough(): void
    {
        $strategy = new HtmlChunkingStrategy();
        $input = '<!DOCTYPE html><h2>T</h2><?php echo 1; ?>';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(2, $chunks);
        $this->assertSame('<!DOCTYPE html>', $chunks[0]->getText());
        $this->assertNull($chunks[0]->getMetadata()['tag']);
        $this->assertSame('<h2>T</h2><?php echo 1; ?>', $chunks[1]->getText());

        $stripped = new HtmlChunkingStrategy(stripTags: true);
        $chunks = iterator_to_array($stripped->process($input, true));

        $this->assertCount(1, $chunks);
        $this->assertSame('T', $chunks[0]->getText());
    }

    public function testProseAngleBracketsArePreservedWithStripTags(): void
    {
        $strategy = new HtmlChunkingStrategy(stripTags: true);
        $chunks = iterator_to_array($strategy->process('<h2>T</h2>x < y and z > w', true));

        $this->assertCount(1, $chunks);
        $this->assertSame('Tx < y and z > w', $chunks[0]->getText());
    }

    public function testDuplicateAttributesFirstOccurrenceWins(): void
    {
        $strategy = new HtmlChunkingStrategy();
        $chunks = iterator_to_array($strategy->process('<h1 id="first" id="second">T</h1>', true));

        $this->assertCount(1, $chunks);
        $this->assertSame(['id' => 'first'], $chunks[0]->getMetadata()['attributes']);
    }

    public function testMultibyteContentIsPreserved(): void
    {
        $strategy = new HtmlChunkingStrategy(stripTags: true);
        $chunks = iterator_to_array($strategy->process('<h2>Café ☕</h2>Le cœur de la matière — 日本語.', true));

        $this->assertCount(1, $chunks);
        $this->assertSame('Café ☕Le cœur de la matière — 日本語.', $chunks[0]->getText());
    }

    public function testStreamingAcrossMultipleCallsMatchesOneShot(): void
    {
        $input = "<h1>T1</h1>abc<h2>T2</h2>def<!-- c --><h3>T3</h3>ghi<script>var a='<h1>';</script>end";

        $oneShot = array_map(
            fn ($c) => $c->getText(),
            iterator_to_array((new HtmlChunkingStrategy())->process($input, true)),
        );

        $streamed = [];
        $strategy = new HtmlChunkingStrategy();
        foreach (mb_str_split($input, 7) as $piece) {
            foreach ($strategy->process($piece, false) as $chunk) {
                $streamed[] = $chunk->getText();
            }
        }
        foreach ($strategy->process('', true) as $chunk) {
            $streamed[] = $chunk->getText();
        }

        $this->assertSame($oneShot, $streamed);
    }

    public function testCommentClosingDelimiterSplitAcrossChunkBoundaryIsDetected(): void
    {
        $strategy = new HtmlChunkingStrategy(keepComments: true);

        foreach ($strategy->process('<h2>A</h2><!-- <fake> text --', false) as $chunk) {
            $this->fail('No chunk should be emitted while a comment is open');
        }

        $chunks = iterator_to_array($strategy->process('>B<h2>C</h2>', true));

        $this->assertCount(2, $chunks);
        $this->assertSame('<h2>A</h2><!-- <fake> text -->B', $chunks[0]->getText());
    }

    public function testOpaqueClosingTagSplitAcrossChunkBoundaryIsDetected(): void
    {
        $strategy = new HtmlChunkingStrategy();

        foreach ($strategy->process('<h2>A</h2><script>var x = 1;</scri', false) as $chunk) {
            $this->fail('No chunk should be emitted while a script block is open');
        }

        $chunks = iterator_to_array($strategy->process('pt>B<h2>C</h2>', true));

        $this->assertCount(2, $chunks);
        $this->assertSame('<h2>A</h2><script>var x = 1;</script>B', $chunks[0]->getText());
    }

    public function testOpaqueClosingTagLiteralAtBufferEndMissingOnlyLookaheadCharIsDetected(): void
    {
        // The buffer ends exactly on the full "</script" literal; only the
        // lookahead character arrives in the next call. Regression test for
        // an off-by-one in the resume-scan lookback window.
        $strategy = new HtmlChunkingStrategy();

        foreach ($strategy->process('<h2>A</h2><script>x</script', false) as $chunk) {
            $this->fail('No chunk should be emitted while a script block is open');
        }

        $chunks = iterator_to_array($strategy->process('>B<h2>C</h2>', true));

        $this->assertCount(2, $chunks);
        $this->assertSame('<h2>A</h2><script>x</script>B', $chunks[0]->getText());
    }

    public function testLargeOpaqueBodyStreamedInSmallChunksStaysLinear(): void
    {
        $body = str_repeat('a', 4_000_000);
        $input = '<h2>Before</h2><script>' . $body . '</script><h2>After</h2>';

        $strategy = new HtmlChunkingStrategy();
        $chunks = [];

        $start = microtime(true);
        foreach (str_split($input, 32) as $piece) {
            foreach ($strategy->process($piece, false) as $chunk) {
                $chunks[] = $chunk;
            }
        }
        foreach ($strategy->process('', true) as $chunk) {
            $chunks[] = $chunk;
        }
        $elapsed = microtime(true) - $start;

        $this->assertCount(2, $chunks);
        $this->assertSame('<h2>Before</h2><script>' . $body . '</script>', $chunks[0]->getText());
        $this->assertSame('<h2>After</h2>', $chunks[1]->getText());
        $this->assertLessThan(
            2.0,
            $elapsed,
            'Streaming a large <script> body in small chunks must stay ~linear '
            . '(regression guard against full-buffer rescans in STATE_OPAQUE)',
        );
    }

    public function testLargeCommentStreamedInSmallChunksStaysLinear(): void
    {
        $body = str_repeat('x', 4_000_000);
        $input = '<h2>A</h2><!-- <fake> ' . $body . ' --><h2>B</h2>';

        $strategy = new HtmlChunkingStrategy();
        $chunks = [];

        $start = microtime(true);
        foreach (str_split($input, 32) as $piece) {
            foreach ($strategy->process($piece, false) as $chunk) {
                $chunks[] = $chunk;
            }
        }
        foreach ($strategy->process('', true) as $chunk) {
            $chunks[] = $chunk;
        }
        $elapsed = microtime(true) - $start;

        $this->assertCount(2, $chunks);
        $this->assertSame('<h2>A</h2>', $chunks[0]->getText());
        $this->assertSame('<h2>B</h2>', $chunks[1]->getText());
        $this->assertLessThan(
            2.0,
            $elapsed,
            'Streaming a large HTML comment in small chunks must stay ~linear '
            . '(regression guard against full-buffer rescans in STATE_COMMENT)',
        );
    }

    public function testStripTagsWithKeepCommentsExcludesCommentsFromText(): void
    {
        $strategy = new HtmlChunkingStrategy(stripTags: true, keepComments: true);
        $input = '<h1>Title</h1>Text <!-- a comment --> more text';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(1, $chunks);
        $this->assertSame('TitleText  more text', $chunks[0]->getText());
    }

    public function testResetClearsState(): void
    {
        $strategy = new HtmlChunkingStrategy();
        iterator_to_array($strategy->process('<h1>A</h1>', true));

        $strategy->reset();
        $chunks = iterator_to_array($strategy->process('<h1>B</h1>', true));

        $this->assertCount(1, $chunks);
        $this->assertSame(0, $chunks[0]->getPosition());
        $this->assertSame('<h1>B</h1>', $chunks[0]->getText());
    }

    public function testEmptyTagsArrayThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HtmlChunkingStrategy(tags: []);
    }

    public function testInvalidTagNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HtmlChunkingStrategy(tags: ['<bad>']);
    }

    public function testBlankSelectorThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HtmlChunkingStrategy(selector: '   ');
    }

    public function testInvalidXPathExpressionThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HtmlChunkingStrategy(selector: '//[[');
    }

    public function testSelectorExtractsNodesWithOuterHtml(): void
    {
        $strategy = new HtmlChunkingStrategy(selector: '//section[@class="content"]');
        $input = '<div class="nav">Menu</div>'
            . '<section class="content"><h2>A</h2>First.</section>'
            . '<section class="ads">Spam</section>'
            . '<section class="content"><h2>B</h2>Second.</section>';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(2, $chunks);
        $this->assertSame('<section class="content"><h2>A</h2>First.</section>', $chunks[0]->getText());
        $this->assertSame('<section class="content"><h2>B</h2>Second.</section>', $chunks[1]->getText());
    }

    public function testSelectorMetadataComesFromDom(): void
    {
        $strategy = new HtmlChunkingStrategy(selector: '//article[@id="main"]');
        $chunks = iterator_to_array($strategy->process('<article id="main" lang="fr">Hello</article>', true));

        $this->assertCount(1, $chunks);
        $this->assertSame('html', $chunks[0]->getMetadata()['strategy']);
        $this->assertSame('article', $chunks[0]->getMetadata()['tag']);
        $this->assertSame(['id' => 'main', 'lang' => 'fr'], $chunks[0]->getMetadata()['attributes']);
    }

    public function testSelectorWithStripTagsReturnsTextOnly(): void
    {
        $strategy = new HtmlChunkingStrategy(selector: '//section[@class="content"]', stripTags: true);
        $input = '<section class="content"><h2>A</h2>First <b>bold</b>.</section>';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(1, $chunks);
        $this->assertSame('AFirst bold.', $chunks[0]->getText());
    }

    public function testSelectorStripTagsExcludesScriptAndStyleContent(): void
    {
        $strategy = new HtmlChunkingStrategy(selector: '//div[@id="main"]', stripTags: true);
        $input = '<div id="main"><p>Hello</p><script>alert("evil");</script>'
            . '<style>p{color:red}</style></div>';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(1, $chunks);
        $this->assertSame('Hello', $chunks[0]->getText());
    }

    public function testSelectorStripTagsOnDirectlySelectedScriptElementYieldsNothing(): void
    {
        $strategy = new HtmlChunkingStrategy(selector: '//script', stripTags: true);
        $chunks = iterator_to_array($strategy->process('<div><script>alert(1);</script></div>', true));

        $this->assertCount(0, $chunks);
    }

    public function testSelectorWithoutKeepCommentsIgnoresDirectlySelectedCommentNode(): void
    {
        $strategy = new HtmlChunkingStrategy(selector: '//comment()');
        $chunks = iterator_to_array($strategy->process('<div>a<!-- secret --></div>', true));

        $this->assertCount(0, $chunks);
    }

    public function testSelectorWithKeepCommentsReturnsDirectlySelectedCommentNode(): void
    {
        $strategy = new HtmlChunkingStrategy(selector: '//comment()', keepComments: true);
        $chunks = iterator_to_array($strategy->process('<div>a<!-- secret --></div>', true));

        $this->assertCount(1, $chunks);
        $this->assertSame('secret', $chunks[0]->getText());
    }

    public function testSelectorWithoutKeepCommentsRemovesCommentsFromOutput(): void
    {
        $strategy = new HtmlChunkingStrategy(selector: '//div');
        $chunks = iterator_to_array($strategy->process('<div>a<!-- secret -->b</div>', true));

        $this->assertCount(1, $chunks);
        $this->assertSame('<div>ab</div>', $chunks[0]->getText());
    }

    public function testSelectorKeepCommentsPreservesCommentsInOutput(): void
    {
        $strategy = new HtmlChunkingStrategy(selector: '//div', keepComments: true);
        $chunks = iterator_to_array($strategy->process('<div>a<!-- secret -->b</div>', true));

        $this->assertCount(1, $chunks);
        $this->assertStringContainsString('<!-- secret -->', $chunks[0]->getText());
    }

    public function testSelectorTakesPrecedenceOverTags(): void
    {
        $strategy = new HtmlChunkingStrategy(tags: ['h1'], selector: '//p');
        $input = '<h1>Ignored heading</h1><p>One</p><p>Two</p>';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(2, $chunks);
        $this->assertSame('<p>One</p>', $chunks[0]->getText());
        $this->assertSame('p', $chunks[0]->getMetadata()['tag']);
    }

    public function testUnmatchedSelectorYieldsNothing(): void
    {
        $strategy = new HtmlChunkingStrategy(selector: '//video');
        $chunks = iterator_to_array($strategy->process('<p>No video here.</p>', true));

        $this->assertCount(0, $chunks);
    }

    public function testSelectorBuffersUntilEnd(): void
    {
        $strategy = new HtmlChunkingStrategy(selector: '//p');

        foreach ($strategy->process('<p>O', false) as $chunk) {
            $this->fail('No chunk should be emitted before isEnd in XPath mode');
        }

        $chunks = iterator_to_array($strategy->process('ne</p>', true));

        $this->assertCount(1, $chunks);
        $this->assertSame('<p>One</p>', $chunks[0]->getText());
    }
}
