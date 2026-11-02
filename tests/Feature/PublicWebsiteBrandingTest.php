<?php

use Illuminate\Support\Facades\Config;

it('renders the marketplace homepage with its canonical site url', function () {
    Config::set('app.url', 'https://numanfreight.example');
    $this->withoutVite();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->where('appUrl', 'https://numanfreight.example')
        );
});

it('serves the Numan Freight browser and social brand assets', function () {
    $favicon = file_get_contents(public_path('favicon.svg'));
    $socialImage = getimagesize(public_path('brand/numanfreight-social.png'));

    expect($favicon)
        ->toContain('Numan Freight')
        ->and($socialImage[0])->toBe(1440)
        ->and($socialImage[1])->toBe(780);
});
