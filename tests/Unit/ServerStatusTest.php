<?php

use App\Enums\ServerStatus;

test('server status enum has expected cases', function () {
    $cases = ServerStatus::cases();

    expect($cases)->toHaveCount(4);
    expect(ServerStatus::Pending->value)->toBe('pending');
    expect(ServerStatus::Provisioning->value)->toBe('provisioning');
    expect(ServerStatus::Provisioned->value)->toBe('provisioned');
    expect(ServerStatus::Failed->value)->toBe('failed');
});

test('server status enum does not include connected status', function () {
    $values = collect(ServerStatus::cases())->map->value;

    expect($values)->not->toContain('connected');
});

test('server status label returns correct string for each case', function () {
    expect(ServerStatus::Pending->label())->toBe('Pending');
    expect(ServerStatus::Provisioning->label())->toBe('Provisioning');
    expect(ServerStatus::Provisioned->label())->toBe('Provisioned');
    expect(ServerStatus::Failed->label())->toBe('Failed');
});

test('server status color returns correct string for each case', function () {
    expect(ServerStatus::Pending->color())->toBe('zinc');
    expect(ServerStatus::Provisioning->color())->toBe('amber');
    expect(ServerStatus::Provisioned->color())->toBe('emerald');
    expect(ServerStatus::Failed->color())->toBe('red');
});
