<?php

use Tests\Helpers\SchemaTestHelper;

pest()->extend(Tests\TestCase::class)->in('Feature');
pest()->extend(Tests\TestCase::class)->in('Stress');
pest()->extend(Tests\TestCase::class)->in('Unit');
pest()->extend(Tests\TestCase::class)->in('Integration');

function schema()
{
    return SchemaTestHelper::createSchemaBuilder();
}