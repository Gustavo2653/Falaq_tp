<?php

namespace Tests\Feature;

use App\Models\Evento;
use App\Models\Pergunta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Não depende de rodar "npm run build" para testar as views
        $this->withoutVite();
    }

    // ---------- TICKET #001 (Segurança) ----------

    public function test_rejeita_pergunta_vazia_com_422(): void
    {
        $evento = Evento::create(['titulo' => 'Evento']);

        $this->postJson(route('eventos.perguntas.store', $evento->id), ['texto' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('texto');

        $this->assertDatabaseCount('perguntas', 0);
    }

    public function test_rejeita_pergunta_de_uma_letra_com_422(): void
    {
        $evento = Evento::create(['titulo' => 'Evento']);

        $this->postJson(route('eventos.perguntas.store', $evento->id), ['texto' => 'a'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('texto');
    }

    public function test_rejeita_pergunta_com_mais_de_255_caracteres(): void
    {
        $evento = Evento::create(['titulo' => 'Evento']);

        $this->postJson(route('eventos.perguntas.store', $evento->id), ['texto' => str_repeat('a', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('texto');
    }

    public function test_rejeita_evento_inexistente_com_422(): void
    {
        $this->postJson(route('eventos.perguntas.store', 999), ['texto' => 'Uma pergunta válida aqui?'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('evento_id');
    }

    public function test_aceita_pergunta_valida(): void
    {
        $user = User::factory()->create();
        $evento = Evento::create(['titulo' => 'Evento']);

        $this->actingAs($user)
            ->post(route('eventos.perguntas.store', $evento->id), ['texto' => 'Uma pergunta válida aqui?'])
            ->assertRedirect(route('eventos.show', $evento->id))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('perguntas', [
            'evento_id' => $evento->id,
            'texto' => 'Uma pergunta válida aqui?',
        ]);
    }

    // ---------- TICKET #002 (Performance) ----------

    public function test_show_pagina_10_perguntas_do_evento_mais_recentes_primeiro(): void
    {
        $evento = Evento::create(['titulo' => 'Evento A']);
        $outro = Evento::create(['titulo' => 'Evento B']);

        for ($i = 1; $i <= 25; $i++) {
            Pergunta::create([
                'evento_id' => $evento->id,
                'texto' => "Pergunta do evento A numero {$i}",
                'status' => 'pendente',
            ])->forceFill(['created_at' => now()->subMinutes(100 - $i)])->save();
        }
        Pergunta::create(['evento_id' => $outro->id, 'texto' => 'Pergunta de OUTRO evento', 'status' => 'pendente']);

        $response = $this->get(route('eventos.show', $evento->id))->assertOk();

        $perguntas = $response->viewData('perguntas');
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $perguntas);
        $this->assertCount(10, $perguntas->items());
        $this->assertSame(25, $perguntas->total());
        $this->assertSame('Pergunta do evento A numero 25', $perguntas->first()->texto);
        $this->assertTrue($perguntas->every(fn ($p) => $p->evento_id === $evento->id));

        $response->assertDontSee('Pergunta de OUTRO evento')
            ->assertSee('pagination', false)
            ->assertSee('?page=2', false);
    }
}
