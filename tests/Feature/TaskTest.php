<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

Class TaskTest extends TestCase
{
    use RefreshDatabase;


    public function test_authenticated_user_can_fetch_all_tasks()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $jane = $this->getTokenForUser('jane@example.com');
        $this->createTask($jane);

        Task::factory(5)->for($user)->create();
        app('auth')->forgetGuards();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/tasks');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => ['id', 'title', 'status', 'description', 'due_date', 'created_at', 'updated_at'],
        ]);
        $response->assertJsonCount(5);
    }

    public function test_authenticated_user_can_fetch_all_tasks_with_status_filters()
    {
        $token = $this->createTaskForFilters();

        app('auth')->forgetGuards();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/tasks?status=completed');

        $response->assertStatus(200);
        $response->assertJsonCount(3);
    }

    public function test_authenticated_user_can_fetch_all_tasks_with_date_filters()
    {
        $token = $this->createTaskForFilters();

        app('auth')->forgetGuards();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/tasks?due_before=' . date('Y-m-d', strtotime('+4 days')));

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    }


    public function test_authenticated_user_can_fetch_all_tasks_with_status_date_filters()
    {
        $token = $this->createTaskForFilters();

        app('auth')->forgetGuards();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/tasks?status=completed&due_before=' . date('Y-m-d', strtotime('+4 days')));

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function test_authenticated_user_can_create_task()
    {
        $response = $this->createTask($this->getTokenForUser());

        $response->assertStatus(201);
        $response->assertJson([
                'title' => 'Test Task',
                'status' => 'in-progress',
            ]
        );
        $this->assertDatabaseHas('tasks', [
                'title'       => 'Test Task',
                'status'      => 'in-progress',
                'description' => 'Test Description',
            ]
        );
    }

    public function test_invalid_token_cannot_create_task()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer 1|abcdefghij1234567890ABCDEFGHIJabcdefghij',
        ])->postJson('/api/tasks', [
            'title' => 'Test Task',
            'description' => 'Test Description',
            'status' => 'in-progress',
            'due_date' => date('Y-m-d', strtotime('tomorrow')),
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_fetch_task()
    {
        $token = $this->getTokenForUser();
        $response = $this->createTask($token);
        $task = $response->json();

        app('auth')->forgetGuards();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/tasks/' . $task['id']);

        $response->assertStatus(200);
        $response->assertJson([
                'title' => 'Test Task',
                'status' => 'in-progress',
            ]
        );
    }

    public function test_authenticated_user_cannot_fetch_other_task()
    {
        $token = $this->getTokenForUser();
        $response = $this->createTask($token);
        $task = $response->json();

        $jane = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
        ]);

        $janeToken = $jane->createToken('test-token')->plainTextToken;

        app('auth')->forgetGuards();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $janeToken,
        ])->getJson('/api/tasks/' . $task['id']);

        $response->assertStatus(403);
    }

    public function test_invalid_token_cannot_fetch_task()
    {
        $token = $this->getTokenForUser();
        $response = $this->createTask($token);
        $task = $response->json();

        app('auth')->forgetGuards();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer 1|abcdefghij1234567890ABCDEFGHIJabcdefghij',
        ])->getJson('/api/tasks/' . $task['id']);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_update_task()
    {
        $token = $this->getTokenForUser();
        $response = $this->createTask($token);
        $task = $response->json();

        app('auth')->forgetGuards();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->putJson('/api/tasks/' . $task['id'], [
            'title' => 'Updated Test Task',
            'status' => 'completed',
        ]);

        $response->assertJson([
                'title' => 'Updated Test Task',
                'status' => 'completed',
            ]
        );
        $this->assertDatabaseHas('tasks', [
                'title'       => 'Updated Test Task',
                'status'      => 'completed',
            ]
        );

    }

    public function test_authenticated_user_can_delete_task()
    {
        $token = $this->getTokenForUser();
        $response = $this->createTask($token);
        $task = $response->json();

        app('auth')->forgetGuards();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson('/api/tasks/' . $task['id']);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('tasks', [
                'title' => 'Test Task',
            ]
        );
    }

    private function getTokenForUser($email = 'john@example.com'): string
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        return $user->createToken('test-token')->plainTextToken;

    }

    private function createTask($token): TestResponse
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/tasks', [
            'title' => 'Test Task',
            'description' => 'Test Description',
            'status' => 'in-progress',
            'due_date' => date('Y-m-d', strtotime('tomorrow')),
        ]);
    }

    private function createTaskForFilters()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        Task::factory()->for($user)->create(['due_date' => date('Y-m-d', strtotime('tomorrow')), 'status' => 'completed']);
        Task::factory()->for($user)->create(['due_date' => date('Y-m-d', strtotime('tomorrow')), 'status' => 'in-progress']);
        Task::factory()->for($user)->create(['due_date' => date('Y-m-d', strtotime('+7 days')), 'status' => 'in-progress']);
        Task::factory()->for($user)->create(['due_date' => date('Y-m-d', strtotime('+7 days')), 'status' => 'completed']);
        Task::factory()->for($user)->create(['due_date' => date('Y-m-d', strtotime('+7 days')), 'status' => 'completed']);

        return $token;
    }
}
