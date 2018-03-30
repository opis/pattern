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
    /** @var array */
    protected $options;

    /** @var array */
    protected $tokens = [];

    const CAPTURE_LEFT = 1;
    const CAPTURE_RIGHT = 2;
    const ALLOW_OPT_TRAIL = 4;

    const START_SYMBOL = 0;
    const END_SYMBOL = 1;
    const SEPARATOR_SYMBOL = 2;
    const OPT_SYMBOL = 3;
    const CAPTURE_MODE = 4;
    const REGEX_DELIMITER = 5;
    const REGEX_MODIFIER = 6;
    const DEFAULT_REGEX_EXP = 7;

    /**
     * Constructor
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $options += [
            self::START_SYMBOL => '{',
            self::END_SYMBOL => '}',
            self::SEPARATOR_SYMBOL => '/',
            self::OPT_SYMBOL => '?',
            self::CAPTURE_MODE => self::CAPTURE_LEFT | self::ALLOW_OPT_TRAIL,
            self::REGEX_DELIMITER => '~',
            self::REGEX_MODIFIER => 'u',
        ];

        if (!isset($options[self::DEFAULT_REGEX_EXP])) {
            $expr = preg_quote($options[self::SEPARATOR_SYMBOL], $options[self::REGEX_DELIMITER]);
            $options[self::DEFAULT_REGEX_EXP] = '[^' . $expr . ']+';
        }

        $this->options = $options;
    }

    /**
     * @param string $pattern
     * @param array $placeholders
     * @return string
     */
    public function getRegex(string $pattern, array $placeholders): string
    {
        $regex = [];
        $tokens = $this->getTokens($pattern);
        $delimiter = $this->options[self::REGEX_DELIMITER];
        $modifier = $this->options[self::REGEX_MODIFIER];
        $default_exp = $this->options[self::DEFAULT_REGEX_EXP];
        $capture_left = ($this->options[self::CAPTURE_MODE] & self::CAPTURE_LEFT) === self::CAPTURE_LEFT;
        $capture_right = ($this->options[self::CAPTURE_MODE] & self::CAPTURE_RIGHT) === self::CAPTURE_RIGHT;
        $allow_trail = ($this->options[self::CAPTURE_MODE] & self::ALLOW_OPT_TRAIL) === self::ALLOW_OPT_TRAIL;

        $sep = preg_quote($this->options[self::SEPARATOR_SYMBOL], $delimiter);

        for ($i = 0, $l = count($tokens); $i < $l; $i++) {
            $t = $tokens[$i];
            $p = $tokens[$i - 1] ?? null;
            $n = $tokens[$i + 1] ?? null;

            if ($t['type'] === 'separator') {
                if ($capture_left) {
                    if (isset($n)) {
                        if ($n['type'] === 'variable' && $n['opt']) {
                            if (isset($p)) {
                                $regex[] = '(' . $sep . '(?P<' . preg_quote($n['value'], $delimiter) .
                                    '>(' . ($placeholders[$n['value']] ?? $default_exp) . ')))?';
                            } else {
                                $regex[] = $sep;
                                $expr = '(?P<' . preg_quote($n['value'], $delimiter) .
                                    '>(' . ($placeholders[$n['value']] ?? $default_exp) . '))';
                                $regex[] = '(' . $expr . ($allow_trail ? $sep . '?' : '') . ')?';
                            }
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
                    if (isset($n) && $n['type'] === 'separator' && $t['opt']) {
                        $regex[] = '((?P<' . preg_quote($t['value'], $delimiter) .
                            '>(' . ($placeholders[$t['value']] ?? $default_exp) . '))' . $sep . ')?';
                        $i++;
                    } else {
                        $regex[] = '(?P<' . preg_quote($t['value'], $delimiter) .
                            '>(' . ($placeholders[$t['value']] ?? $default_exp) . '))';
                    }
                } else {
                    $regex[] = '(?P<' . preg_quote($t['value'], $delimiter) .
                        '>(' . ($placeholders[$t['value']] ?? $default_exp) . '))';
                    if (!isset($n) && $allow_trail) {
                        $regex[] = $sep . '?';
                    }
                }
            } else {
                $regex[] = preg_quote($t['value'], $delimiter);
                if ($capture_left && $allow_trail && !isset($n)) {
                    $regex[] = $sep . '?';
                }
            }
        }

        return $delimiter . '^' . implode('', $regex) . '$' . $delimiter . $modifier;
    }

    /**
     * @param string $pattern
     * @return string[]
     */
    public function getNames(string $pattern): array
    {
        $names = [];
        foreach ($this->getTokens($pattern) as $token) {
            if ($token['type'] === 'variable') {
                if (!in_array($token['value'], $names)) {
                    $names[] = $token['value'];
                }
            }
        }
        return $names;
    }

    /**
     * @param string $regex
     * @param string $path
     * @return array
     */
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

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param string $pattern
     * @return array
     */
    protected function getTokens(string $pattern): array
    {
        $key = md5($pattern);

        if (isset($this->tokens[$key])) {
            return $this->tokens[$key];
        }

        $sym_separator = $this->options[self::SEPARATOR_SYMBOL];
        $sym_opt = $this->options[self::OPT_SYMBOL];
        $sym_start = $this->options[self::START_SYMBOL];
        $sym_end = $this->options[self::END_SYMBOL];

        $state = 'data';
        $tokens = [];
        $data_marker = 0;
        for ($i = 0, $l = strlen($pattern); $i <= $l; $i++) {
            if ($i === $l) {
                $c = null;
            } else {
                $c = $pattern[$i];
            }
            switch ($state) {
                case 'data':
                    if ($c === $sym_separator) {
                        if ($i - $data_marker > 0) {
                            $tokens[] = [
                                'type' => 'data',
                                'value' => substr($pattern, $data_marker, $i - $data_marker)
                            ];
                        }
                        $tokens[] = [
                            'type' => 'separator',
                            'value' => $c,
                        ];
                        $data_marker = $i + 1;
                    } elseif ($c === $sym_start) {
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
                    } elseif ($c === $sym_end) {
                        $name = substr($pattern, $data_marker + 1, $i - $data_marker - 1);
                        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name)) {
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
                    } elseif ($c === $sym_end) {
                        $name = substr($pattern, $data_marker + 1, ($i - 1) - $data_marker - 1);
                        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name)) {
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

        $this->tokens[$key] = $tokens;
        return $tokens;
    }
}