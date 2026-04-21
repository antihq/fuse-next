<?php

namespace Database\Factories;

use App\Enums\SiteStatus;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'domain' => $this->faker->domainName,
            'repository' => 'https://github.com/'.$this->faker->slug.'/'.$this->faker->slug.'.git',
            'status' => SiteStatus::Pending->value,
        ];
    }

    public function deploying(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'deploying',
        ]);
    }

    public function deployed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'deployed',
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }

    public function deleting(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'deleting',
        ]);
    }
}
