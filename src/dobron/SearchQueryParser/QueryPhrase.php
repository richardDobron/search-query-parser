<?php

namespace dobron\SearchQueryParser;

class QueryPhrase
{
	/**
	 * @var string
	 */
	private string $operator;
	/**
	 * @var string|null
	 */
	private ?string $field;
	/**
	 * @var string|array|null
	 */
	private $value;
	/**
	 * @var bool|null
	 */
	private ?bool $negate;
	/**
	 * @var QueryPhrase|null
	 */
	private ?QueryPhrase $prev;
	/**
	 * @var QueryPhrase|null
	 */
	private ?QueryPhrase $next;

	/**
	 * QueryPhrase constructor
	 *
	 * @param string            $operator
	 * @param string|null       $field
	 * @param string|array|null $value
	 * @param bool|null         $negate
	 * @param QueryPhrase|null  $prev
	 * @param QueryPhrase|null  $next
	 */
	public function __construct(string $operator, ?string $field, $value, ?bool $negate, ?QueryPhrase $prev = null, ?QueryPhrase $next = null)
	{
		$this->setOperator($operator);
		$this->setField($field);
		$this->setValue($value);
		$this->setNegate($negate);
		$this->setPrev($prev);
		$this->setNext($next);
	}

	/**
	 * @return string
	 */
	public function getOperator(): string
	{
		return $this->operator;
	}

	/**
	 * @param string $operator
	 */
	public function setOperator(string $operator): void
	{
		$this->operator = $operator;
	}

	/**
	 * @return string|null
	 */
	public function getField(): ?string
	{
		return $this->field;
	}

	/**
	 * @param string|null $field
	 */
	public function setField(?string $field): void
	{
		$this->field = $field;
	}

	/**
	 * @return string|array|null
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 * @param string|array|null $value
	 */
	public function setValue($value): void
	{
		$this->value = $value;
	}

	/**
	 * @return bool
	 */
	public function isNegate(): bool
	{
		return $this->negate === true;
	}

	/**
	 * @param bool|null $negate
	 */
	public function setNegate(?bool $negate): void
	{
		$this->negate = $negate;
	}

	/**
	 * @return QueryPhrase|null
	 */
	public function getPrev(): ?QueryPhrase
	{
		return $this->prev;
	}

	/**
	 * @param QueryPhrase|null $prev
	 */
	public function setPrev(?QueryPhrase $prev): void
	{
		$this->prev = $prev;
	}

	/**
	 * @return QueryPhrase|null
	 */
	public function getNext(): ?QueryPhrase
	{
		return $this->next;
	}

	/**
	 * @param QueryPhrase|null $next
	 */
	public function setNext(?QueryPhrase $next): void
	{
		$this->next = $next;
	}

	/**
	 * Checks if the string has spaces or not.
	 *
	 * @return bool
	 */
	public function hasSpaces(): bool
	{
		return preg_match('/\s/', $this->getValue());
	}

	/**
	 * Checks if the string has hyphen or not.
	 *
	 * @return bool
	 */
	public function hasHyphen(): bool
	{
		return preg_match('/-/', $this->getValue());
	}

	/**
	 * Checks if the string is quoted.
	 *
	 * @return bool
	 */
	public function isQuoted(): bool
	{
		return preg_match('/^".+?"$/', $this->getValue());
	}
}
