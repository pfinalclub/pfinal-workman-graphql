<?php

declare(strict_types=1);

namespace PFinalClub\WorkermanGraphQL\Schema;

use GraphQL\Type\Schema as GraphQLSchema;

interface SchemaBuilderInterface
{
    public function build(): GraphQLSchema;
}

