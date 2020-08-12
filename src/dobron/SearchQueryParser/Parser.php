<?php

namespace dobron\SearchQueryParser;

class Parser
{
    /**
     * @var array $options
     */
    private array $options;
    /**
     * @var SearchQuery $query
     */
    protected SearchQuery $query;

    const INV_OPERATORS = [
        '='  => '<>',
        '>=' => '<=',
        '>'  => '<',
        '<=' => '>=',
    ];

    const REGEX_QUOTE = "/^\"|\"$|^'|'$/";
    const REGEX_OPERATOR = '/^(<=?|>=?)/';
    const REGEX_PHRASE = '/(\S+:\'(?:[^\'\\\\]|\\\\.)*\')
                           |(\S+:"(?:[^"\\\\]|\\\\.)*")
                           |(\S+:(?:\s+)?(?:[^: ]+(?:\s+[^:"\- ]+(?:\b|\s|$)(?!:))*))
                           |(-?"(?:[^"\\\\]|\\\\.)*")
                           |(-?\'(?:[^\'\\\\]|\\\\.)*\')|\S+|\S+:\S+/x';

    /**
     * SearchQuery constructor
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }

    /**
     * @param array $options
     *
     * @return $this
     */
    public function setOptions(array $options = []): self
    {
        $options['offsets'] = $options['offsets'] ?? true;
        $options['ranges'] = $options['ranges'] ?? [];

        if (is_string($options['ranges'])) {
            $options['ranges'] = explode(',', $options['ranges']);
        }

        $options['keywords'] = $options['keywords'] ?? [];

        if (is_string($options['keywords'])) {
            $options['keywords'] = explode(',', $options['keywords']);
        }

        $this->options = $options;

        return $this;
    }

    /**
     * @param string|null $string
     *
     * @return SearchQuery
     */
    public function parse(?string $string): SearchQuery
    {
        if (!$string) {
            $string = '';
        }

        $string = trim($string);

        $this->query = new SearchQuery();

        if (!empty($this->options['offsets'])) {
            $this->query->setOffsets([]);
        }

        $terms = [];

        preg_match_all(self::REGEX_PHRASE, $string, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $match) {
            $term = $match[0];
            $isExcludedTerm = $this->isExcluded($term);

            $sepIndex = strpos($term, ':');
            if ($sepIndex !== false) {
                $key = substr($term, 0, $sepIndex);
                $val = trim(substr($term, $sepIndex + 1));

                $quoted = $this->isQuoted($val);

                $val = preg_replace(self::REGEX_QUOTE, '', $val);

                $val = preg_replace('/\\\\(.?)/', '$1', $val);

                $operator = $this->detectOperator($val, $isExcludedTerm);

                $val = preg_replace(self::REGEX_OPERATOR, '', $val);

                $terms[] = [
                    'keyword'     => $key,
                    'value'       => $val,
                    'quoted'      => $quoted,
                    'operator'    => $operator,
                    'negate'      => $isExcludedTerm,
                    'offsetStart' => $match[1],
                    'offsetEnd'   => $match[1] + strlen($term),
                ];
            } else {
                if ($isExcludedTerm) {
                    $term = substr($term, 1);
                }

                $term = preg_replace(self::REGEX_QUOTE, '', $term);

                $term = preg_replace('/\\\\(.?)/', '$1', $term);

                $term = preg_replace(self::REGEX_OPERATOR, '', $term);

                if ($isExcludedTerm) {
                    $excluded = $this->query->getExcluded();

                    $value = [
                        'column'   => 'text',
                        'operator' => 'not like',
                        'negate'   => true,
                        'value'    => '%' . $term . '%',
                    ];

                    if (!empty($excluded['text'])) {
                        if (!is_array($excluded['text'])) {
                            $excluded['text'] = [$excluded['text']];
                        }

                        $excluded['text'][] = $value;
                    } else {
                        $excluded['text'] = [$value];
                    }

                    $this->query->setExcluded($excluded);
                } else {
                    $terms[] = [
                        'text'        => $term,
                        'offsetStart' => $match[1],
                        'offsetEnd'   => $match[1] + strlen($term),
                    ];
                }
            }
        }

        $terms = array_reverse($terms);

        while ($term = array_pop($terms)) {
            if (!empty($term['text'])) {
                $this->query->addText($term['text']);

                if (!empty($this->options['offsets'])) {
                    $this->query->addOffset($term);
                }
            } else {
                $key = $term['keyword'];
                $isExclusion = false;

                $isKeyword = $this->isKeyword($key);
                $isRange = $this->isRange($key);

                if ($this->isExcluded($key) && ($isKeyword || $isRange)) {
                    $key = $this->cleanKeyword($key);
                    $isExclusion = true;
                }

                if ($isKeyword) {
                    if (!empty($this->options['offsets'])) {
                        $this->query->addOffset([
                            'keyword'     => $key,
                            'value'       => $term['value'],
                            'offsetStart' => $isExclusion ? $term['offsetStart'] + 1 : $term['offsetStart'],
                            'offsetEnd'   => $term['offsetEnd']
                        ]);
                    }

                    $value = $term['value'];

                    if (!empty($value)) {
                        if ($isExclusion) {
                            $this->processKeyword('excluded', $key, $term);
                        } else {
                            $this->processKeyword('match', $key, $term);
                        }
                    }
                } elseif ($isRange) {
                    if (!empty($this->options['offsets'])) {
                        $this->query->addOffset($term);
                    }

                    $value = $term['value'];

                    $rangeValues = preg_split('/(?<=\d)-/', $value);

                    $operator = 'between';
                    if ($term['negate']) {
                        $operator = 'not between';
                    }

                    $matched = $this->query->getMatch();
                    $matched[$key] = [
                        'column'   => $key,
                        'operator' => $operator,
                        'negate'   => $term['negate'],
                        'value'    => [],
                    ];

                    if (count($rangeValues) === 2) {
                        $matched[$key]['value'] = [
                            'from' => $rangeValues[0],
                            'to'   => $rangeValues[1] ?? $rangeValues[0]
                        ];

                        $this->query->setMatch($matched);
                    } else {
                        $this->query->addError([
                            "Invalid values for range '{$key}'.",
                            [
                                'value' => $rangeValues
                            ],
                        ]);
                    }
                } else {
                    $text = $term['keyword'] . ':' . $term['value'];
                    $this->query->addText($text);

                    if (!empty($this->options['offsets'])) {
                        $this->query->addOffset([
                            'text'        => $text,
                            'offsetStart' => $term['offsetStart'],
                            'offsetEnd'   => $term['offsetEnd']
                        ]);
                    }
                }
            }
        }

        if (empty($this->query->getText())) {
            $this->query->setText([]);
        }

        return $this->query;
    }

    /**
     * @param string $keyword
     *
     * @return bool
     */
    protected function isRange(string $keyword): bool
    {
        $keyword = $this->cleanKeyword($keyword);

        return in_array($keyword, $this->options['ranges'], true);
    }

    /**
     * @param string $keyword
     *
     * @return bool
     */
    protected function isKeyword(string $keyword): bool
    {
        $keyword = $this->cleanKeyword($keyword);

        return in_array($keyword, $this->options['keywords'], true);
    }

    /**
     * @param string $value
     * @param bool $invert
     *
     * @return string
     */
    protected function detectOperator(string $value, $invert = false): string
    {
        preg_match(self::REGEX_OPERATOR, $value, $match);

        $operator = $match[1] ?? '=';

        if ($invert) {
            $operator = self::INV_OPERATORS[$operator];
        }

        return $operator;
    }

    /**
     * @param string $keyword
     *
     * @return bool
     */
    protected function isQuoted(string $keyword): bool
    {
        return preg_match('/^".+?"$/', $keyword);
    }

    /**
     * @param string $keyword
     *
     * @return string
     */
    protected function cleanKeyword(string $keyword): string
    {
        if ($this->isExcluded($keyword)) {
            $keyword = substr($keyword, 1);
        }

        return $keyword;
    }

    /**
     * @param string $keyword
     *
     * @return bool
     */
    protected function isExcluded(string $keyword): bool
    {
        return preg_match('/^-/', $keyword);
    }

    /**
     * @param string $type
     * @param string $key
     * @param array $term
     */
    protected function processKeyword(string $type, string $key, array $term): void
    {
        $value = $term['value'];

        if (empty($term['quoted'])) {
            $values = explode(',', $value);
        } else {
            $values = [$value];
        }

        if ($type === 'match') {
            $object = $this->query->getMatch();
        } elseif ($type === 'excluded') {
            $object = $this->query->getExcluded();
        } else {
            return;
        }

        $value = [
            'column'   => $this->cleanKeyword($term['keyword']),
            'operator' => $term['operator'],
            'negate'   => $term['negate'],
            'value'    => $value,
        ];

        if (array_key_exists($key, $object)) {
            if (is_array($object[$key])) {
                if (count($values) > 1) {
                    $object[$key] = array_merge($object[$key], $values);
                } else {
                    $object[$key] = [$object[$key]];
                    $object[$key][] = $value;
                }
            } else {
                $object[$key] = [$object[$key]];
                $object[$key][] = $value;
            }
        } else {
            if (count($values) > 1) {
                $object[$key] = $values;
            } else {
                $object[$key] = $value;
            }
        }

        if ($type === 'match') {
            $this->query->setMatch($object);
        } elseif ($type === 'excluded') {
            $this->query->setExcluded($object);
        }
    }
}
