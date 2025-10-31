<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Tests\Unit;

use PFinalClub\WorkermanGraphQL\Context;
use PFinalClub\WorkermanGraphQL\Http\Request;
use PFinalClub\WorkermanGraphQL\Http\RequestInterface;
use PHPUnit\Framework\TestCase;

final class ContextTest extends TestCase
{
    private RequestInterface $request;

    protected function setUp(): void
    {
        $this->request = new Request('GET', '/graphql');
    }

    public function testCanCreateContextWithRequest(): void
    {
        $context = new Context($this->request);

        $this->assertSame($this->request, $context->getRequest());
    }

    public function testCanCreateContextWithValues(): void
    {
        $values = ['user_id' => 123, 'role' => 'admin'];
        $context = new Context($this->request, $values);

        $this->assertEquals($values, $context->all());
    }

    public function testCanGetValue(): void
    {
        $context = new Context($this->request, ['key' => 'value']);

        $this->assertEquals('value', $context->get('key'));
        $this->assertNull($context->get('nonexistent'));
        $this->assertEquals('default', $context->get('nonexistent', 'default'));
    }

    public function testCanSetValue(): void
    {
        $context = new Context($this->request);
        $newContext = $context->withValue('key', 'value');

        $this->assertNotSame($context, $newContext);
        $this->assertNull($context->get('key'));
        $this->assertEquals('value', $newContext->get('key'));
    }

    public function testCanUpdateRequest(): void
    {
        $context = new Context($this->request);
        $newRequest = new Request('POST', '/graphql');
        $newContext = $context->withRequest($newRequest);

        $this->assertNotSame($context, $newContext);
        $this->assertSame($this->request, $context->getRequest());
        $this->assertSame($newRequest, $newContext->getRequest());
    }
}

