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

class Builder
{
    const CAPTURE_LEFT = 0;
    const CAPTURE_RIGHT = 1;
    const CAPTURE_TRAIL = 2;
    const ADD_OPT_SEPARATOR = 4;
    const STANDARD_MODE = self::CAPTURE_LEFT | self::CAPTURE_TRAIL | self::ADD_OPT_SEPARATOR;

    const START_MARKER = 0;
    const END_MARKER = 1;
    const SEGMENT_DELIMITER = 2;
    const OPT_PLACEHOLDER_SYMBOL = 3;
    const CAPTURE_MODE = 4;
    const REGEX_DELIMITER = 5;
    const REGEX_MODIFIER = 6;
    const DEFAULT_REGEX_EXP = 7;

    /** @var string */
    protected $startMarker;

    /** @var string */
    protected $endMarker;

    /** @var string */
    protected $separator;

    /** @var  int */
    protected $captureMode;

    /** @var bool */
    protected $captureLeft;

    /** @var bool */
    protected $captureTrail;

    /** @var bool */
    protected $addOptionalSeparator;

    /** @var string */
    protected $optional;

    /** @var string */
    protected $delimiter;

    /** @var string */
    protected $modifier;

    /** @var string */
    protected $placeholder;

    /** @var array */
    protected $comp;

    /** @var  null|array */
    protected $options;

    /**
     * Compiler constructor.
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->captureMode = $capture = (int)($options[self::CAPTURE_MODE] ?? self::STANDARD_MODE);
        $this->startMarker = $startTag = (string)($options[self::START_MARKER] ?? '{');
        $this->endMarker = $endTag = (string)($options[self::END_MARKER] ?? '}');
        $this->separator = $separator = (string)($options[self::SEGMENT_DELIMITER] ?? '/');
        $this->optional = $optional = (string)($options[self::OPT_PLACEHOLDER_SYMBOL] ?? '?');
        $this->delimiter = $delimiter = (string)($options[self::REGEX_DELIMITER] ?? '`');
        $this->modifier = $modifier = (string)($options[self::REGEX_MODIFIER] ?? 'u');
        $this->placeholder = $wildcard = (string)($options[self::DEFAULT_REGEX_EXP] ?? '[^' . preg_quote($separator, $delimiter) . ']+');
        $this->captureLeft = ($capture & static::CAPTURE_RIGHT) === static::CAPTURE_LEFT;
        $this->captureTrail = ($capture & static::CAPTURE_TRAIL) === static::CAPTURE_TRAIL;
        $this->addOptionalSeparator = ($capture & static::ADD_OPT_SEPARATOR) === static::ADD_OPT_SEPARATOR;

        $this->comp = [
            preg_quote($startTag, $delimiter),
            preg_quote($endTag, $delimiter),
            preg_quote($separator, $delimiter),
            preg_quote($optional, $delimiter)
        ];
    }

    /**
     * @param string $pattern
     * @param array $placeholders
     * @return string
     */
    public function getRegex(string $pattern, array $placeholders = []): string
    {
        $names = $this->getNames($pattern);
        list($st, $et, $sep, $opt) = $this->comp;
        $pattern = preg_quote($pattern, $this->delimiter);

        if (empty($names)) {
            goto TRAIL;
        }

        foreach ($names as $name) {
            if (!isset($placeholders[$name])) {
                $placeholders[$name] = $this->placeholder;
            }
        }

        $unmatched = array();
        $position = -1;

        foreach ($placeholders as $key => $value) {
            //$original = $key;
            $key = preg_quote($key, $this->delimiter);
            $value = '(?P<' . $key . '>(' . $value . '))';
            $count = 0;
            $position++;
            if ($this->captureLeft) {
                $pattern = str_replace($sep . $st . $key . $et, $sep . $value, $pattern, $count);

                if ($count == 0) {
                    if ($position === 0 && strpos($pattern, $sep . $st . $key . $opt . $et) === 0) {
                        $pattern = str_replace($sep . $st . $key . $opt . $et, '(' . $sep . $value . '?)?', $pattern, $count);
                    } else {
                        $pattern = str_replace($sep . $st . $key . $opt . $et, '(?:' . $sep . $value . ')?', $pattern, $count);
                    }
                }
            } else {
                $pattern = str_replace($st . $key . $et . $sep, $value . $sep, $pattern, $count);
                if ($count == 0) {
                    $pattern = str_replace($st . $key . $opt . $et . $sep, '(' . $value . $sep . ')?', $pattern, $count);
                }
            }

            if ($count == 0) {
                $unmatched[$key] = $value;
            }
        }

        if (!empty($unmatched)) {
            foreach ($unmatched as $key => $value) {
                if ($this->addOptionalSeparator) {
                    $value = $this->captureLeft ? '(' . $sep . ')?' . $value : $value . '(' . $sep . ')?';
                }

                $pattern = str_replace($st . $key . $et, $value, $pattern, $count);

                if ($count == 0) {
                    $pattern = str_replace($st . $key . $opt . $et, '(' . $value . ')?', $pattern);
                }
            }
        }

        TRAIL:

        if ($this->captureTrail) {
            if ($this->captureLeft) {
                if (substr($pattern, strlen($pattern) - strlen($sep)) !== $sep) {
                    $pattern = $pattern . '(' . $sep . ')?';
                }
            } else {
                if (substr($pattern, 0, strlen($sep)) !== $sep) {
                    $pattern = '(' . $sep . ')?' . $pattern;
                }
            }
        }

        return $this->delimiter . '^' . $pattern . '$' . $this->delimiter . $this->modifier;
    }

    /**
     * @param string $pattern
     * @return array
     */
    public function getNames(string $pattern): array
    {
        list($st, $et) = $this->comp;

        $regex = $this->delimiter . $st . '(.*?)' . $et . $this->delimiter;

        preg_match_all($regex, $pattern, $matches);

        $optional = $this->optional;

        return array_map(function ($m) use ($optional) {
            return trim($m, $optional);
        }, $matches[1]);
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
            return is_string($value) && strlen($value) > 0 && $parameters[$value] != null;
        });

        return array_intersect_key($parameters, array_flip($keys));
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        if ($this->options === null) {
            $this->options = [
                self::CAPTURE_MODE => $this->captureMode,
                self::START_MARKER => $this->startMarker,
                self::END_MARKER => $this->endMarker,
                self::OPT_PLACEHOLDER_SYMBOL => $this->optional,
                self::REGEX_DELIMITER => $this->delimiter,
                self::REGEX_MODIFIER => $this->modifier,
                self::DEFAULT_REGEX_EXP => $this->placeholder,
            ];
        }

        return $this->options;
    }
}