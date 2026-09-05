<?php

namespace CureRx\Tests\Unit\Core;

use CureRx\Core\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    // --- required ---

    public function testRequiredFailsOnEmptyString(): void
    {
        $v = new Validator(['name' => '']);
        $v->required('name');
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors());
    }

    public function testRequiredPassesWhenValuePresent(): void
    {
        $v = new Validator(['name' => 'Alice']);
        $v->required('name');
        $this->assertFalse($v->fails());
        $this->assertEmpty($v->errors());
    }

    public function testRequiredFailsWhenKeyAbsent(): void
    {
        $v = new Validator([]);
        $v->required('name');
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors());
    }

    // --- integer ---

    public function testIntegerRejectsNonNumericString(): void
    {
        $v = new Validator(['age' => 'abc']);
        $v->integer('age');
        $this->assertTrue($v->fails());
    }

    public function testIntegerAcceptsValidInteger(): void
    {
        $v = new Validator(['age' => '25']);
        $v->integer('age');
        $this->assertFalse($v->fails());
    }

    public function testIntegerAcceptsZero(): void
    {
        // Regression: integer(0) was rejected as falsy before fix in Phase 3
        $v = new Validator(['count' => '0']);
        $v->integer('count', 0);
        $this->assertFalse($v->fails());
    }

    public function testIntegerEnforcesMinimum(): void
    {
        $v = new Validator(['age' => '-1']);
        $v->integer('age', 0);
        $this->assertTrue($v->fails());
    }

    public function testIntegerEnforcesMaximum(): void
    {
        $v = new Validator(['age' => '200']);
        $v->integer('age', 0, 150);
        $this->assertTrue($v->fails());
    }

    public function testIntegerSkipsAbsentField(): void
    {
        $v = new Validator([]);
        $v->integer('age', 0);
        $this->assertFalse($v->fails());
    }

    public function testIntegerRejectsEmptyString(): void
    {
        $v = new Validator(['age' => '']);
        $v->integer('age');
        $this->assertFalse($v->fails()); // empty string is skipped (use required() to enforce presence)
    }

    // --- decimal ---

    public function testDecimalRejectsNonNumericString(): void
    {
        $v = new Validator(['price' => 'abc']);
        $v->decimal('price');
        $this->assertTrue($v->fails());
    }

    public function testDecimalAcceptsFloatString(): void
    {
        $v = new Validator(['price' => '19.99']);
        $v->decimal('price', 0.0);
        $this->assertFalse($v->fails());
    }

    public function testDecimalAcceptsZero(): void
    {
        $v = new Validator(['price' => '0']);
        $v->decimal('price');
        $this->assertFalse($v->fails());
    }

    public function testDecimalEnforcesMinimum(): void
    {
        $v = new Validator(['price' => '-5.00']);
        $v->decimal('price', 0.0);
        $this->assertTrue($v->fails());
    }

    public function testDecimalSkipsAbsentField(): void
    {
        $v = new Validator([]);
        $v->decimal('price');
        $this->assertFalse($v->fails());
    }

    // --- enum ---

    public function testEnumRejectsValueNotInList(): void
    {
        $v = new Validator(['gender' => 'Unknown']);
        $v->enum('gender', ['Male', 'Female', 'Other']);
        $this->assertTrue($v->fails());
    }

    public function testEnumAcceptsValueInList(): void
    {
        $v = new Validator(['gender' => 'Female']);
        $v->enum('gender', ['Male', 'Female', 'Other']);
        $this->assertFalse($v->fails());
    }

    public function testEnumSkipsAbsentField(): void
    {
        $v = new Validator([]);
        $v->enum('gender', ['Male', 'Female', 'Other']);
        $this->assertFalse($v->fails());
    }

    // --- date ---

    public function testDateRejectsInvalidFormat(): void
    {
        $v = new Validator(['dob' => '31-12-2025']);
        $v->date('dob');
        $this->assertTrue($v->fails());
    }

    public function testDateAcceptsYyyyMmDd(): void
    {
        $v = new Validator(['dob' => '2025-12-31']);
        $v->date('dob');
        $this->assertFalse($v->fails());
    }

    public function testDateRejectsImpossibleDate(): void
    {
        $v = new Validator(['dob' => '2025-02-30']);
        $v->date('dob');
        $this->assertTrue($v->fails());
    }

    public function testDateSkipsEmptyField(): void
    {
        $v = new Validator(['dob' => '']);
        $v->date('dob');
        $this->assertFalse($v->fails());
    }

    // --- chaining and errors() ---

    public function testChainingCollectsMultipleErrors(): void
    {
        $v = new Validator(['name' => '', 'age' => 'abc']);
        $v->required('name')->integer('age');
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors());
        $this->assertArrayHasKey('age', $v->errors());
    }

    public function testChainingPassesAllValidData(): void
    {
        $v = new Validator(['name' => 'Alice', 'age' => '30', 'gender' => 'Female', 'dob' => '1994-06-15']);
        $v->required('name')->integer('age', 0, 150)->enum('gender', ['Male', 'Female', 'Other'])->date('dob');
        $this->assertFalse($v->fails());
        $this->assertEmpty($v->errors());
    }
}
