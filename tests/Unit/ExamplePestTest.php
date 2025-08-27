<?php

declare(strict_types=1);

describe('Basic Functionality', function () {
    it('can perform basic assertions', function () {
        expect(true)->toBeTrue();
        expect(false)->toBeFalse();
        expect(1)->toBeOne();
        expect('hello')->toBeString();
        expect([])->toBeArray();
    });

    it('can use datasets', function (string $browser) {
        expect($browser)->toBeString();
        expect(strlen($browser))->toBeGreaterThan(0);
    })->with('browsers');

    it('can test mathematical operations', function () {
        expect(2 + 2)->toBe(4);
        expect(10 / 2)->toBe(5);
        expect(3 * 3)->toBe(9);
    });
});

describe('String Operations', function () {
    it('can manipulate strings', function () {
        $string = 'Hello World';

        expect($string)->toContain('World');
        expect(strtoupper($string))->toBe('HELLO WORLD');
        expect(strlen($string))->toBe(11);
    });

    it('can work with JSON', function () {
        $data = ['name' => 'John', 'age' => 30];
        $json = json_encode($data);

        expect($json)->toBeJson();
        expect(json_decode($json, true))->toBe($data);
    });
});

describe('Array Operations', function () {
    it('can work with arrays', function () {
        $array = [1, 2, 3, 4, 5];

        expect($array)->toHaveCount(5);
        expect($array)->toContain(3);
        expect(array_sum($array))->toBe(15);
    });

    it('can filter arrays', function () {
        $numbers = [1, 2, 3, 4, 5, 6];
        $evens = array_filter($numbers, fn ($n) => $n % 2 === 0);

        expect($evens)->toHaveCount(3);
        expect(array_values($evens))->toBe([2, 4, 6]);
    });
});
