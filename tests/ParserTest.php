<?php

namespace dobron\QueryTextParser\Test;

use dobron\SearchQueryParser\Parser;
use PHPUnit\Framework\TestCase;

require_once (__DIR__ . '/../src/dobron/SearchQueryParser/helpers.php');

class ParserTest extends TestCase
{
    private Parser $parser;

    public function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function testSimpleQuery()
    {
        $result = $this->parser->parse('Slovakia');

        $this->assertIsArray($result->getText());
        $this->assertEquals([
            [
                'column'   => 'text',
                'operator' => 'like',
                'negate'   => false,
                'value'    => '%Slovakia%',
            ]
        ], $result->getText());
    }

    public function testMultiple()
    {
        $result = $this->parser->setOptions([
            'keywords' => ['author', 'publisher'],
        ])->parse('author:me,John Snow publisher:"Stan Smith, Jr"');

        $matched = $result->getMatch();
        $this->assertSame($matched['author'], [
            'me',
            'John Snow',
        ]);
        $this->assertSame($matched['publisher'], [
            'column'   => 'publisher',
            'operator' => '=',
            'negate'   => false,
            'value'    => 'Stan Smith, Jr',
        ]);
    }

    public function testKeywordRanges()
    {
        $result = $this->parser->setOptions([
            'keywords' => ['price'],
        ])->parse('price:>10 price:<100');

        $matched = $result->getMatch();
        $this->assertSame($matched['price'], [
            [
                'column'   => 'price',
                'operator' => '>',
                'negate'   => false,
                'value'    => '10',
            ],
            [
                'column'   => 'price',
                'operator' => '<',
                'negate'   => false,
                'value'    => '100',
            ]
        ]);
    }

    public function testRanges()
    {
        $result = $this->parser->setOptions([
            'ranges' => ['price', 'minus', 'length'],
        ])->parse('price:5-99 minus:-1000--2500 -length:100-600');

        $matched = $result->getMatch();
        $this->assertSame($matched['price'], [
            'column'   => 'price',
            'operator' => 'between',
            'negate'   => false,
            'value'    => [
                'from' => '5',
                'to'   => '99',
            ],
        ]);

        $this->assertSame($matched['minus'], [
            'column'   => 'minus',
            'operator' => 'between',
            'negate'   => false,
            'value'    => [
                'from' => '-1000',
                'to'   => '-2500',
            ],
        ]);

        $this->assertSame($matched['length'], [
            'column'   => 'length',
            'operator' => 'not between',
            'negate'   => true,
            'value'    => [
                'from' => '100',
                'to'   => '600',
            ],
        ]);
    }

    public function testOperator()
    {
        $result = $this->parser->setOptions([
            'keywords' => ['price'],
        ])->parse('price:>=100');

        $matched = $result->getMatch();
        $this->assertSame($matched['price'], [
            'column'   => 'price',
            'operator' => '>=',
            'negate'   => false,
            'value'    => '100',
        ]);
    }

    public function testComplex()
    {
        $result = $this->parser->setOptions([
            'keywords' => 'site,title,inurl',
            'offsets'  => false,
        ])->parse('site:en.wikipedia.org/ title:Slovakia "cities and towns" -education inurl:wiki/');

        $matched = $result->getMatch();
        $excluded = $result->getExcluded();
        $offsets = $result->getOffsets();

        $this->assertSame([
            [
                'column'   => 'text',
                'operator' => 'like',
                'negate'   => false,
                'value'    => '%cities and towns%',
            ]
        ], $result->getText());
        $this->assertCount(3, $matched);
        $this->assertArrayHasKey('site', $matched);
        $this->assertSame($matched['site'], [
            'column'   => 'site',
            'operator' => '=',
            'negate'   => false,
            'value'    => 'en.wikipedia.org/',
        ]);
        $this->assertArrayHasKey('title', $matched);
        $this->assertSame($matched['title'], [
            'column'   => 'title',
            'operator' => '=',
            'negate'   => false,
            'value'    => 'Slovakia',
        ]);
        $this->assertArrayHasKey('inurl', $matched);
        $this->assertSame($matched['inurl'], [
            'column'   => 'inurl',
            'operator' => '=',
            'negate'   => false,
            'value'    => 'wiki/',
        ]);

        $this->assertCount(1, $excluded);
        $this->assertNull($offsets);
        $this->assertEquals([
            [
                'column'   => 'text',
                'operator' => 'not like',
                'negate'   => true,
                'value'    => '%education%',
            ]
        ], $excluded['text']);
        $this->assertCount(5, $result->getQueries());
    }

    public function testQuotes()
    {
        $result = $this->parser->parse('"Czechoslovakia" -"Slovak republic" -"Czech" -"Czechia" "Foreign trade"');

        $excluded = $result->getExcluded();

        $this->assertCount(1, $excluded);
        $this->assertEquals([
            [
                'column'   => 'text',
                'operator' => 'like',
                'negate'   => false,
                'value'    => '%Czechoslovakia%',
            ],
            [
                'column'   => 'text',
                'operator' => 'like',
                'negate'   => false,
                'value'    => '%Foreign trade%',
            ],
        ], $result->getText());
        $this->assertEquals([
            [
                'column'   => 'text',
                'operator' => 'not like',
                'negate'   => true,
                'value'    => '%Slovak republic%',
            ], [
                'column'   => 'text',
                'operator' => 'not like',
                'negate'   => true,
                'value'    => '%Czech%',
            ], [
                'column'   => 'text',
                'operator' => 'not like',
                'negate'   => true,
                'value'    => '%Czechia%',
            ],
        ], $excluded['text']);
    }

    public function testErrors()
    {
        $result = $this->parser->setOptions([
            'ranges'   => ['price'],
            'offsets'  => false,
        ])->parse('Book: The Little Prince price:10-20-30');

        $text = $result->getText();
        $matched = $result->getMatch();
        $errors = $result->getErrors();

        $this->assertEmpty($matched);
        $this->assertEquals([
            [
                'column'   => 'text',
                'operator' => 'like',
                'negate'   => false,
                'value'    => '%Book:The Little Prince%',
            ]
        ], $text);
        $this->assertEquals([
            [
                "Invalid values for range 'price'.",
                [
                    'value' => [
                        '10',
                        '20',
                        '30',
                    ],
                ],
            ]
        ], $errors);
    }

    public function testExclusion()
    {
        $result = $this->parser->setOptions([
            'keywords' => ['product', 'price', 'category'],
            'offsets'  => false,
        ])->parse('product:Apple -price: >=1000 -category: Computers');

        $matched = $result->getMatch();
        $excluded = $result->getExcluded();

        $this->assertEquals([
            'column'   => 'product',
            'operator' => '=',
            'negate'   => false,
            'value'    => 'Apple',
        ], $matched['product']);

        $this->assertEquals([
            'column'   => 'price',
            'operator' => '<=',
            'negate'   => true,
            'value'    => '1000',
        ], $excluded['price']);
        $this->assertEquals([
            'column'   => 'category',
            'operator' => '<>',
            'negate'   => true,
            'value'    => 'Computers',
        ], $excluded['category']);
    }
}
