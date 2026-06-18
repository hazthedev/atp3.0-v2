<?php

namespace Tests\Feature\Hr;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeesTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_validate(): void
    {
        Livewire::test('hr.employees')
            ->call('new')
            ->set('employeeNo', '')
            ->call('save')
            ->assertHasErrors(['employeeNo' => 'required'])
            ->set('employeeNo', 'EMP-001')
            ->set('name', 'Mohd Hazrin')
            ->set('email', 'not-an-email')
            ->call('save')
            ->assertHasErrors(['email'])
            ->set('email', 'haz@example.com')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('employees', ['employee_no' => 'EMP-001', 'name' => 'Mohd Hazrin']);
    }

    public function test_delete(): void
    {
        $e = Employee::create(['employee_no' => 'EMP-9', 'name' => 'X']);
        Livewire::test('hr.employees')->call('delete', $e->id);
        $this->assertDatabaseMissing('employees', ['id' => $e->id]);
    }
}
