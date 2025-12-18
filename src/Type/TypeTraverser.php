<?php declare(strict_types = 1);

namespace PHPStan\Type;

final class TypeTraverser
{

	/** @var callable(Type $type, callable(Type): Type $traverse): Type */
	private $cb;

	/** @var array<string, bool> */
	private array $visitedTypes = [];

	/**
	 * Map a Type recursively
	 *
	 * For every Type instance, the callback can return a new Type, and/or
	 * decide to traverse inner types or to ignore them.
	 *
	 * The following example converts constant strings to objects, while
	 * preserving unions and intersections:
	 *
	 * TypeTraverser::map($type, function (Type $type, callable $traverse): Type {
	 *     if ($type instanceof UnionType || $type instanceof IntersectionType) {
	 *         // Traverse inner types
	 *         return $traverse($type);
	 *     }
	 *     if ($type instanceof ConstantStringType) {
	 *         // Replaces the current type, and don't traverse
	 *         return new ObjectType($type->getValue());
	 *     }
	 *     // Replaces the current type, and don't traverse
	 *     return new MixedType();
	 * });
	 *
	 * @api
	 * @param callable(Type $type, callable(Type): Type $traverse): Type $cb
	 */
	public static function map(Type $type, callable $cb): Type
	{
		$self = new self($cb);

		return $self->mapInternal($type);
	}

	/** @param callable(Type $type, callable(Type): Type $traverse): Type $cb */
	private function __construct(callable $cb)
	{
		$this->cb = $cb;
	}

	/** @internal */
	public function mapInternal(Type $type): Type
	{
		$typeHash = spl_object_hash($type);

		// Prevent infinite recursion by tracking visited types
		if (isset($this->visitedTypes[$typeHash])) {
			// Return the type as-is if we've already processed it
			return $type;
		}

		$this->visitedTypes[$typeHash] = true;
		$result = ($this->cb)($type, [$this, 'traverseInternal']);
		unset($this->visitedTypes[$typeHash]);

		return $result;
	}

	/** @internal */
	public function traverseInternal(Type $type): Type
	{
		return $type->traverse([$this, 'mapInternal']);
	}

}
