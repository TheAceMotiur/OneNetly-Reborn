<?php

use Inertia\Testing\AssertableInertia as Assert;

test('missing pages render the branded error screen', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error/Index')
            ->where('status', 404));
});
