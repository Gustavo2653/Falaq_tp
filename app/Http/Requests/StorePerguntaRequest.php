<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePerguntaRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * O evento vem da URL (/eventos/{id}/perguntas), não do formulário.
     * Copiamos o {id} da rota para "evento_id" antes de validar, assim a regra
     * exists:eventos,id é aplicada e o cliente não consegue trocar o evento
     * mandando outro evento_id no corpo da requisição.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'evento_id' => $this->route('id'),
        ]);
    }

    /**
     * TICKET #001: Regras de validação estritas contra spam.
     */
    public function rules(): array
    {
        return [
            'texto'     => ['required', 'string', 'min:10', 'max:255'],
            'evento_id' => ['required', 'exists:eventos,id'],
        ];
    }

    /**
     * Mensagens de erro em português.
     */
    public function messages(): array
    {
        return [
            'texto.required'     => 'A pergunta não pode ficar vazia.',
            'texto.string'       => 'A pergunta precisa ser um texto.',
            'texto.min'          => 'A pergunta deve ter no mínimo :min caracteres.',
            'texto.max'          => 'A pergunta deve ter no máximo :max caracteres.',
            'evento_id.required' => 'O evento é obrigatório.',
            'evento_id.exists'   => 'O evento informado não existe.',
        ];
    }
}
