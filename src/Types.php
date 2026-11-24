<?php

declare(strict_types=1);

namespace Ray\FakeQuery;

/**
 * Ray.FakeQuery Domain Types for Psalm
 *
 * JSON Data Types; numeric object keys such as "0" decode to int keys, so JsonRow keys are array-key.
 *
 * @psalm-type JsonRow = array<array-key, mixed>
 * @psalm-type JsonRowList = list<JsonRow>
 * @psalm-type DbQueryIdSet = array<string, true>
 */
final class Types
{
}
