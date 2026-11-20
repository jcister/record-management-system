<?php

use App\Models\Requests;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('redirects to a later page to reveal the highlighted request on my requests', function () {
    $user = User::factory()->create();

    actingAs($user);

    $requests = collect();

    foreach (range(1, 25) as $minuteOffset) {
        $requests->push(
            Requests::factory()->create([
                'requested_by' => $user->id,
                'created_at' => now()->subMinutes($minuteOffset),
            ])
        );
    }

    $target = $requests->last();

    $response = $this->get(route('requests.myrequests', [
        'highlight' => $target->request_id,
    ]));

    $response->assertRedirect();

    $location = $response->headers->get('Location');
    $query = parse_url((string) $location, PHP_URL_QUERY);
    parse_str((string) $query, $params);

    expect($location)->toContain('/requests/myrequests');
    expect($params)
        ->toHaveKeys(['page', 'highlight'])
        ->and($params['page'])->toBe('2')
        ->and($params['highlight'])->toBe($target->request_id);
});
