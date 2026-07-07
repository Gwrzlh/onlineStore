<?php

test('the application api is running successfully', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/orders');
    
    $response->assertStatus(405);
});