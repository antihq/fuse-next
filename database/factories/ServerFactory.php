<?php

namespace Database\Factories;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    protected $model = Server::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->company(),
            'ip_address' => fake()->ipv4(),
            'status' => ServerStatus::Pending,
        ];
    }

    public function provisioning(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ServerStatus::Provisioning,
        ]);
    }

    public function provisioned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ServerStatus::Provisioned,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ServerStatus::Failed,
        ]);
    }

    public function connected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ServerStatus::Connected,
        ]);
    }
}
