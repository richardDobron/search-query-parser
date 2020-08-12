<?php

namespace dobron\SearchQueryParser;

class Compiler
{
	/**
	 * @var array $options
	 */
	private array $options;
	/**
	 * @var QueryPhrase[] $options
	 */
	private array $query;

	/**
	 * Compiler constructor
	 *
	 * @param null|array $data
	 * @param array      $options
	 */
	public function __construct(?array $data = null, array $options = [])
	{
		if (is_array($data)) {
			$this->setQuery($data);
		}

		$this->setOptions($options);
	}

	/**
	 * @return array
	 */
	public function getOptions(): array
	{
		return $this->options;
	}

	/**
	 * @param array $options
	 *
	 * @return Compiler
	 */
	public function setOptions(array $options): self
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
	 * @return array
	 */
	public function getQuery(): array
	{
		return $this->query;
	}

	/**
	 * @param array $query
	 *
	 * @return Compiler
	 */
	public function setQuery(array $query): self
	{
		$this->query = $query;

		return $this;
	}

	/**
	 * @param array $data
	 *
	 * @return $this
	 */
	private function parse(array &$data)
	{
		foreach ($data as $index => &$parts) {
			if (is_array($parts)) {
				$field = $parts[0] ?? null;
				$value = $parts[1] ?? null;
				$operator = $parts[2] ?? '=';
				$negate = $parts[3] ?? null;

				if (count($parts) === 2) {
					if (is_bool($value)) {
						$negate = $value;
						$value = $field;

						$field = null;
					}
				} else if (count($parts) === 1) {
					$value = $field;
					$field = null;
				}

				if (is_string($field) || is_null($field)) {
					$parts = new QueryPhrase($operator, $field, $value, $negate);
				}
			}
		}

		foreach ($data as $index => &$parts) {
			if ($parts instanceof QueryPhrase) {
				if (isset($data[$index - 1])) {
					$parts->setPrev($data[$index - 1]);
				}

				if (isset($data[$index + 1])) {
					$parts->setNext($data[$index + 1]);
				}
			}
		}

		return $this;
	}

	/**
	 * @param QueryPhrase $queryPhrase
	 *
	 * @return bool
	 */
	protected function isRange(QueryPhrase $queryPhrase): bool
	{
		return in_array($queryPhrase->getField(), $this->options['ranges'], true);
	}

	/**
	 * @param QueryPhrase $queryPhrase
	 *
	 * @return bool
	 */
	protected function isKeyword(QueryPhrase $queryPhrase): bool
	{
		return in_array($queryPhrase->getField(), $this->options['keywords'], true);
	}

	/**
	 * @param QueryPhrase $queryPhrase
	 *
	 * @return bool
	 */
	private function isInvalid(QueryPhrase $queryPhrase): bool
	{
		if (empty($queryPhrase->getValue())) {
			return true;
		}

		if (is_array($queryPhrase->getValue()) && $this->isRange($queryPhrase)) {
			$range = $queryPhrase->getValue();

			if (!$range || count($range) > 2) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param QueryPhrase $queryPhrase
	 *
	 * @return string|null
	 */
	public function formatQueryPhrase(QueryPhrase $queryPhrase): ?string
	{
		$hasCommas = strpos(implode('', (array)$queryPhrase->getValue()), ',') !== false;

		if (is_array($queryPhrase->getValue())) {
			$glue = ' ';

			if ($this->isKeyword($queryPhrase)) {
				$glue = ',';
			} elseif ($this->isRange($queryPhrase)) {
				$glue = '-';

				if ($this->isInvalid($queryPhrase)) {
					return null;
				}

				$range = $queryPhrase->getValue();

				$queryPhrase->setValue([
					$range[0],
					$range[1] ?? $range[0],
				]);
			}

			$queryPhrase->setValue(implode($glue, $queryPhrase->getValue()));
		}

		if ($this->canQuote($queryPhrase, $hasCommas)) {
			$queryPhrase->setValue('"'.$queryPhrase->getValue().'"');
		}

		$text = '';

		if ($queryPhrase->isNegate()) {
			$text .= '-';
		}

		if (!empty($queryPhrase->getField())) {
			$text .= $queryPhrase->getField().':';

			if ($queryPhrase->getOperator() !== '=') {
				$text .= $queryPhrase->getOperator();
			}
		}

		$text .= $queryPhrase->getValue();

		return $text;
	}

	/**
	 * @param QueryPhrase $queryPhrase
	 * @param bool        $hasCommas
	 *
	 * @return bool
	 */
	private function canQuote(QueryPhrase $queryPhrase, bool $hasCommas): bool
	{
		return (
			!empty($this->options['alwaysQuote'])
			|| (!$this->isRange($queryPhrase) && $queryPhrase->hasHyphen()) || (
				(
					($queryPhrase->getPrev() !== null && $queryPhrase->getPrev()->getField() && !$this->isInvalid($queryPhrase->getPrev()))
					|| ($queryPhrase->getNext() !== null && $queryPhrase->getNext()->getField() && !$this->isInvalid($queryPhrase->getNext()))
				) && !$queryPhrase->isQuoted() && (
					$queryPhrase->hasSpaces() && (
						$hasCommas || !$queryPhrase->getField()
					)
				)
			)
		);
	}

	/**
	 * @return string
	 */
	public function compile(): string
	{
		$this->parse($this->query);

		$search = [];

		foreach ($this->query as $phrase) {
			$query = $this->formatQueryPhrase($phrase);

			if (!empty($query)) {
				$search[] = $query;
			}
		}

		return implode(' ', $search);
	}
}
