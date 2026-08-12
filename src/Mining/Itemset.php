<?php

declare(strict_types=1);

namespace App\Mining;

use App\Dataset\CanonicalItemIndexKey;
use App\Dataset\ItemNormalizer;
use InvalidArgumentException;

class Itemset
{
    /** @var list<string> */
    private array $items;
    private string $identity;

    /**
     * Creates a canonical Itemset from an array of canonical item strings.
     *
     * @param array<int, string> $items Raw items that must already be canonical
     * @throws InvalidArgumentException if items are non-canonical, empty, or contain duplicates
     */
    public static function fromCanonicalItems(array $items): self
    {
        if (count($items) === 0) {
            throw new InvalidArgumentException("Itemset must contain at least one item.");
        }

        $seen = [];
        $normalizedItems = [];

        foreach ($items as $item) {
            // Validate that item is already strictly canonical
            $normalized = ItemNormalizer::normalize($item);
            if ($normalized !== $item) {
                throw new InvalidArgumentException("Item '{$item}' is not in canonical form.");
            }

            $encodedKey = CanonicalItemIndexKey::encode($item);
            if (isset($seen[$encodedKey])) {
                throw new InvalidArgumentException("Duplicate item '{$item}' in Itemset creation.");
            }
            $seen[$encodedKey] = true;
            $normalizedItems[] = $item;
        }

        // Sort items ascending by PHP binary strcmp byte comparison
        usort($normalizedItems, 'strcmp');

        // Build collision-safe length-prefixed binary identity
        $binaryKey = pack('N', count($normalizedItems));
        foreach ($normalizedItems as $it) {
            $binaryKey .= pack('N', strlen($it)) . $it;
        }

        return new self($normalizedItems, $binaryKey);
    }

    /**
     * Sealed private constructor to enforce canonical invariants.
     *
     * @param list<string> $items Sorted canonical items
     * @param string $identity Binary length-prefixed identity string
     */
    private function __construct(array $items, string $identity)
    {
        $this->items = $items;
        $this->identity = $identity;
    }

    /**
     * @return list<string> Sorted canonical item strings
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getSize(): int
    {
        return count($this->items);
    }

    /**
     * Returns the collision-safe length-prefixed binary identity string.
     */
    public function getIdentity(): string
    {
        return $this->identity;
    }

    /**
     * Returns hexadecimal representation of binary identity for display/debugging.
     */
    public function getHexIdentity(): string
    {
        return bin2hex($this->identity);
    }

    public function equals(Itemset $other): bool
    {
        return $this->identity === $other->identity;
    }

    /**
     * Deterministic Itemset comparator:
     * - Compare first differing item using binary strcmp.
     * - If all shared positions match, shorter itemset sorts before longer itemset.
     *
     * @return int -1 if $a < $b, 1 if $a > $b, 0 if equal
     */
    public static function compare(Itemset $a, Itemset $b): int
    {
        $itemsA = $a->getItems();
        $itemsB = $b->getItems();
        $minLen = min(count($itemsA), count($itemsB));

        for ($i = 0; $i < $minLen; $i++) {
            $cmp = strcmp($itemsA[$i], $itemsB[$i]);
            if ($cmp !== 0) {
                return $cmp < 0 ? -1 : 1;
            }
        }

        if (count($itemsA) === count($itemsB)) {
            return 0;
        }

        return count($itemsA) < count($itemsB) ? -1 : 1;
    }
}
