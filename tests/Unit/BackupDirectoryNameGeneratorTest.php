<?php

namespace Tests\Unit;

use App\Support\BackupDirectoryNameGenerator;
use Tests\TestCase;

class BackupDirectoryNameGeneratorTest extends TestCase
{
    public function test_it_generates_expected_directory_names(): void
    {
        $generator = new BackupDirectoryNameGenerator();

        $this->assertSame('lemusi_hciii', $generator->generate('Lemusi H/C III'));
        $this->assertSame('405_brigade_hciii', $generator->generate('405 Brigade HC III'));
        $this->assertSame('masaka_rrh', $generator->generate('Masaka RRH'));
        $this->assertSame('st_josephs_hospital', $generator->generate("St. Joseph's Hospital"));
    }

    public function test_it_removes_duplicate_underscores_and_preserves_numbers(): void
    {
        $generator = new BackupDirectoryNameGenerator();

        $this->assertSame('abc_123_gh', $generator->generate('ABC / 123 (GH)'));
        $this->assertSame('foo_bar', $generator->generate('Foo___Bar'));
    }
}
