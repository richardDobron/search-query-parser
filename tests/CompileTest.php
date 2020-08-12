<?php

namespace dobron\QueryTextParser\Test;

use dobron\SearchQueryParser\Compiler;
use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../src/dobron/SearchQueryParser/helpers.php');

class CompileTest extends TestCase
{
    private Compiler $compiler;

    public function setUp(): void
    {
        $this->compiler = new Compiler();
    }

    public function testSimpleQuery()
    {
        $result = $this->compiler->setOptions([
        ])->setQuery([
            ['Slovakia'],
        ])->compile();

        $this->assertEquals('Slovakia', $result);
    }

    public function testMultiple()
    {
        $result = $this->compiler->setOptions([
            'keywords' => 'author',
        ])->setQuery([
            ['author', [
                'me',
                'John Snow',
            ]],
            ['publisher', 'Stan Smith, Jr'],
        ])->compile();

        $this->assertEquals('author:me,John Snow publisher:"Stan Smith, Jr"', $result);
    }

    public function testKeywordRanges()
    {
        $result = $this->compiler->setOptions([
        ])->setQuery([
            ['price', 10, '>'],
            ['price', 100, '<'],
        ])->compile();

        $this->assertEquals('price:>10 price:<100', $result);
    }

    public function testRanges()
    {
        $result = $this->compiler->setOptions([
            'ranges' => 'price,minus,length',
        ])->setQuery([
            ['price', [
                5,
                99,
            ]],
            ['minus', [
                -1000,
                -2500
            ]],
            ['length', [
                100,
                600
            ],
            '=', true],
        ])->compile();

        $this->assertEquals('price:5-99 minus:-1000--2500 -length:100-600', $result);
    }

    public function testOperator()
    {
        $result = $this->compiler->setOptions([
        ])->setQuery([
            ['price', 100, '>='],
        ])->compile();

        $this->assertEquals('price:>=100', $result);
    }

    public function testComplex()
    {
        $result = $this->compiler->setOptions([
        ])->setQuery([
            ['site', 'en.wikipedia.org/'],
            ['title', 'Slovakia'],
            [null, ['cities and towns']],
            [null, 'education', '=', true],
            ['inurl', 'wiki/', '=']
        ])->compile();

        $this->assertEquals('site:en.wikipedia.org/ title:Slovakia "cities and towns" -education inurl:wiki/', $result);
    }

    public function testQuotes()
    {
        $result = $this->compiler->setOptions([
            'alwaysQuote' => true
        ])->setQuery([
            ['Czechoslovakia'],
            ['Slovak republic', true],
            ['Czech', true],
            ['Czechia', true],
            ['Foreign trade'],
        ])->compile();

        $this->assertEquals('"Czechoslovakia" -"Slovak republic" -"Czech" -"Czechia" "Foreign trade"', $result);
    }

    public function testErrors()
    {
        $result = $this->compiler->setOptions([
            'ranges' => 'price',
        ])->setQuery([
            [null, 'Book: The Little Prince'],
            ['price', [
                10,
                20,
                30,
            ]],
        ])->compile();

        $this->assertEquals('Book: The Little Prince', $result);
    }

    public function testExclusion()
    {
        $result = $this->compiler->setOptions([
        ])->setQuery([
            ['product', 'Apple'],
            ['price', 1000, '>=', true],
            ['category', 'Computers', '=', true],
            ['tablet', true],
        ])->compile();

        $this->assertEquals('product:Apple -price:>=1000 -category:Computers -tablet', $result);
    }
}
