<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guests_are_redirected_from_the_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect();
    }
}
