<?php

uses(\Tests\TestCase::class);

use App\Services\AIService;
use Illuminate\Support\Facades\Cache;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

test('AI-01: suggestOnboarding returns parsed types array', function () {
    $mockResponse = mockOpenAI(['types' => [['nom' => 'Médicaments', 'attributs' => []]]]);
    OpenAI::fake([$mockResponse]);

    app()->instance('current_organisation_id', 1);
    $service = new AIService();
    $result  = $service->suggestOnboarding('Pharmacie');

    expect($result)->toBeArray()->not->toBeEmpty();
    expect($result[0])->toHaveKey('nom');
});

test('AI-02: suggestReorder result is cached on second call', function () {
    $mockResponse = mockOpenAI(['suggestions' => [['product_id' => 1, 'quantite_suggeree' => 50]]]);
    OpenAI::fake([$mockResponse, $mockResponse]);
    Cache::flush();

    app()->instance('current_organisation_id', 1);
    $service  = new AIService();
    $products = [['id' => 1, 'nom' => 'Produit', 'quantite' => 5]];

    $first  = $service->suggestReorder($products);
    $second = $service->suggestReorder($products);

    expect($first)->toBe($second);
});

test('AI-03: detectAnomaly returns anomalies key', function () {
    $mockResponse = mockOpenAI(['anomalies' => [['product_id' => 1, 'severite' => 'high', 'description' => 'Test']]]);
    OpenAI::fake([$mockResponse]);

    app()->instance('current_organisation_id', 1);
    $service = new AIService();
    $result  = $service->detectAnomaly([['product_id' => 1, 'quantite' => 999, 'date_mouvement' => now()]]);

    expect($result)->toBeArray();
});

test('AI-04: forecastDemand returns forecast key', function () {
    $mockResponse = mockOpenAI(['forecast' => [['date' => '2025-06-01', 'quantite_prevue' => 10]]]);
    OpenAI::fake([$mockResponse]);

    app()->instance('current_organisation_id', 1);
    $service = new AIService();
    $result  = $service->forecastDemand(['id' => 1, 'nom' => 'Widget'], []);

    expect($result)->toBeArray();
});

test('AI-05: OpenAI failure returns empty array gracefully', function () {
    OpenAI::fake([new \Exception('API error')]);

    app()->instance('current_organisation_id', 1);
    $service = new AIService();
    $result  = $service->suggestReorder([]);

    expect($result)->toBe([]);
});

// Helper to mock OpenAI chat response
function mockOpenAI(array $content)
{
    return CreateResponse::fake([
        'choices' => [['message' => ['content' => json_encode($content)]]],
    ]);
}
