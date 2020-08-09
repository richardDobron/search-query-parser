<?php

namespace dobron\SearchQueryParser;

use JsonSerializable;
use function dobron\QueryTextParser\array_in_array;

class SearchQuery implements JsonSerializable
{
    /**
     * @var array
     */
    public array $text = [];

    /**
     * @var array
     */
    public array $match = [];

    /**
     * @var array
     */
    public array $excluded = [];

    /**
     * @var array
     */
    public array $errors = [];

    /**
     * @var null|array
     */
    public ?array $offsets = null;

    /**
     * @return array
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @param array $text
     */
    public function setText($text): void
    {
        $this->text = $text;
    }

    /**
     * @param string $text
     */
    public function addText(string $text): void
    {
        $this->text[] = [
            'column'   => 'text',
            'operator' => 'like',
            'negate'   => false,
            'value'    => '%' . $text . '%',
        ];
    }

    /**
     * @return array
     */
    public function getMatch(): array
    {
        return $this->match;
    }

    /**
     * @param array $match
     */
    public function setMatch(array $match): void
    {
        $this->match = $match;
    }

    /**
     * @return array
     */
    public function getQueries(): array
    {
        $queries = [];

        $matches = [
            $this->getMatch(),
            $this->getExcluded(),
            $this->getText(),
        ];

        foreach ($matches as $match) {
            if (isset($match['text'])) {
                $queries = array_merge($queries, $match['text']);
                unset($match['text']);
            }

            if (empty($match)) {
                continue;
            }

            $match = array_values($match);

            if (array_in_array($match)) {
                $queries = array_merge($queries, $match);
            } else {
                $queries[] = $match;
            }
        }

        return $queries;
    }

    /**
     * @return array
     */
    public function getExcluded(): array
    {
        return $this->excluded;
    }

    /**
     * @param array $excluded
     */
    public function setExcluded(array $excluded): void
    {
        $this->excluded = $excluded;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param array $errors
     */
    public function setErrors(array $errors): void
    {
        $this->errors = $errors;
    }

    /**
     * @param array $error
     */
    public function addError(array $error): void
    {
        $this->errors[] = $error;
    }

    /**
     * @return array|null
     */
    public function getOffsets(): ?array
    {
        return $this->offsets;
    }

    /**
     * @param array|null $offsets
     */
    public function setOffsets(?array $offsets): void
    {
        $this->offsets = $offsets;
    }

    /**
     * @param array $offset
     */
    public function addOffset(array $offset): void
    {
        $this->offsets[] = $offset;
    }

    public function jsonSerialize()
    {
        return [
            'text'     => $this->getText(),
            'match'    => $this->getMatch(),
            'excluded' => $this->getExcluded(),
            'offsets'  => $this->getOffsets()
        ];
    }
}
