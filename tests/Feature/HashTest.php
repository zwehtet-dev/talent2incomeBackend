<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HashTest extends TestCase
{
    public function test_hash_driver()
    {
        $driver = config('hashing.driver');
        $this->assertSame('bcrypt', $driver);

        $hash = Hash::make('test');
        $this->assertTrue(str_starts_with($hash, '$2y$'));
        $this->assertTrue(Hash::check('test', $hash));
    }
}
