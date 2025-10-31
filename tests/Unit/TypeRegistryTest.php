<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Tests\Unit;

use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Definition\Type;
use PFinalClub\WorkermanGraphQL\Exception\SchemaException;
use PFinalClub\WorkermanGraphQL\Schema\TypeRegistry;
use PHPUnit\Framework\TestCase;

final class TypeRegistryTest extends TestCase
{
    private TypeRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new TypeRegistry();
    }

    public function testCanRegisterType(): void
    {
        $type = Type::string();
        $this->registry->register('String', $type);

        $this->assertTrue($this->registry->has('String'));
        $this->assertSame($type, $this->registry->get('String'));
    }

    public function testHasReturnsFalseForUnregisteredType(): void
    {
        $this->assertFalse($this->registry->has('NonExistent'));
    }

    public function testGetThrowsExceptionForUnregisteredType(): void
    {
        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('Type "NonExistent" is not registered.');

        $this->registry->get('NonExistent');
    }

    public function testCanGetAllRegisteredTypes(): void
    {
        $stringType = Type::string();
        $intType = Type::int();

        $this->registry->register('String', $stringType);
        $this->registry->register('Int', $intType);

        $all = $this->registry->all();

        $this->assertCount(2, $all);
        $this->assertSame($stringType, $all['String']);
        $this->assertSame($intType, $all['Int']);
    }
}

