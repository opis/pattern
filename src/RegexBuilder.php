<?php
/* ===========================================================================
 * Copyright 2018 The Opis Project
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

namespace Opis\Pattern;

class RegexBuilder
{
    protected $options;

    public function __construct()
    {
        $this->options = [
            'separator' => '/',
            'start_tag' => '{',
            'end_tag' => '}',
            'opt_symbol' => '?',
            'delimiter' => '~',
            'default_regex' => '[^/]+',
            'capture_left' => true,
            'capture_right' => false,
            'allow_sep_trail' => true,
        ];
    }

    public function getRegex(string $pattern, array $placeholders): string
    {
        $regex = [];
        $tokens = $this->tokens($pattern);
        $delimiter = $this->options['delimiter'];
        $default_exp = $this->options['default_regex'];
        $capture_left = $this->options['capture_left'];
        $capture_right = $this->options['capture_right'];
        $allow_trail = $this->options['allow_sep_trail'];

        $sep = preg_quote($this->options['separator'], $delimiter);

        for($i = 0, $l = count($tokens); $i < $l; $i++) {
            $t = $tokens[$i];
            $n = $tokens[$i + 1] ?? null;

            if ($t['type'] === 'separator') {
                if ($capture_left) {
                    if (isset($n)) {
                        if ($n['type'] === 'variable' && $n['opt']) {
                            $regex[] = '(' . $sep . '(?P<' .preg_quote($n['value'], $delimiter) .
                                '>(' . ($placeholders[$n['value']] ?? $default_exp) . ')))?';
                            $i++;
                        } else {
                            $regex[] = preg_quote($t['value'], $delimiter);
                        }
                    } else {
                        $regex[] = $sep . ($allow_trail ? '?' : '');
                    }
                } else {
                    if (!isset($n)) {
                        $regex[] = $sep . ($allow_trail ? '?' : '');
                    } else {
                        $regex[] = $sep;
                    }
                }
            } elseif ($t['type'] === 'variable') {
                if ($capture_right) {
                    if (isset($n) && $n['type'] === 'separator') {
                        $regex[] = '((?P<' .preg_quote($t['value'], $delimiter) .
                            '>(' . ($placeholders[$t['value']] ?? $default_exp) . '))' . $sep . ')?';
                        $i++;
                    } else {
                        $regex[] = '(?P<' .preg_quote($t['value'], $delimiter) .
                            '>(' . ($placeholders[$t['value']] ?? $default_exp) . '))';
                    }
                } else {
                    $regex[] = '(?P<' .preg_quote($t['value'], $delimiter) .
                        '>(' . ($placeholders[$t['value']] ?? $default_exp) . '))';
                    if (!isset($n) && $allow_trail){
                        $regex[] = $sep . '?';
                    }
                }
            } else {
                $regex[] = preg_quote($t['value'], $delimiter);
                if ($capture_left && $allow_trail && !isset($n)){
                    $regex[] = $sep . '?';
                }
            }
        }

        return $delimiter . '^' . implode('', $regex) . '$' . $delimiter . 'u';
    }

    public function getNames(string $pattern): array
    {
        $names = [];
        foreach ($this->tokens($pattern) as $token) {
            if ($token['type'] === 'variable') {
                if (!in_array($token['value'], $names)) {
                    $names[] = $token['value'];
                }
            }
        }
        return $names;
    }

    public function getValues(string $regex, string $path): array
    {
        preg_match($regex, $path, $parameters);

        $parameters = array_slice($parameters, 1);

        if (count($parameters) === 0) {
            return array();
        }

        $keys = array_filter(array_keys($parameters), function ($value) use ($parameters) {
            return is_string($value) && strlen($value) > 0 && isset($parameters[$value]);
        });

        return array_intersect_key($parameters, array_flip($keys));
    }

    public function tokens(string $pattern)
    {
        $sym_separator = $this->options['separator'];
        $sym_opt = $this->options['opt_symbol'];
        $sym_start_tag = $this->options['start_tag'];
        $sym_end_tag = $this->options['end_tag'];

        $state = 'data';
        $tokens = [];
        $data_marker = 0;
        for ($i = 0, $l = strlen($pattern); $i <= $l; $i++) {
            if ($i === $l) {
                $c = null;
            } else {
                $c = $pattern[$i];
            }
            switch ($state){
                case 'data':
                    if ($c === $sym_separator){
                        if ($i - $data_marker > 0) {
                            $tokens[] = [
                                'type' => 'data',
                                'value' => substr($pattern, $data_marker,   $i - $data_marker)
                            ];
                        }
                        $tokens[] = [
                            'type' => 'separator',
                            'value' => $c,
                        ];
                        $data_marker = $i + 1;
                    } elseif ($c === $sym_start_tag) {
                        if ($i - $data_marker > 0) {
                            $tokens[] = [
                                'type' => 'data',
                                'value' => substr($pattern, $data_marker, $i - $data_marker)
                            ];
                        }
                        $state = 'var';
                        $data_marker = $i;
                    } elseif ($c === null) {
                        $state = 'eof';
                        $i--;
                    }
                    break;
                case 'var':
                    if ($c === $sym_opt) {
                        $state = 'opt_var';
                    } elseif ($c === $sym_end_tag) {
                        $name = substr($pattern, $data_marker + 1, $i - $data_marker - 1);
                        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name)){
                            throw new \RuntimeException("Invalid name $name");
                        }
                        $tokens[] = [
                            'type' => 'variable',
                            'value' => $name,
                            'opt' => false,
                        ];
                        $data_marker = $i + 1;
                        $state = 'data';
                    } elseif ($c === null) {
                        $state = 'eof';
                        $i--;
                    }
                    break;
                case 'opt_var':
                    if ($c === null) {
                        $state = 'eof';
                        $i--;
                    } elseif($c === $sym_end_tag) {
                        $name = substr($pattern, $data_marker + 1, ($i - 1) - $data_marker - 1);
                        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name)){
                            throw new \RuntimeException("Invalid name");
                        }
                        $data_marker = $i + 1;
                        $state = 'data';
                        $tokens[] = [
                            'type' => 'variable',
                            'value' => $name,
                            'opt' => true,
                        ];
                    } else {
                        $state = 'data';
                        $i--;
                    }
                    break;
                case 'eof':
                    if ($i - $data_marker > 0) {
                        $tokens[] = [
                            'type' => 'data',
                            'value' => substr($pattern, $data_marker, $i - $data_marker)
                        ];
                    }
                    break;
            }
        }

        return $tokens;
    }
}