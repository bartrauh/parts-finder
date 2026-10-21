<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Workshop;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    private string $token;
    /**
     * A basic test example.
     */
    public function setUp(): void {
        parent::setUp();

        $this->token = Workshop::first()->token;
    }

    public function test_unauthenticated(): void
    {
        $response = $this->get('/api/parts');

        $response->assertStatus(401);
    }

    public function test_authenticated_404(): void
    {
        $response = $this->withToken($this->token)->get('/api');

        $response->assertStatus(404);
    }
    public function test_authenticated(): void
    {
        $response = $this->withToken($this->token)->get('/api/parts');

        $response->assertStatus(200);
    }
    public function test_parts_q(): void
    {
        $response = $this->withToken($this->token)->get('/api/parts?q=85020284');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }
public function test_quote_good(): void
    {
        $response = $this->withToken($this->token)->get('/api/quote/1');

        $response->assertStatus(200);
    }
    public function test_quote_noaccess(): void
    {
        $response = $this->withToken($this->token)->get('/api/quote/3');

        $response->assertStatus(404);
    }
}
