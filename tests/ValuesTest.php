<?php
/* ===========================================================================
 * Copyright 2013-2018 The Opis Project
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * ============================================================================ */

namespace Opis\Pattern\Test;

use Opis\Pattern\RegexBuilder;
use PHPUnit\Framework\TestCase;

class ValuesTest extends TestCase
{
    /** @var RegexBuilder */
    protected $builder;

    protected function setUp()
    {
        $this->builder = new RegexBuilder();
    }

    /**
     * @dataProvider valuesProvider
     */
    public function testValues($pattern, $placeholders, $tests)
    {
        $regex = $this->builder->getRegex($pattern, $placeholders);
        foreach ($tests as $path => $values) {
            if (!$this->builder->matches($regex, $path)) {
                $this->assertNull($values, "$pattern => $path");
            }
            else {
                $this->assertEquals($values, $this->builder->getValues($regex, $path), "$pattern => $path");
            }
        }
    }

    public function valuesProvider()
    {
        return [
            ['{a}/{b}', [], [
                'abc/test/' => [
                    'a' => 'abc',
                    'b' => 'test'
                ],
                '123/111' => [
                    'a' => '123',
                    'b' => '111'
                ],
                '/t' => null
            ]],
            ['{a}/{b}', ['a' => '\d+'], [
                'abc/test/' => null,
                '123/111' => [
                    'a' => '123',
                    'b' => '111'
                ],
                '123/aaa' => [
                    'a' => '123',
                    'b' => 'aaa'
                ],
                '123' => null
            ]],
            ['{a}/{b?}', [], [
                'abc/test/' => [
                    'a' => 'abc',
                    'b' => 'test'
                ],
                '123/111' => [
                    'a' => '123',
                    'b' => '111'
                ],
                'aaa' => [
                    'a' => 'aaa'
                ],
                'aaa/' => [
                    'a' => 'aaa'
                ],
                '/bbb' => null
            ]],
            ['{a?}/{b?}', [], [
                'abc/test/' => [
                    'a' => 'abc',
                    'b' => 'test'
                ],
                '123/111' => [
                    'a' => '123',
                    'b' => '111'
                ],
                'aaa' => [
                    'a' => 'aaa'
                ],
                'aaa/' => [
                    'a' => 'aaa'
                ],
                '/bbb' => [
                    'a' => '',
                    'b' => 'bbb'
                ],
                '' => [],
                '/' => [],
                '//' => null,
            ]],
            ['pre-{a?}/{b?}-suf', [], [
                'abc/test/' => null,
                'pre-abc/def-suf' => [
                    'a' => 'abc',
                    'b' => 'def'
                ],
                'pre-abc/def-suf/' => [
                    'a' => 'abc',
                    'b' => 'def'
                ],
                'pre-/def-suf' => [
                    'a' => '',
                    'b' => 'def'
                ],
                'pre-abc/-suf' => [
                    'a' => 'abc'
                ],
                'pre-/-suf' => [],
                'pre/suf' => null,
            ]],
        ];
    }

}