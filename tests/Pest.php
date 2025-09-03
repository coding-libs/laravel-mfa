<?php

/* Minimal Pest bootstrap for unit tests */
uses()->group('unit');

// Enable Laravel Testbench for Feature tests
uses(Tests\TestCase::class)->in('Feature');
