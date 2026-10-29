<?php

namespace Tests\Unit;

use App\Support\DepartmentRouting;
use Tests\TestCase;

class DepartmentRoutingTest extends TestCase
{
    public function test_incident_categories_are_assigned_to_the_expected_departments(): void
    {
        $this->assertSame(['Fire'], DepartmentRouting::categoriesFor('BFP'));
        $this->assertSame(['Fire', 'Crime/Security', 'Accident', 'Medical'], DepartmentRouting::categoriesFor('MDRRMO'));
        $this->assertSame(['Fire', 'Crime/Security', 'Accident'], DepartmentRouting::categoriesFor('PNP'));

        $this->assertSame(['BFP', 'MDRRMO', 'PNP'], DepartmentRouting::departmentsForCategory('Fire'));
        $this->assertSame(['MDRRMO', 'PNP'], DepartmentRouting::departmentsForCategory('Crime/Security'));
        $this->assertSame(['MDRRMO', 'PNP'], DepartmentRouting::departmentsForCategory('Accident'));
        $this->assertSame(['MDRRMO'], DepartmentRouting::departmentsForCategory('Medical'));
    }
}
