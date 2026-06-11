<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Pest-style Feature tests boot the framework via Tests\TestCase against the
| MySQL "testing" database. The starter kit's class-based PHPUnit tests keep
| their own RefreshDatabase usage and run unchanged under Pest.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Concurrency suite
|--------------------------------------------------------------------------
|
| Uses DatabaseTruncation instead of RefreshDatabase on purpose: the
| wrapping test transaction would make row locks invisible to the second
| MySQL connection (mysql_b). Do not "simplify" this back.
|
*/

pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concurrency');
