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

use Opis\Pattern\Builder;
use PHPUnit\Framework\TestCase;

class DefaultTest extends TestCase
{
    /** @var Builder */
    protected $b;

    public function setUp()
    {
        $this->b = new Builder();
    }

    /**
     * @dataProvider regexProvider
     */
    public function testSimpleRegex($pattern, $placeholders, $expected)
    {
        $regex = $this->b->getRegex($pattern, $placeholders);
        $this->assertEquals($expected, $regex);
    }

    public function regexProvider()
    {
        return [
            [
                '/', [], '~^/?$~u',
                '/a', [], '~/a/?$~u',
                '/a/{b}', [], '~/a/(?P<b>([^/]))/?$~u',
                '/a/{b?}', [], '~/a(/(?P<b>([^/])))?/?$~u',
            ]
        ];
    }

}